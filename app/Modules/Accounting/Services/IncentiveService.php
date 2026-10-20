<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\IncentiveItem;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturnItem;
use App\Modules\Sales\Models\WarrantyCase;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Insentif berkala — mesin Artesis IncentiveService, idiom
 * Lentera. Prinsip yang dipertahankan persis:
 * - Hanya penjualan LUNAS (status aktif + paid ≥ total) dengan
 *   kasir berperan Cabang/Admin yang dinilai.
 * - Baris per SaleItem: basis = line_total item (final sesudah
 *   diskon item); persen × basis, flat × qty.
 * - Setiap penjualan juga melahirkan baris KEPALA OPTIK bagi
 *   Admin aktif TUNGGAL di cabang transaksi (fail-closed bila
 *   0 atau >1; dilewati bila admin itu sendiri penjualnya).
 * - Rule: override produk menang atas kolektif; urutan
 *   effective_from terbaru lalu id terbaru. Tanpa rule → baris
 *   cancelled ber-alasan, dan approve MENOLAK periode yang
 *   memiliki baris cancelled karena rule.
 * - Retur parsial menyusutkan basis pro-rata; retur penuh /
 *   kasus garansi membatalkan baris penjual (tanpa baris admin).
 * - TIDAK ADA jurnal akuntansi untuk insentif (approve/paid
 *   hanya perubahan status).
 */
class IncentiveService
{
    /** @var list<string> */
    public const ELIGIBLE_ROLES = ['Cabang', 'Admin'];

    public const REASON_NO_RULE = 'Rule insentif aktif tidak ditemukan untuk role atau tanggal transaksi.';

    public const REASON_WARRANTY = 'Transaksi memiliki kasus warranty.';

    public const REASON_RETURN = 'Transaksi memiliki retur pelanggan.';

