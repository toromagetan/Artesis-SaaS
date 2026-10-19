<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\IncentiveAdjustment;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Insentif berkala (paritas Artesis). Basis perhitungan:
 * penjualan AKTIF dengan cashier_id = penerima, di cabang
 * periode, sale_date dalam rentang. Aturan produk menilai item
 * produk itu (persen × Σ line_total; flat × qty); aturan umum
 * menilai seluruh penjualan penerima (persen × Σ total; flat
 * × jumlah transaksi). Alur: draft → approved (Owner/Admin,
 * jurnal Dr Beban Insentif 5-1800 / Cr Utang Insentif 2-1600)
 * → paid (Dr Utang Insentif / Cr Kas/Bank). Penyesuaian hanya
 * saat draft.
 */
class IncentiveService
{
    public const PAY_METHODS = ['cash', 'transfer'];

    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    public function createRule(User $actor, array $data): IncentiveRule
    {
        $rule = IncentiveRule::create($data + ['created_by' => $actor->id]);
        $this->audit->log(action: 'incentive_rule.created', subjectType: IncentiveRule::class, subjectId: $rule->id, newValues: ['name' => $rule->name, 'recipient_role' => $rule->recipient_role], actor: $actor);

        return $rule;
    }

    public function toggleRule(User $actor, IncentiveRule $rule): IncentiveRule
    {
        $rule->update(['is_active' => ! $rule->is_active]);
        $this->audit->log(action: 'incentive_rule.toggled', subjectType: IncentiveRule::class, subjectId: $rule->id, newValues: ['is_active' => $rule->is_active], actor: $actor);

        return $rule;
    }

