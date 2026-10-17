<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Purchasing\Events\PurchasePaymentAllocationCreated;
use App\Modules\Purchasing\Events\PurchasePaymentPosted;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\Receipt;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pembayaran supplier tunggal (Blueprint D.7 + PRD 6.4): membayar
 * tepat satu receipt, tidak melebihi sisa tagihannya. Pembayaran
 * tersimpan tidak diedit — koreksi lewat pembayaran pembalik.
 */
class PurchasePaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paySingle(
        User $actor,
        Receipt $receipt,
        string $amount,
        string $method,
        ?string $paymentDate = null,
        ?string $reference = null,
    ): PurchasePayment {
        $value = Money::fromDecimal($amount);

        return DB::transaction(function () use ($actor, $receipt, $value, $method, $paymentDate, $reference) {
            $locked = Receipt::where('id', $receipt->id)
                ->with(['items', 'allocations', 'returns', 'purchaseOrder'])
                ->lockForUpdate()
                ->firstOrFail();

            $supplier = Supplier::find($locked->purchaseOrder->supplier_id);
            if ($supplier === null || ! $supplier->is_active) {
                throw ValidationException::withMessages(['supplier_id' => __('ui.purchasing.supplier_inactive')]);
            }
            if ($value->isZero() || $value->isNegative()) {
                throw ValidationException::withMessages(['amount' => __('ui.purchasing.amount_positive')]);
            }
            $remaining = $locked->remainingAmount();
            if ($remaining->isZero()) {
                throw ValidationException::withMessages(['amount' => __('ui.purchasing.receipt_already_paid')]);
            }
            if ($value->cmp($remaining) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.purchasing.amount_exceeds_remaining', ['remaining' => $remaining->format()])]);
            }

            $payment = PurchasePayment::create([
                'number' => Numbering::next('PAY', now()),
                'supplier_id' => $supplier->id,
                'method' => $method,
                'amount' => $value->toDecimalString(),
                'payment_date' => $paymentDate ?? now()->toDateString(),
                'reference' => $reference,
                'paid_by' => $actor->id,
            ]);
            $allocation = $payment->allocations()->create([
                'receipt_id' => $locked->id,
                'amount' => $value->toDecimalString(),
            ]);
            PurchasePaymentAllocationCreated::dispatch($allocation);

            $this->audit->log(action: 'purchase_payment.create', subjectType: 'purchase_payment', subjectId: $payment->id,
                newValues: ['number' => $payment->number, 'amount' => $value->toDecimalString(), 'receipt' => $locked->number],
                branchId: $locked->purchaseOrder->branch_id, actor: $actor);

            PurchasePaymentPosted::dispatch($payment);

            return $payment;
        });
    }
}
