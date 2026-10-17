<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Komisi (F1.7, Blueprint B.6 + PRD 8.8): terakru dari DATA FINAL —
 * dokter perujuk dari penjualan ber-dokter (tarif di master dokter),
 * teknisi/faset dari proses lensa Complete (tarif dari Setting
 * 'lab.technician_commission_rate', persen). Basis = total penjualan
 * di luar pajak. Void → baris penyeimbang negatif di periode
 * berjalan (periode terkunci → periode berikut), bukan edit.
 *
 * Jurnal: per periode Dr Beban Komisi / Cr Utang Komisi
 * (postPeriod); pembayaran per penerima Dr Utang Komisi /
 * Cr Kas/Bank dan baris menjadi 'paid' (payBeneficiary).
 */
class CommissionService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    public function accrueForDoctor(Sale $sale): ?Commission
    {
        if ($sale->doctor_id === null || $sale->status !== 'active') {
            return null;
        }
        $doctor = $sale->doctor;
        if ($doctor === null || ! $doctor->is_active) {
            return null;
        }
        $rate = (string) $doctor->commission_rate;
        if (Money::fromDecimal($rate)->isZero()) {
            return null;
        }
        $exists = Commission::where('source_sale_id', $sale->id)
            ->where('type', 'doctor')
            ->where('beneficiary_doctor_id', $doctor->id)
            ->whereNull('corrects_id')
            ->exists();
        if ($exists) {
            return null;
        }
        $base = $this->netBase($sale);

        return Commission::create([
            'type' => 'doctor',
            'beneficiary_doctor_id' => $doctor->id,
            'source_sale_id' => $sale->id,
            'base_amount' => $base->toDecimalString(),
            'rate' => $rate,
            'amount' => $base->percent($rate)->toDecimalString(),
            'period' => substr((string) $sale->sale_date, 0, 7),
            'status' => 'accrued',
        ]);
    }

    public function accrueForTechnician(LensProcess $process): ?Commission
    {
        if ($process->technician_id === null) {
            return null;
        }
        $sale = $process->sale;
        if ($sale === null || $sale->status !== 'active') {
            return null;
        }
        $rate = (string) Setting::value('lab.technician_commission_rate', '0');
        if (Money::fromDecimal($rate)->isZero()) {
            return null;
        }
        $exists = Commission::where('source_sale_id', $sale->id)
            ->where('type', 'technician')
            ->where('beneficiary_user_id', $process->technician_id)
            ->whereNull('corrects_id')
            ->exists();
        if ($exists) {
            return null;
        }
        $base = $this->netBase($sale);
        $period = $process->completed_at !== null
            ? $process->completed_at->format('Y-m')
            : now()->format('Y-m');

        return Commission::create([
            'type' => 'technician',
            'beneficiary_user_id' => $process->technician_id,
            'source_sale_id' => $sale->id,
            'base_amount' => $base->toDecimalString(),
            'rate' => $rate,
            'amount' => $base->percent($rate)->toDecimalString(),
            'period' => $period,
            'status' => 'accrued',
        ]);
    }

    /** Baris penyeimbang untuk semua akrual penjualan yang di-void. */
    public function correctForVoidedSale(Sale $sale): int
    {
        $period = now()->format('Y-m');
        if ($this->posting->isPeriodLocked($period)) {
            $period = now()->addMonth()->format('Y-m');
        }
        $created = 0;
        $originals = Commission::where('source_sale_id', $sale->id)
            ->where('status', 'accrued')
            ->whereNull('corrects_id')
            ->get();
        foreach ($originals as $original) {
            $already = Commission::where('corrects_id', $original->id)->exists();
            if ($already) {
                continue;
            }
            Commission::create([
                'type' => $original->type,
                'beneficiary_doctor_id' => $original->beneficiary_doctor_id,
                'beneficiary_user_id' => $original->beneficiary_user_id,
                'source_sale_id' => $sale->id,
                'corrects_id' => $original->id,
                'base_amount' => $original->base_amount,
                'rate' => $original->rate,
                'amount' => Money::fromDecimal((string) $original->amount)->negate()->toDecimalString(),
                'period' => $period,
                'status' => 'corrected',
            ]);
            $created++;
        }

        return $created;
    }

    /** Jurnalkan akrual + koreksi satu periode yang belum terjurnal. */
    public function postPeriod(User $actor, string $period): ?Journal
    {
        return DB::transaction(function () use ($actor, $period) {
            $rows = Commission::where('period', $period)
                ->whereNull('journal_id')
                ->whereIn('status', ['accrued', 'corrected'])
                ->lockForUpdate()
                ->get();
            if ($rows->isEmpty()) {
                return null;
            }
            $net = Money::zero();
            foreach ($rows as $row) {
                $net = $net->add($row->amountMoney());
            }
            if ($net->isZero()) {
                // Akrual dan koreksinya saling meniadakan di periode
                // yang sama — tidak ada jurnal, baris tetap belum
                // terjurnal agar pembayaran tidak pernah menyentuhnya
                // (neto nol memang tidak dapat dibayar).
                return null;
            }
            $lines = $net->isNegative()
                ? [
                    ['account' => Chart::UTANG_KOMISI, 'debit' => $net->abs()->toDecimalString()],
                    ['account' => Chart::BEBAN_KOMISI, 'credit' => $net->abs()->toDecimalString()],
                ]
                : [
                    ['account' => Chart::BEBAN_KOMISI, 'debit' => $net->toDecimalString()],
                    ['account' => Chart::UTANG_KOMISI, 'credit' => $net->toDecimalString()],
                ];
            $journal = $this->posting->post('commission', null, $this->postingDateFor($period), $lines, __('ui.payroll.commission_journal', ['period' => $period]), $actor);
            Commission::whereIn('id', $rows->pluck('id'))->update(['journal_id' => $journal->id]);
            $this->audit->log(action: 'commissions.post', subjectType: 'commission_period', newValues: ['period' => $period, 'net' => $net->toDecimalString(), 'rows' => $rows->count()], actor: $actor);

            return $journal;
        });
    }

    /** Bayar seluruh komisi terjurnal & belum dibayar milik satu penerima. */
    public function payBeneficiary(User $actor, string $type, ?int $doctorId, ?int $userId, string $method): Journal
    {
        return DB::transaction(function () use ($actor, $type, $doctorId, $userId, $method) {
            $query = Commission::where('type', $type)
                ->whereNotNull('journal_id')
                ->whereIn('status', ['accrued', 'corrected'])
                ->lockForUpdate();
            if ($type === 'doctor') {
                $query->where('beneficiary_doctor_id', $doctorId);
            } else {
                $query->where('beneficiary_user_id', $userId);
            }
            $rows = $query->get();
            if ($rows->isEmpty()) {
                throw ValidationException::withMessages(['pay' => __('ui.payroll.nothing_to_pay')]);
            }
            $net = Money::zero();
            foreach ($rows as $row) {
                $net = $net->add($row->amountMoney());
            }
            if (! $net->isPositive()) {
                throw ValidationException::withMessages(['pay' => __('ui.payroll.nothing_to_pay')]);
            }
            $journal = $this->posting->post('commission_payment', null, now()->toDateString(), [
                ['account' => Chart::UTANG_KOMISI, 'debit' => $net->toDecimalString()],
                ['account' => Chart::tenderAccount($method), 'credit' => $net->toDecimalString()],
            ], __('ui.payroll.commission_payment_journal'), $actor);
            Commission::whereIn('id', $rows->pluck('id'))->update(['status' => 'paid', 'paid_at' => now()]);
            $this->audit->log(action: 'commissions.pay', subjectType: 'commission_payment', newValues: ['type' => $type, 'doctor_id' => $doctorId, 'user_id' => $userId, 'net' => $net->toDecimalString()], actor: $actor);

            return $journal;
        });
    }

    private function netBase(Sale $sale): Money
    {
        return Money::fromDecimal((string) $sale->total_amount)
            ->sub(Money::fromDecimal((string) $sale->tax_amount));
    }

    private function postingDateFor(string $period): string
    {
        $end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth();

        return ($end->isFuture() ? now() : $end)->toDateString();
    }
}
