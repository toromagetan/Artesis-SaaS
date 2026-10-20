<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\IncentiveAdjustment;
use App\Modules\Accounting\Models\IncentiveItem;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\SaleReturn;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Clawback insentif otomatis (paritas Artesis
 * IncentiveAdjustmentService): saat retur pelanggan terjadi atas
 * item yang sudah eligible di periode approved/paid, dibuat
 * penyesuaian NEGATIF pro-rata di periode bulan BERIKUTNYA.
 * Item historis tidak pernah diubah; penyesuaian adalah ledger
 * aditif. Dipanggil dari dalam transaksi retur — kegagalannya
 * me-rollback seluruh retur (fail-closed). Retur tanpa item
 * insentif beku = no-op.
 */
class IncentiveAdjustmentService
{
    /**
     * @return list<IncentiveAdjustment> penyesuaian yang dibuat
     */
    public function processReturn(SaleReturn $return, User $actor): array
    {
        $returnedByItem = $return->items()
            ->selectRaw('sale_item_id, SUM(qty) as total_qty')
            ->groupBy('sale_item_id')
            ->pluck('total_qty', 'sale_item_id');

        $created = [];
        foreach ($returnedByItem as $saleItemId => $returnedQty) {
            $created = [...$created, ...$this->processItem($return, (int) $saleItemId, (int) $returnedQty, $actor)];
        }

        return $created;
    }

    /**
     * @return list<IncentiveAdjustment>
     */
    private function processItem(SaleReturn $return, int $saleItemId, int $returnedQty, User $actor): array
    {
        return DB::transaction(function () use ($return, $saleItemId, $returnedQty, $actor): array {
            $candidatePeriodIds = IncentiveItem::query()
                ->where('sale_item_id', $saleItemId)
                ->where('sale_id', $return->sale_id)
                ->where('status', IncentiveItem::STATUS_ELIGIBLE)
                ->whereHas('period', fn ($q) => $q->whereIn('status', ['approved', 'paid']))
                ->pluck('incentive_period_id')
                ->unique()->sort()->values();

            if ($candidatePeriodIds->isEmpty()) {
                return [];
            }

            DB::table('incentive_periods')
                ->whereIn('id', $candidatePeriodIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get('id');

            $frozenItems = IncentiveItem::query()
                ->where('sale_item_id', $saleItemId)
                ->where('sale_id', $return->sale_id)
                ->where('status', IncentiveItem::STATUS_ELIGIBLE)
                ->whereIn('incentive_period_id', $candidatePeriodIds->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $created = [];
            foreach ($frozenItems as $item) {
                $already = IncentiveAdjustment::query()
                    ->where('original_incentive_item_id', $item->id)
                    ->where('sale_return_id', $return->id)
                    ->exists();
                if ($already) {
                    continue;
                }
                $adjustment = $this->createAdjustment($item, $return, $returnedQty, $actor);
                if ($adjustment !== null) {
                    $created[] = $adjustment;
                }
            }

            return $created;
        });
    }

    private function createAdjustment(IncentiveItem $item, SaleReturn $return, int $returnedQty, User $actor): ?IncentiveAdjustment
    {
        $sourcePeriod = $item->period;
        if ($sourcePeriod === null) {
            return null;
        }
        $originalQty = (int) $item->quantity;
        $originalIncentive = Money::fromDecimal((string) $item->incentive_amount);
        if ($originalQty <= 0 || $returnedQty <= 0 || ! $originalIncentive->isPositive()) {
            return null;
        }

        // Penjaga kumulatif pada ledger (locking read): total qty
        // yang sudah di-clawback + retur ini tidak boleh melebihi
        // qty historis item.
        $adjustedQty = (int) IncentiveAdjustment::query()
            ->where('original_incentive_item_id', $item->id)
            ->lockForUpdate()
            ->sum('returned_quantity');
        if ($adjustedQty + $returnedQty > $originalQty) {
            throw ValidationException::withMessages([
                'quantity' => __('ui.incentives.clawback_exceeds', ['qty' => $originalQty]),
            ]);
        }

        // Pro-rata dari snapshot historis (integer, half-up).
        $cents = $originalIncentive->cents() * $returnedQty;
        $quotient = intdiv($cents, $originalQty);
        $remainder = $cents % $originalQty;
        if (abs($remainder) * 2 >= $originalQty) {
            $quotient += $cents >= 0 ? 1 : -1;
        }
        $amount = Money::of(-$quotient);
        if ($amount->isZero()) {
            return null;
        }

        $target = $this->resolveNextPeriod($sourcePeriod, $actor);

        return IncentiveAdjustment::create([
            'incentive_period_id' => $target->id,
            'original_incentive_item_id' => $item->id,
            'sale_return_id' => $return->id,
            'sale_id' => $item->sale_id,
            'sale_item_id' => $item->sale_item_id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'recipient_user_id' => $item->recipient_user_id,
            'recipient_role' => $item->recipient_role,
            'returned_quantity' => $returnedQty,
            'adjustment_type' => IncentiveAdjustment::TYPE_CUSTOMER_RETURN,
            'amount' => $amount->toDecimalString(),
            'reason' => IncentiveAdjustment::REASON_CUSTOMER_RETURN,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * Periode target = bulan kalender SETELAH period_end sumber,
     * scope_key/cabang/penerima identik. Periode yang sudah ada
     * dipakai apa pun statusnya; bila belum ada dibuat draft.
     */
    private function resolveNextPeriod(IncentivePeriod $source, User $actor): IncentivePeriod
    {
        $targetStart = $source->period_end->copy()->addDay()->startOfMonth();
        $targetEnd = $targetStart->copy()->endOfMonth();

        $existing = IncentivePeriod::query()
            ->where('period_start', $targetStart->toDateString())
            ->where('period_end', $targetEnd->toDateString())
            ->where('scope_key', $source->scope_key)
            ->first();
        if ($existing !== null) {
            return IncentivePeriod::lockForUpdate()->findOrFail($existing->id);
        }

        $created = IncentivePeriod::create([
            'period_start' => $targetStart->toDateString(),
            'period_end' => $targetEnd->toDateString(),
            'scope_key' => $source->scope_key,
            'branch_id' => $source->branch_id,
            'recipient_user_id' => $source->recipient_user_id,
            'status' => 'draft',
            'generated_by' => $actor->id,
            'transaction_total' => '0.00',
            'incentive_total' => '0.00',
        ]);

        return IncentivePeriod::lockForUpdate()->findOrFail($created->id);
    }
}
