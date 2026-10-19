<?php

namespace App\Modules\Sales\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Events\SalePaymentRecorded;
use App\Modules\Sales\Models\CustomerCredit;
use App\Modules\Sales\Models\CustomerCreditApplication;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleReturn;
use App\Support\Money;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voucher kredit pelanggan (paritas Artesis CustomerCredit +
 * RefundService::applyCredit).
 *
 * Terbit: HANYA dari retur penjualan bermetode kredit toko
 * (SaleReturnService memanggil issueFromReturn di transaksi yang
 * sama). Jurnal penerbitan mengikuti jurnal retur: Cr Kredit Toko.
 *
 * Pakai: apply() menerapkan kredit ke penjualan LAIN milik
 * pelanggan yang sama menjadi baris pembayaran metode
 * 'store_credit' (jurnal listener: Dr Kredit Toko / Cr Piutang
 * Pelanggan). Pagar (selaras Artesis): kredit aktif, sisa cukup,
 * pelanggan sama, tidak melebihi sisa tagihan penjualan, dan
 * sepasang (kredit, penjualan) hanya satu kali penerapan.
 */
class CustomerCreditService
{
    /**
     * Terbitkan voucher dari retur bermetode kredit toko.
     * Mengembalikan null bila metode bukan kredit toko atau
     * nilai retur nol. WAJIB dipanggil di dalam transaksi retur.
     */
    public function issueFromReturn(User $actor, SaleReturn $return): ?CustomerCredit
    {
        if ($return->refund_method !== 'store_credit') {
            return null;
        }
        $total = Money::fromDecimal((string) $return->total_amount);
        if (! $total->isPositive()) {
            return null;
        }

        $sale = $return->sale;
        $credit = new CustomerCredit([
            'credit_number' => Numbering::next('CR', now()),
            'customer_id' => $sale->customer_id,
            'sale_id' => $sale->id,
            'sale_return_id' => $return->id,
            'amount' => $total->toDecimalString(),
            'remaining_amount' => $total->toDecimalString(),
            'status' => CustomerCredit::STATUS_ACTIVE,
            'reason' => $return->reason,
            'issued_at' => now(),
            'created_by' => $actor->id,
        ]);
        $credit->branch_id = $sale->branch_id;
        $credit->save();

        app(AuditLogger::class)->log(
            action: 'sales.credit_issued',
            subjectType: 'customer_credit',
            subjectId: $credit->id,
            newValues: ['number' => $credit->credit_number, 'amount' => $total->toDecimalString(), 'sale_return_id' => $return->id],
            branchId: $sale->branch_id,
            actor: $actor,
        );

        return $credit;
    }

    /**
     * Terapkan voucher ke penjualan milik pelanggan yang sama.
     */
    public function apply(User $actor, CustomerCredit $credit, Sale $sale, string $amount): CustomerCreditApplication
    {
        return DB::transaction(function () use ($actor, $credit, $sale, $amount) {
            $credit = CustomerCredit::lockForUpdate()->findOrFail($credit->id);
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);

            $value = Money::fromDecimal($amount);
            if (! $value->isPositive()) {
                throw ValidationException::withMessages(['amount' => __('ui.credits.amount_invalid')]);
            }
            if (! $credit->isActive()) {
                throw ValidationException::withMessages(['credit' => __('ui.credits.not_active')]);
            }
            if ($value->cmp(Money::fromDecimal((string) $credit->remaining_amount)) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.credits.insufficient')]);
            }
            if ((int) $credit->customer_id !== (int) $sale->customer_id) {
                throw ValidationException::withMessages(['sale_id' => __('ui.credits.wrong_customer')]);
            }
            if ($sale->isVoid()) {
                throw ValidationException::withMessages(['sale_id' => __('ui.sales.sale_void')]);
            }
            if ($value->cmp($sale->remaining()) > 0) {
                throw ValidationException::withMessages(['amount' => __('ui.credits.exceeds_payable')]);
            }
            if (CustomerCreditApplication::where('customer_credit_id', $credit->id)->where('sale_id', $sale->id)->exists()) {
                throw ValidationException::withMessages(['sale_id' => __('ui.credits.already_applied')]);
            }

            $application = CustomerCreditApplication::create([
                'customer_credit_id' => $credit->id,
                'sale_id' => $sale->id,
                'amount' => $value->toDecimalString(),
                'applied_at' => now(),
                'applied_by' => $actor->id,
            ]);

            $payment = $sale->payments()->create([
                'method' => 'store_credit',
                'amount' => $value->toDecimalString(),
                'paid_at' => now(),
                'reference' => 'credit-application:'.$application->id,
                'received_by' => $actor->id,
            ]);
            $sale->update([
                'paid_amount' => Money::fromDecimal((string) $sale->paid_amount)->add($value)->toDecimalString(),
            ]);

            // Penerimaan piutang mengalir ke jadwal cicilan (F3.2),
            // sama seperti pembayaran biasa.
            app(InstallmentService::class)->allocate($sale, $value);

            $remaining = Money::fromDecimal((string) $credit->remaining_amount)->sub($value);
            $credit->update([
                'remaining_amount' => $remaining->toDecimalString(),
                'status' => $remaining->isZero() ? CustomerCredit::STATUS_EXHAUSTED : CustomerCredit::STATUS_ACTIVE,
            ]);

            app(AuditLogger::class)->log(
                action: 'sales.credit_applied',
                subjectType: 'customer_credit',
                subjectId: $credit->id,
                newValues: ['application_id' => $application->id, 'sale_id' => $sale->id, 'amount' => $value->toDecimalString()],
                branchId: $sale->branch_id,
                actor: $actor,
            );

            SalePaymentRecorded::dispatch($payment, $sale);

            return $application;
        });
    }
}
