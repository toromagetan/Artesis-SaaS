<?php

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\Services\IncentiveAdjustmentService;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Events\SaleReturned;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturn;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Retur penjualan (Blueprint B.3 + PRD Detail 2.5).
 * - Qty retur tidak boleh melampaui terjual − sudah diretur.
 * - Nilai baris retur = prorata line_total dengan aritmetika integer
 *   (half-up), BUKAN float.
 * - Pengembalian dana cash/transfer tercatat sebagai baris pembayaran
 *   negatif (mengurangi paid_amount); store_credit tidak menyentuh
 *   pembayaran penjualan asal — nilainya terbit sebagai voucher
 *   kredit pelanggan (CustomerCredit) bernomor CR yang dapat
 *   diterapkan ke penjualan lain pelanggan yang sama.
 * - Efek stok mengikuti keadaan barang lewat listener Inventory (M.4):
 *   resale → stok kembali; damaged → tidak masuk stok jual.
 */
class SaleReturnService
{
    /**
     * @param  array<int, array<string, mixed>>  $lines  sale_item_id, qty, condition
     */
    public function create(User $actor, Sale $sale, array $lines, string $reason, string $refundMethod): SaleReturn
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => __('ui.sales.items_required')]);
        }
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.sales.return_reason_required')]);
        }

        return DB::transaction(function () use ($actor, $sale, $lines, $reason, $refundMethod) {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['reason' => __('ui.sales.sale_void')]);
            }

            $prepared = [];
            $total = Money::zero();
            foreach ($lines as $i => $line) {
                $item = SaleItem::where('sale_id', $sale->id)->findOrFail($line['sale_item_id']);
                $qty = (int) $line['qty'];
                $alreadyReturned = $item->returnedQty();
                if ($qty < 1 || $qty > $item->qty - $alreadyReturned) {
                    throw ValidationException::withMessages([
                        "items.{$i}.qty" => __('ui.sales.return_qty_exceeds'),
                    ]);
                }
                // Prorata integer: amount_sen = line_total_sen × qty / item_qty (half-up)
                $lineTotalSen = Money::fromDecimal((string) $item->line_total)->cents();
                $numerator = $lineTotalSen * $qty;
                $amountSen = intdiv($numerator, $item->qty);
                if (2 * ($numerator % $item->qty) >= $item->qty) {
                    $amountSen++;
                }
                $amount = Money::of($amountSen);
                $total = $total->add($amount);
                $prepared[] = ['item' => $item, 'qty' => $qty, 'condition' => $line['condition'], 'amount' => $amount];
            }

            $return = new SaleReturn([
                'number' => Numbering::next('SR', now()),
                'sale_id' => $sale->id,
                'reason' => trim($reason),
                'refund_method' => $refundMethod,
                'total_amount' => $total->toDecimalString(),
                'created_by' => $actor->id,
            ]);
            $return->branch_id = $sale->branch_id;
            $return->save();

            foreach ($prepared as $line) {
                $return->items()->create([
                    'sale_item_id' => $line['item']->id,
                    'qty' => $line['qty'],
                    'condition' => $line['condition'],
                    'amount' => $line['amount']->toDecimalString(),
                ]);
            }

            // Refund tunai/transfer: baris pembayaran negatif.
            if ($refundMethod !== 'store_credit' && ! $total->isZero()) {
                $sale->payments()->create([
                    'method' => $refundMethod === 'transfer' ? 'transfer' : 'cash',
                    'amount' => $total->negate()->toDecimalString(),
                    'paid_at' => now(),
                    'reference' => 'RETUR '.$return->number,
                    'received_by' => $actor->id,
                ]);
                $sale->update([
                    'paid_amount' => Money::fromDecimal((string) $sale->paid_amount)->sub($total)->toDecimalString(),
                ]);
            }

            // Refund kredit toko: terbitkan voucher kredit pelanggan
            // (paritas Artesis CustomerCredit) di transaksi yang sama.
            if ($refundMethod === 'store_credit') {
                $return->setRelation('sale', $sale);
                app(CustomerCreditService::class)->issueFromReturn($actor, $return);
            }

            // Clawback insentif otomatis (paritas Artesis): retur
            // atas item yang sudah eligible di periode approved/
            // paid membuat penyesuaian negatif di periode
            // berikutnya — di transaksi yang sama, kegagalan
            // membatalkan seluruh retur.
            app(IncentiveAdjustmentService::class)->processReturn($return, $actor);

            app(AuditLogger::class)->log(
                action: 'sales.return',
                subjectType: 'sale_return',
                subjectId: $return->id,
                newValues: ['number' => $return->number, 'sale_id' => $sale->id, 'total' => $total->toDecimalString()],
                branchId: $sale->branch_id,
                actor: $actor,
            );

            SaleReturned::dispatch($return);

            return $return;
        });
    }
}
