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
 * Pembayaran kolektif supplier (Blueprint D.7 — KONTRAK KERAS,
 * pelajaran bug produksi Artesis 2026-10-15):
 *  1. Kumpulkan receipt belum lunas supplier, urut received_date ASC
 *     lalu id ASC (FIFO deterministik).
 *  2. Validasi SELURUHNYA sebelum DB::transaction dibuka:
 *     amount > 0, ≤ total sisa, supplier ada & aktif. Nilai receipt
 *     dibaca ulang di dalam transaksi dengan lockForUpdate.
 *  3. Satu transaksi: satu pembayaran (PAY) + alokasi per receipt
 *     sampai amount habis. Σ alokasi == amount, selalu.
 *  4. Kegagalan apa pun di tengah alokasi menggulung SELURUHNYA —
 *     tidak ada keadaan tercatat-sebagian (diuji injeksi kegagalan).
 * Controller menangkap \Throwable dan mengubahnya menjadi pesan
 * ramah — tidak pernah 500 mentah.
 */
class CollectivePaymentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Receipt belum lunas milik supplier, urutan FIFO.
     *
     * @return Receipt[]
     */
    public function outstandingReceipts(Supplier $supplier): array
    {
        $receipts = Receipt::with(['items', 'allocations', 'returns', 'purchaseOrder'])
            ->whereHas('purchaseOrder', fn ($q) => $q->where('supplier_id', $supplier->id)->where('status', '!=', 'void'))
            ->orderBy('received_date')
            ->orderBy('id')
            ->get();

        return $receipts
            ->filter(fn (Receipt $r) => ! $r->remainingAmount()->isZero())
            ->values()
            ->all();
    }

    public function totalOutstanding(Supplier $supplier): Money
    {
        $total = Money::zero();
        foreach ($this->outstandingReceipts($supplier) as $receipt) {
            $total = $total->add($receipt->remainingAmount());
        }

        return $total;
    }

    public function process(
        User $actor,
        Supplier $supplier,
        string $amount,
        string $method,
        ?string $paymentDate = null,
        ?string $reference = null,
    ): PurchasePayment {
        // ---- Fase 1: validasi penuh SEBELUM transaksi dibuka ----
        if (! $supplier->is_active) {
            throw ValidationException::withMessages(['supplier_id' => __('ui.purchasing.supplier_inactive')]);
        }
        $value = Money::fromDecimal($amount);
        if ($value->isZero() || $value->isNegative()) {
            throw ValidationException::withMessages(['amount' => __('ui.purchasing.amount_positive')]);
        }
        $candidates = $this->outstandingReceipts($supplier);
        if ($candidates === []) {
            throw ValidationException::withMessages(['amount' => __('ui.purchasing.no_outstanding')]);
        }
        $totalOutstanding = Money::zero();
        foreach ($candidates as $receipt) {
            $totalOutstanding = $totalOutstanding->add($receipt->remainingAmount());
        }
        if ($value->cmp($totalOutstanding) > 0) {
            throw ValidationException::withMessages([
                'amount' => __('ui.purchasing.amount_exceeds_remaining', ['remaining' => $totalOutstanding->format()]),
            ]);
        }

        // ---- Fase 2: satu transaksi untuk pembayaran + semua alokasi ----
        return DB::transaction(function () use ($actor, $supplier, $value, $method, $paymentDate, $reference, $candidates) {
            $payment = PurchasePayment::create([
                'number' => Numbering::next('PAY', now()),
                'supplier_id' => $supplier->id,
                'method' => $method,
                'amount' => $value->toDecimalString(),
                'payment_date' => $paymentDate ?? now()->toDateString(),
                'reference' => $reference,
                'paid_by' => $actor->id,
            ]);

            $left = $value;
            foreach ($candidates as $candidate) {
                if ($left->isZero()) {
                    break;
                }
                // Baca ulang terkunci: sisa tidak boleh berubah dari validasi.
                $receipt = Receipt::where('id', $candidate->id)
                    ->with(['items', 'allocations', 'returns'])
                    ->lockForUpdate()
                    ->firstOrFail();
                $remaining = $receipt->remainingAmount();
                if ($remaining->isZero()) {
                    continue;
                }
                $share = $left->cmp($remaining) >= 0 ? $remaining : $left;
                $allocation = $payment->allocations()->create([
                    'receipt_id' => $receipt->id,
                    'amount' => $share->toDecimalString(),
                ]);
                PurchasePaymentAllocationCreated::dispatch($allocation);
                $left = $left->sub($share);
            }

            if (! $left->isZero()) {
                // Sisa berubah di antara validasi dan transaksi: gulung semua.
                throw ValidationException::withMessages(['amount' => __('ui.purchasing.collective_changed')]);
            }

            $this->audit->log(action: 'purchase_payment.collective', subjectType: 'purchase_payment', subjectId: $payment->id,
                newValues: [
                    'number' => $payment->number,
                    'amount' => $value->toDecimalString(),
                    'allocations' => $payment->allocations()->count(),
                ],
                actor: $actor);

            PurchasePaymentPosted::dispatch($payment);

            return $payment;
        });
    }
}