    public function __construct(
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
     * Hasilkan (atau hitung ulang) periode draft untuk satu bulan
     * kalender (format Y-m). scope_key mengikuti Artesis:
     * branch_{id}_user_{id} | user_{id} | branch_{id} | all.
     */
    public function generate(User $actor, ?int $branchId, string $month, ?int $recipientUserId): IncentivePeriod
    {
        $start = CarbonImmutable::createFromFormat('!Y-m', $month);
        if ($start === false) {
            throw ValidationException::withMessages(['month' => __('ui.incentives.invalid_month')]);
        }
        $from = $start->startOfMonth();
        $to = $start->endOfMonth();

        $scopeKey = match (true) {
            $branchId !== null && $recipientUserId !== null => 'branch_'.$branchId.'_user_'.$recipientUserId,
            $recipientUserId !== null => 'user_'.$recipientUserId,
            $branchId !== null => 'branch_'.$branchId,
            default => 'all',
        };

        return DB::transaction(function () use ($actor, $branchId, $recipientUserId, $from, $to, $scopeKey) {
            $period = IncentivePeriod::firstOrCreate(
                [
                    'period_start' => $from->toDateString(),
                    'period_end' => $to->toDateString(),
                    'scope_key' => $scopeKey,
                ],
                [
                    'branch_id' => $branchId,
                    'recipient_user_id' => $recipientUserId,
                    'status' => 'draft',
                    'generated_by' => $actor->id,
                ],
            );
            $period = IncentivePeriod::lockForUpdate()->findOrFail($period->id);
            $this->ensureDraft($period);
            $this->rebuildDraft($period, $actor->id);
            $this->audit->log(action: 'incentive.generated', subjectType: IncentivePeriod::class, subjectId: $period->id, newValues: ['scope_key' => $scopeKey, 'incentive_total' => (string) $period->incentive_total], actor: $actor);

            return $period->fresh(['branch', 'recipient', 'items', 'adjustments']);
        });
    }

    /**
     * Setujui periode: hitung ulang draft terlebih dahulu, lalu
     * TOLAK bila masih ada baris cancelled karena rule tidak
     * ditemukan. Tanpa jurnal (paritas Artesis).
     */
    public function approve(User $actor, IncentivePeriod $period): IncentivePeriod
    {
        return DB::transaction(function () use ($actor, $period) {
            $period = IncentivePeriod::lockForUpdate()->findOrFail($period->id);
            $this->ensureDraft($period);
            $this->rebuildDraft($period, $actor->id);

            $blocked = $period->items()
                ->where('status', IncentiveItem::STATUS_CANCELLED)
                ->where('cancellation_reason', 'like', 'Rule insentif%')
                ->exists();
            if ($blocked) {
                throw ValidationException::withMessages(['period' => __('ui.incentives.approve_blocked_no_rule')]);
            }

            $period->update([
                'status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);
            $this->audit->log(action: 'incentive.approved', subjectType: IncentivePeriod::class, subjectId: $period->id, newValues: ['incentive_total' => (string) $period->incentive_total], actor: $actor);

            return $period->fresh();
        });
    }

    /** Tandai dibayar — perubahan status saja, tanpa jurnal. */
    public function markPaid(User $actor, IncentivePeriod $period): IncentivePeriod
    {
        if ($period->status !== 'approved') {
            throw ValidationException::withMessages(['period' => __('ui.incentives.paid_requires_approved')]);
        }
        $period->update([
            'status' => 'paid',
            'paid_by' => $actor->id,
            'paid_at' => now(),
        ]);
        $this->audit->log(action: 'incentive.paid', subjectType: IncentivePeriod::class, subjectId: $period->id, newValues: ['net_total' => $period->netTotal()->toDecimalString()], actor: $actor);

        return $period->fresh();
    }

    // ----------------------------------------------------------------

    private function rebuildDraft(IncentivePeriod $period, int $userId): void
    {
        $period->items()->delete();
        $affected = $this->affectedSales();
        $transactionTotal = Money::zero();
        $incentiveTotal = Money::zero();

        $sales = Sale::query()
            ->with(['cashier.roles', 'items'])
            ->where('status', 'active')
            ->whereColumn('paid_amount', '>=', 'total_amount')
            ->whereBetween('sale_date', [$period->period_start->toDateString(), $period->period_end->toDateString()])
            ->when($period->branch_id !== null, fn ($q) => $q->where('branch_id', $period->branch_id))
            ->when($period->recipient_user_id !== null, fn ($q) => $q->where('cashier_id', $period->recipient_user_id))
            ->orderBy('id')
            ->get();

        foreach ($sales as $sale) {
            $seller = $sale->cashier;
            $role = $seller !== null ? $this->eligibleRole($seller) : null;
            if ($role === null) {
                continue; // Penjual bukan Cabang/Admin — penjualan dilewati seluruhnya.
            }
            $saleItems = $sale->items->sortBy('id')->values();
            $saleTransaction = Money::fromDecimal((string) $sale->total_amount);
            $saleAffected = $affected[$sale->id] ?? null;
            $isWarranty = $saleAffected !== null && str_contains($saleAffected['reason'], 'warranty');
            $itemReturns = $saleAffected['item_returns'] ?? [];

            // Baris penjual: satu per SaleItem.
            foreach ($saleItems as $item) {
                $originalFinal = Money::fromDecimal((string) $item->line_total);
                $transactionTotal = $transactionTotal->add($originalFinal);
                $returnedQty = (int) ($itemReturns[$item->id] ?? 0);

                if ($isWarranty || $returnedQty >= (int) $item->qty) {
                    $this->createRow($period, $sale, $item, $seller->id, $role, $saleTransaction, null, Money::zero(), IncentiveItem::STATUS_CANCELLED,
                        $isWarranty ? self::REASON_WARRANTY : ($saleAffected['reason'] ?? self::REASON_RETURN));

                    continue;
                }

                [$remainingQty, $remainingFinal] = $this->remainingBasis($item, $returnedQty);
                $rule = $this->activeRule($role, (string) $sale->sale_date, $item->product_id);
                if ($rule === null) {
                    $this->createRow($period, $sale, $item, $seller->id, $role, $saleTransaction, null, Money::zero(), IncentiveItem::STATUS_CANCELLED, self::REASON_NO_RULE, $remainingQty, $remainingFinal);

                    continue;
                }
                $amount = $this->calculate($rule, $remainingFinal, $remainingQty);
                $this->createRow($period, $sale, $item, $seller->id, $role, $saleTransaction, $rule, $amount, IncentiveItem::STATUS_ELIGIBLE, null, $remainingQty, $remainingFinal);
                $incentiveTotal = $incentiveTotal->add($amount);
            }

            // Baris kepala optik: Admin aktif tunggal cabang (fail-closed).
            $admin = $this->resolveAdminRecipient((int) $sale->branch_id);
            if ($saleItems->isNotEmpty() && (int) $admin->id !== (int) $seller->id) {
                foreach ($saleItems as $item) {
                    $returnedQty = (int) ($itemReturns[$item->id] ?? 0);
                    if ($isWarranty || $returnedQty >= (int) $item->qty) {
                        continue; // Retur penuh/garansi: hanya baris penjual yang cancelled.
                    }
                    [$remainingQty, $remainingFinal] = $this->remainingBasis($item, $returnedQty);
                    $rule = $this->activeRule('Admin', (string) $sale->sale_date, $item->product_id);
                    if ($rule === null) {
                        $this->createRow($period, $sale, $item, $admin->id, 'Admin', $saleTransaction, null, Money::zero(), IncentiveItem::STATUS_CANCELLED, self::REASON_NO_RULE, $remainingQty, $remainingFinal);

                        continue;
                    }
                    $amount = $this->calculate($rule, $remainingFinal, $remainingQty);
                    $this->createRow($period, $sale, $item, $admin->id, 'Admin', $saleTransaction, $rule, $amount, IncentiveItem::STATUS_ELIGIBLE, null, $remainingQty, $remainingFinal);
                    $incentiveTotal = $incentiveTotal->add($amount);
                }
            }
        }

        $period->update([
            'generated_by' => $userId,
            'transaction_total' => $transactionTotal->toDecimalString(),
            'incentive_total' => $incentiveTotal->toDecimalString(),
        ]);
    }

    private function createRow(
        IncentivePeriod $period, Sale $sale, SaleItem $item, int $recipientId, string $recipientRole,
        Money $saleTransaction, ?IncentiveRule $rule, Money $amount, string $status, ?string $reason,
        ?int $quantity = null, ?Money $itemFinal = null,
    ): void {
        $qty = $quantity ?? (int) $item->qty;
        $final = $itemFinal ?? Money::fromDecimal((string) $item->line_total);
        IncentiveItem::create([
            'incentive_period_id' => $period->id,
            'sale_id' => $sale->id,
            'sale_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name_snapshot,
            'recipient_user_id' => $recipientId,
            'recipient_role' => $recipientRole,
            'incentive_rule_id' => $rule?->id,
            'calculation_type' => $rule?->calculation_type,
            'rule_value' => $rule !== null ? (string) $rule->value : '0',
            'quantity' => $qty,
            'item_subtotal' => Money::fromDecimal((string) $item->unit_price)->mul((int) $item->qty)->toDecimalString(),
            'item_final_amount' => $final->toDecimalString(),
            'transaction_amount' => $saleTransaction->toDecimalString(),
            'incentive_amount' => $amount->toDecimalString(),
            'status' => $status,
            'cancellation_reason' => $reason,
        ]);
    }

    /** Peran layak pertama pengguna (Cabang diutamakan), atau null. */
    private function eligibleRole(User $user): ?string
    {
        $names = $user->roles->pluck('name')->all();
        foreach (self::ELIGIBLE_ROLES as $role) {
            if (in_array($role, $names, true)) {
                return $role;
            }
        }

        return null;
    }

    public function activeRule(string $role, string $saleDate, ?int $productId = null): ?IncentiveRule
    {
        $base = IncentiveRule::query()
            ->where('recipient_role', $role)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $saleDate)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $saleDate))
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($productId !== null) {
            $override = (clone $base)->where('product_id', $productId)->first();
            if ($override !== null) {
                return $override;
            }
        }

        return (clone $base)->whereNull('product_id')->first();
    }

