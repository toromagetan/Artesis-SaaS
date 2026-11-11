<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
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

    /**
     * Tenant pemilik sebuah penjualan, dari cabang penjualan —
     * dibaca langsung ke tabel agar tidak bergantung pada
     * global scope / sesi auth (putaran koreksi temuan 5).
     */
    private function saleTenantId(Sale $sale): ?int
    {
        if ($sale->branch_id === null) {
            return null;
        }
        $tenantId = DB::table('branches')->where('id', $sale->branch_id)->value('tenant_id');

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /** Ember tenant aktor untuk posting/pembayaran komisi (gagal-tertutup). */
    private function actorBucket(User $actor): ?int
    {
        $context = TenantContext::for($actor);

        return match (true) {
            $context->isValid() => $context->tenantId(),
            $context->isStandalone() => null,
            default => throw new AuthorizationException('Aksi komisi hanya untuk aktor bisnis bertenant atau instalasi mandiri.'),
        };
    }

    public function accrueForDoctor(Sale $sale): ?Commission
    {
        if ($sale->doctor_id === null || $sale->status !== 'active') {
            return null;
        }
        // Dibaca tanpa global scope: layanan ini memang dirancang
        // berjalan tanpa sesi (antrean/konsol) dan memvalidasi ember
        // tenant secara manual di bawah — relasi terpagar scope akan
        // kosong pada konteks gagal-tertutup (temuan 8).
        $doctor = $sale->doctor()->withoutGlobalScopes()->first();
        if ($doctor === null || ! $doctor->is_active) {
            return null;
        }
        // Tenant komisi SELALU mengikuti penjualan sumber, bukan
        // auth() (temuan 5): listener dapat berjalan lewat antrean
        // atau konsol tanpa sesi tenant yang benar. Dokter yang
        // bertenant lain dari penjualan tidak menghasilkan komisi
        // (kolom dokter.tenant_id tersedia sesudah Modul C merge;
        // selama belum ada, pagar SalesService di Modul C adalah
        // lapis penegaknya).
        $bucket = $this->saleTenantId($sale);
        if (array_key_exists('tenant_id', $doctor->getAttributes())
            && ($doctor->tenant_id !== null ? (int) $doctor->tenant_id : null) !== $bucket) {
            return null;
        }
        $rate = (string) $doctor->commission_rate;
        if (Money::fromDecimal($rate)->isZero()) {
            return null;
        }
        $exists = Commission::withoutGlobalScopes()->where('source_sale_id', $sale->id)
            ->where('type', 'doctor')
            ->where('beneficiary_doctor_id', $doctor->id)
            ->whereNull('corrects_id')
            ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
            ->exists();
        if ($exists) {
            return null;
        }
        $base = $this->netBase($sale);

        return $this->createCommission([
            'type' => 'doctor',
            'beneficiary_doctor_id' => $doctor->id,
            'source_sale_id' => $sale->id,
            'base_amount' => $base->toDecimalString(),
            'rate' => $rate,
            'amount' => $base->percent($rate)->toDecimalString(),
            'period' => substr((string) $sale->sale_date, 0, 7),
            'status' => 'accrued',
        ], $bucket);
    }

    public function accrueForTechnician(LensProcess $process): ?Commission
    {
        if ($process->technician_id === null) {
            return null;
        }
        // Tanpa global scope — lihat catatan pada accrueForDoctor.
        $sale = $process->sale()->withoutGlobalScopes()->first();
        if ($sale === null || $sale->status !== 'active') {
            return null;
        }
        // Tenant komisi mengikuti penjualan sumber (temuan 5), dan
        // teknisi penerima wajib bertenant sama dengan penjualan —
        // teknisi tenant lain tidak boleh dibayar dari penjualan ini.
        $bucket = $this->saleTenantId($sale);
        $technician = User::whereKey($process->technician_id)->first();
        if ($technician === null
            || ($technician->tenant_id !== null ? (int) $technician->tenant_id : null) !== $bucket) {
            return null;
        }
        // Tarif mengikuti tenant PENJUALAN SUMBER, bukan sesi aktor:
        // jalur antrean/konsol tanpa auth tetap memakai tarif tenant
        // pemilik penjualan (audit lanjutan temuan 9). Tenant tanpa
        // penimpaan memakai default global milik platform.
        $rate = (string) Setting::valueForTenant('lab.technician_commission_rate', $bucket, '0');
        if (Money::fromDecimal($rate)->isZero()) {
            return null;
        }
        $exists = Commission::withoutGlobalScopes()->where('source_sale_id', $sale->id)
            ->where('type', 'technician')
            ->where('beneficiary_user_id', $process->technician_id)
            ->whereNull('corrects_id')
            ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
            ->exists();
        if ($exists) {
            return null;
        }
        $base = $this->netBase($sale);
        $period = $process->completed_at !== null
            ? $process->completed_at->format('Y-m')
            : now()->format('Y-m');

        return $this->createCommission([
            'type' => 'technician',
            'beneficiary_user_id' => $process->technician_id,
            'source_sale_id' => $sale->id,
            'base_amount' => $base->toDecimalString(),
            'rate' => $rate,
            'amount' => $base->percent($rate)->toDecimalString(),
            'period' => $period,
            'status' => 'accrued',
        ], $bucket);
    }

    /** Baris penyeimbang untuk semua akrual penjualan yang di-void. */
    public function correctForVoidedSale(Sale $sale): int
    {
        $bucket = $this->saleTenantId($sale);
        $period = now()->format('Y-m');
        if ($this->posting->isPeriodLocked($period, $bucket)) {
            $period = now()->addMonth()->format('Y-m');
        }
        $created = 0;
        // Tanpa global scope: koreksi wajib menemukan akrual sumber
        // walau listener berjalan tanpa sesi auth penjualan itu.
        $originals = Commission::withoutGlobalScopes()->where('source_sale_id', $sale->id)
            ->where('status', 'accrued')
            ->whereNull('corrects_id')
            ->get();
        foreach ($originals as $original) {
            $already = Commission::withoutGlobalScopes()->where('corrects_id', $original->id)->exists();
            if ($already) {
                continue;
            }
            $this->createCommission([
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
            ], $original->tenant_id !== null ? (int) $original->tenant_id : $bucket);
            $created++;
        }

        return $created;
    }

    /** Jurnalkan akrual + koreksi satu periode yang belum terjurnal. */
    public function postPeriod(User $actor, string $period): ?Journal
    {
        // Pagar ember eksplisit (temuan 5): hanya komisi ember
        // aktor yang terjurnal, tanpa bergantung global scope.
        $bucket = $this->actorBucket($actor);

        return DB::transaction(function () use ($actor, $period, $bucket) {
            $rows = Commission::withoutGlobalScopes()->where('period', $period)
                ->whereNull('journal_id')
                ->whereIn('status', ['accrued', 'corrected'])
                ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
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
            Commission::withoutGlobalScopes()->whereIn('id', $rows->pluck('id'))->update(['journal_id' => $journal->id]);
            $this->audit->log(action: 'commissions.post', subjectType: 'commission_period', newValues: ['period' => $period, 'net' => $net->toDecimalString(), 'rows' => $rows->count()], actor: $actor);

            return $journal;
        });
    }

    /** Bayar seluruh komisi terjurnal & belum dibayar milik satu penerima. */
    public function payBeneficiary(User $actor, string $type, ?int $doctorId, ?int $userId, string $method): Journal
    {
        // Pagar ember eksplisit (temuan 5): pembayaran hanya
        // menyentuh komisi ember aktor.
        $bucket = $this->actorBucket($actor);

        return DB::transaction(function () use ($actor, $type, $doctorId, $userId, $method, $bucket) {
            $query = Commission::withoutGlobalScopes()->where('type', $type)
                ->whereNotNull('journal_id')
                ->whereIn('status', ['accrued', 'corrected'])
                ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
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
            Commission::withoutGlobalScopes()->whereIn('id', $rows->pluck('id'))->update(['status' => 'paid', 'paid_at' => now()]);
            $this->audit->log(action: 'commissions.pay', subjectType: 'commission_payment', newValues: ['type' => $type, 'doctor_id' => $doctorId, 'user_id' => $userId, 'net' => $net->toDecimalString()], actor: $actor);

            return $journal;
        });
    }

    /**
     * Buat baris komisi dengan cap tenant yang DIPAKSA dari ember
     * sumber — trait TenantScoped hanya mengisi dari auth() dan
     * hanya bila kosong, jadi cap eksplisit diverifikasi ulang
     * sesudah pembuatan (temuan 5: jangan bergantung pada auth).
     */
    private function createCommission(array $attributes, ?int $bucket): Commission
    {
        $row = Commission::create($attributes + ['tenant_id' => $bucket]);
        if (($row->tenant_id !== null ? (int) $row->tenant_id : null) !== $bucket) {
            $row->forceFill(['tenant_id' => $bucket])->save();
        }

        return $row;
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