    /**
     * Hasilkan periode draft untuk semua penerima yang punya
     * transaksi pada rentang. Mengembalikan [created, skipped].
     */
    public function generate(User $actor, int $branchId, string $start, string $end): array
    {
        if ($end < $start) {
            throw ValidationException::withMessages(['period_end' => __('ui.incentives.invalid_range')]);
        }
        $rules = IncentiveRule::where('is_active', true)
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))
            ->get();
        if ($rules->isEmpty()) {
            throw ValidationException::withMessages(['period_start' => __('ui.incentives.no_rules')]);
        }
        $roles = $rules->pluck('recipient_role')->unique()->all();
        $recipients = User::where('is_active', true)
            ->whereHas('branches', fn ($q) => $q->whereKey($branchId))
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->with('roles')
            ->get();

        $created = 0;
        $skipped = 0;
        foreach ($recipients as $recipient) {
            $exists = IncentivePeriod::where('period_start', $start)
                ->where('period_end', $end)
                ->where('branch_id', $branchId)
                ->where('recipient_user_id', $recipient->id)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }
            $roleNames = $recipient->roles->pluck('name')->all();
            $matching = $rules->whereIn('recipient_role', $roleNames);
            if ($matching->isEmpty()) {
                continue;
            }
            $sales = Sale::where('cashier_id', $recipient->id)
                ->where('branch_id', $branchId)
                ->where('status', 'active')
                ->whereBetween('sale_date', [$start, $end])
                ->with('items')
                ->get();
            if ($sales->isEmpty()) {
                continue;
            }

            DB::transaction(function () use ($actor, $branchId, $start, $end, $recipient, $matching, $sales, &$created) {
                $transactionTotal = Money::zero();
                foreach ($sales as $sale) {
                    $transactionTotal = $transactionTotal->add(Money::fromDecimal((string) $sale->total_amount));
                }
                $period = IncentivePeriod::create([
                    'period_start' => $start, 'period_end' => $end,
                    'branch_id' => $branchId, 'recipient_user_id' => $recipient->id,
                    'status' => 'draft',
                    'transaction_total' => $transactionTotal->toDecimalString(),
                    'incentive_total' => '0.00',
                    'generated_by' => $actor->id,
                ]);
                $total = Money::zero();
                foreach ($matching as $rule) {
                    [$basis, $qty] = $this->basisFor($rule, $sales);
                    $amount = $rule->calculation_type === 'percent'
                        ? $basis->percent((string) $rule->value)
                        : Money::fromDecimal((string) $rule->value)->mul($rule->product_id !== null ? $qty : $sales->count());
                    $period->items()->create([
                        'incentive_rule_id' => $rule->id,
                        'product_id' => $rule->product_id,
                        'basis_amount' => $basis->toDecimalString(),
                        'basis_qty' => $qty,
                        'amount' => $amount->toDecimalString(),
                    ]);
                    $total = $total->add($amount);
                }
                $period->update(['incentive_total' => $total->toDecimalString()]);
                $created++;
            });
        }

        return [$created, $skipped];
    }

    /** @return array{0: Money, 1: int} basis & qty untuk satu aturan. */
    private function basisFor(IncentiveRule $rule, $sales): array
    {
        if ($rule->product_id === null) {
            $basis = Money::zero();
            foreach ($sales as $sale) {
                $basis = $basis->add(Money::fromDecimal((string) $sale->total_amount));
            }

            return [$basis, $sales->count()];
        }
        $basis = Money::zero();
        $qty = 0;
        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                if ($item->product_id === $rule->product_id) {
                    $basis = $basis->add(Money::fromDecimal((string) $item->line_total));
                    $qty += (int) $item->qty;
                }
            }
        }

        return [$basis, $qty];
    }

    public function addAdjustment(User $actor, IncentivePeriod $period, string $amount, string $reason): IncentiveAdjustment
    {
        return DB::transaction(function () use ($actor, $period, $amount, $reason) {
            $period = IncentivePeriod::lockForUpdate()->findOrFail($period->id);
            if (! $period->isDraft()) {
                throw ValidationException::withMessages(['amount' => __('ui.incentives.adjustment_locked')]);
            }
            $value = Money::fromDecimal($amount);
            if ($value->isZero()) {
                throw ValidationException::withMessages(['amount' => __('ui.incentives.adjustment_zero')]);
            }
            $adjustment = $period->adjustments()->create([
                'amount' => $value->toDecimalString(),
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);
            $this->recompute($period);

            return $adjustment;
        });
    }

    private function recompute(IncentivePeriod $period): void
    {
        $total = Money::zero();
        foreach ($period->items()->get() as $item) {
            $total = $total->add(Money::fromDecimal((string) $item->amount));
        }
        foreach ($period->adjustments()->get() as $adjustment) {
            $total = $total->add(Money::fromDecimal((string) $adjustment->amount));
        }
        if ($total->isNegative()) {
            throw ValidationException::withMessages(['amount' => __('ui.incentives.total_negative')]);
        }
        $period->update(['incentive_total' => $total->toDecimalString()]);
    }

    public function approve(User $actor, IncentivePeriod $period): IncentivePeriod
    {
        if (! $actor->hasRole('Owner') && ! $actor->hasRole('Admin')) {
            throw ValidationException::withMessages(['status' => __('ui.incentives.approve_forbidden')]);
        }

        return DB::transaction(function () use ($actor, $period) {
            $period = IncentivePeriod::lockForUpdate()->findOrFail($period->id);
            if (! $period->isDraft()) {
                throw ValidationException::withMessages(['status' => __('ui.incentives.not_draft')]);
            }
            $total = Money::fromDecimal((string) $period->incentive_total);
            if (! $total->isPositive()) {
                throw ValidationException::withMessages(['status' => __('ui.incentives.total_zero')]);
            }
            $this->posting->post('incentive_period', $period->id, now(), [
                ['account' => Chart::BEBAN_INSENTIF, 'branch_id' => $period->branch_id, 'debit' => $total->toDecimalString(), 'memo' => 'Insentif '.$period->period_start->format('Y-m-d')],
                ['account' => Chart::UTANG_INSENTIF, 'branch_id' => $period->branch_id, 'credit' => $total->toDecimalString(), 'memo' => 'Insentif '.$period->period_start->format('Y-m-d')],
            ], 'Persetujuan insentif periode '.$period->period_start->format('d/m/Y').'–'.$period->period_end->format('d/m/Y'), $actor);
            $period->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->audit->log(action: 'incentive.approved', subjectType: IncentivePeriod::class, subjectId: $period->id, newValues: ['total' => $total->toDecimalString()], branchId: $period->branch_id, actor: $actor);

            return $period;
        });
    }

    public function pay(User $actor, IncentivePeriod $period, string $method): IncentivePeriod
    {
        return DB::transaction(function () use ($actor, $period, $method) {
            $period = IncentivePeriod::lockForUpdate()->findOrFail($period->id);
            if ($period->status !== 'approved') {
                throw ValidationException::withMessages(['status' => __('ui.incentives.not_approved')]);
            }
            $total = Money::fromDecimal((string) $period->incentive_total);
            $this->posting->post('incentive_payment', $period->id, now(), [
                ['account' => Chart::UTANG_INSENTIF, 'branch_id' => $period->branch_id, 'debit' => $total->toDecimalString(), 'memo' => 'Insentif dibayar'],
                ['account' => Chart::tenderAccount($method), 'branch_id' => $period->branch_id, 'credit' => $total->toDecimalString(), 'memo' => 'Insentif dibayar'],
            ], 'Pembayaran insentif periode '.$period->period_start->format('d/m/Y').'–'.$period->period_end->format('d/m/Y'), $actor);
            $period->update(['status' => 'paid', 'paid_by' => $actor->id, 'paid_at' => now(), 'pay_method' => $method]);
            $this->audit->log(action: 'incentive.paid', subjectType: IncentivePeriod::class, subjectId: $period->id, newValues: ['total' => $total->toDecimalString(), 'method' => $method], branchId: $period->branch_id, actor: $actor);

            return $period;
        });
    }
}