    private function calculate(IncentiveRule $rule, Money $itemFinal, int $quantity): Money
    {
        if ($rule->calculation_type === 'percent') {
            return $itemFinal->percent((string) $rule->value);
        }

        return Money::fromDecimal((string) $rule->value)->mul($quantity);
    }

    /**
     * Basis sisa sesudah retur parsial (pro-rata Artesis):
     * Q_rem = Q_orig − Q_ret; F_rem = F_orig × Q_rem / Q_orig
     * (pembulatan half-up ke sen terdekat, aritmetika integer).
     *
     * @return array{0: int, 1: Money}
     */
    private function remainingBasis(SaleItem $item, int $returnedQty): array
    {
        $originalQty = (int) $item->qty;
        $originalFinal = Money::fromDecimal((string) $item->line_total);
        if ($returnedQty <= 0 || $originalQty < 1) {
            return [$originalQty, $originalFinal];
        }
        $remainingQty = max(0, min($originalQty - $returnedQty, $originalQty));
        $cents = $originalFinal->cents() * $remainingQty;
        $quotient = intdiv($cents, $originalQty);
        $remainder = $cents % $originalQty;
        if (abs($remainder) * 2 >= $originalQty) {
            $quotient += $cents >= 0 ? 1 : -1;
        }

        return [$remainingQty, Money::of($quotient)];
    }

