<?php

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\InsuranceClaimReceipt;
use App\Modules\Sales\Models\InsuranceUsage;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Penerimaan pembayaran klaim dari perusahaan asuransi:
 * Dr akun tender (Kas/Bank) / Cr Piutang Asuransi (1-1700),
 * sebesar penerimaan (bertahap diperbolehkan, anti-lebih).
 */
class InsuranceClaimService
{
    public const METHODS = ['cash', 'transfer', 'debit', 'qris', 'card'];

    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    public function receive(User $actor, InsuranceUsage $usage, string $amount, string $method): InsuranceClaimReceipt
    {
        return DB::transaction(function () use ($actor, $usage, $amount, $method) {
            $usage = InsuranceUsage::lockForUpdate()->with('sale')->findOrFail($usage->id);
            if ($usage->sale->isVoid()) {
                throw ValidationException::withMessages(['amount' => __('ui.insurance_claims.sale_void')]);
            }
            $value = Money::fromDecimal($amount);
            $remaining = Money::fromDecimal($usage->remainingAmount());
            if (! $value->isPositive() || $value->cmp($remaining) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.insurance_claims.amount_exceeds', ['remaining' => $remaining->format()])]);
            }

            $receipt = InsuranceClaimReceipt::create([
                'insurance_usage_id' => $usage->id,
                'amount' => $value->toDecimalString(),
                'method' => $method,
                'received_at' => now(),
                'received_by' => $actor->id,
            ]);

            $this->posting->post('insurance_claim_receipt', $receipt->id, now(), [
                ['account' => Chart::tenderAccount($method), 'branch_id' => $usage->branch_id, 'debit' => $value->toDecimalString(), 'memo' => $usage->sale->number],
                ['account' => Chart::PIUTANG_ASURANSI, 'branch_id' => $usage->branch_id, 'credit' => $value->toDecimalString(), 'memo' => $usage->sale->number],
            ], 'Penerimaan klaim asuransi '.$usage->sale->number, $actor);

            $this->audit->log(
                action: 'insurance_claim.received',
                subjectType: InsuranceUsage::class,
                subjectId: $usage->id,
                newValues: ['amount' => $value->toDecimalString(), 'method' => $method],
                branchId: $usage->branch_id,
                actor: $actor,
            );

            return $receipt;
        });
    }
}