    /**
     * Peta penjualan terdampak: retur per item (qty teragregasi)
     * dan kasus garansi (menyeluruh per penjualan).
     *
     * @return array<int, array{reason: string, item_returns: array<int, int>}>
     */
    private function affectedSales(): array
    {
        $affected = [];
        SaleReturnItem::query()
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->selectRaw('sale_returns.sale_id as sale_id, sale_return_items.sale_item_id as sale_item_id, SUM(sale_return_items.qty) as total_qty')
            ->groupBy('sale_returns.sale_id', 'sale_return_items.sale_item_id')
            ->get()
            ->each(function ($row) use (&$affected): void {
                $saleId = (int) $row->sale_id;
                $affected[$saleId] ??= ['reason' => self::REASON_RETURN, 'item_returns' => []];
                $affected[$saleId]['item_returns'][(int) $row->sale_item_id] = (int) $row->total_qty;
            });

        WarrantyCase::query()->pluck('sale_id')->each(function ($saleId) use (&$affected): void {
            $saleId = (int) $saleId;
            $affected[$saleId] ??= ['reason' => self::REASON_WARRANTY, 'item_returns' => []];
            $affected[$saleId]['reason'] = self::REASON_WARRANTY;
        });

        return $affected;
    }

    /**
     * Admin aktif TUNGGAL di cabang — fail-closed (paritas
     * Artesis resolveAdminRecipient, cabang via pivot branches).
     */
    private function resolveAdminRecipient(int $branchId): User
    {
        $candidates = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Admin'))
            ->whereHas('branches', fn ($q) => $q->whereKey($branchId))
            ->orderBy('id')
            ->get();

        if ($candidates->count() === 0) {
            throw ValidationException::withMessages(['incentive' => __('ui.incentives.admin_not_found')]);
        }
        if ($candidates->count() > 1) {
            throw ValidationException::withMessages(['incentive' => __('ui.incentives.admin_ambiguous')]);
        }

        return $candidates->first();
    }

    private function ensureDraft(IncentivePeriod $period): void
    {
        if ($period->status !== 'draft') {
            throw ValidationException::withMessages(['incentive' => __('ui.incentives.not_draft')]);
        }
    }
}
