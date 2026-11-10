<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payroll (F1.7, Blueprint B.6): entri gaji per karyawan per
 * periode (pokok + tunjangan − potongan = neto, dihitung di sini);
 * entri yang sudah terjurnal TERKUNCI. Posting per periode = satu
 * jurnal Dr Beban Gaji / Cr Kas-Bank, dikelompokkan per cabang
 * karyawan agar dimensi cabang jurnal terjaga (LR per cabang).
 */
class PayrollService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AuditLogger $audit,
    ) {}

    public function upsertEntry(User $actor, int $employeeId, string $period, string $base, string $allowance, string $deduction): Payroll
    {
        $employee = User::where('id', $employeeId)->where('is_active', true)->where('is_platform', false)->first();
        // Karyawan WAJIB satu perusahaan dengan aktor (Modul B2):
        // entri gaji tidak dapat dibuat untuk staf tenant lain.
        $actorTenant = TenantContext::for($actor);
        $sameCompany = $employee !== null && match (true) {
            $actorTenant->isValid() => (int) $employee->tenant_id === $actorTenant->tenantId(),
            $actorTenant->isStandalone() => $employee->tenant_id === null,
            default => false,
        };
        if (! $sameCompany) {
            throw ValidationException::withMessages(['employee' => __('ui.payroll.employee_invalid')]);
        }
        // Ember tenant baris payroll dicap EKSPLISIT dari aktor —
        // tidak bergantung pada auth() sesi (putaran koreksi
        // temuan 4): pencarian & penulisan memakai ember ini
        // tanpa global scope, agar jalur konsol/antrean dengan
        // aktor berbeda dari sesi tetap seember.
        $bucket = $actorTenant->isValid() ? $actorTenant->tenantId() : null;
        $net = Money::fromDecimal($base)->add(Money::fromDecimal($allowance))->sub(Money::fromDecimal($deduction));
        if ($net->isNegative()) {
            throw ValidationException::withMessages(['net' => __('ui.payroll.net_negative')]);
        }

        return DB::transaction(function () use ($actor, $employee, $period, $base, $allowance, $deduction, $net, $bucket) {
            $row = Payroll::withoutGlobalScopes()
                ->where('employee_user_id', $employee->id)->where('period', $period)
                ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
                ->lockForUpdate()->first();
            if ($row !== null && $row->isPosted()) {
                throw ValidationException::withMessages(['period' => __('ui.payroll.entry_locked')]);
            }
            $row = Payroll::withoutGlobalScopes()->updateOrCreate(
                ['employee_user_id' => $employee->id, 'period' => $period, 'tenant_id' => $bucket],
                [
                    'base_amount' => Money::fromDecimal($base)->toDecimalString(),
                    'allowance_amount' => Money::fromDecimal($allowance)->toDecimalString(),
                    'deduction_amount' => Money::fromDecimal($deduction)->toDecimalString(),
                    'net_amount' => $net->toDecimalString(),
                    'created_by' => $actor->id,
                    'tenant_id' => $bucket,
                ],
            );
            $this->audit->log(action: 'payrolls.save', subjectType: 'payroll', subjectId: $row->id, newValues: ['period' => $period, 'net' => $net->toDecimalString()], actor: $actor);

            return $row;
        });
    }

    /**
     * Posting semua entri belum terjurnal pada satu periode.
     *
     * Putaran koreksi audit independen (temuan 4): pagar tenant
     * ditegakkan EKSPLISIT di sini, bukan hanya mengandalkan
     * global scope (yang tidak berlaku tanpa sesi auth, mis.
     * konsol/antrean):
     * - aktor wajib bertenant sah atau standalone; platform &
     *   unassigned ditolak (bukan pelaku bisnis);
     * - baris yang diproses HANYA baris ember aktor (query tanpa
     *   global scope + saringan tenant eksplisit di bawah kunci);
     * - setiap karyawan wajib seember dengan aktor — baris
     *   tercampur membatalkan seluruh posting, tidak disaring
     *   diam-diam;
     * - dimensi cabang jurnal hanya boleh cabang seember:
     *   relasi cabang karyawan yang menunjuk tenant lain diabaikan
     *   (bukan dipakai), dan ketiadaan cabang seember membuat
     *   baris berdimensi null seperti perilaku lama.
     */
    public function postPeriod(User $actor, string $period, string $method): ?Journal
    {
        $context = TenantContext::for($actor);
        $bucket = match (true) {
            $context->isValid() => $context->tenantId(),
            $context->isStandalone() => null,
            default => throw new AuthorizationException('Posting payroll hanya untuk aktor bisnis bertenant atau instalasi mandiri.'),
        };

        return DB::transaction(function () use ($actor, $period, $method, $bucket) {
            $rows = Payroll::withoutGlobalScopes()
                ->where('period', $period)->whereNull('journal_id')
                ->when($bucket === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $bucket))
                ->with('employee.branches')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return null;
            }
            $tender = Chart::tenderAccount($method);
            $byBranch = [];
            foreach ($rows as $row) {
                $employee = $row->employee;
                $employeeBucket = $employee?->tenant_id !== null && $employee !== null ? (int) $employee->tenant_id : null;
                if ($employee === null || $employeeBucket !== $bucket) {
                    throw ValidationException::withMessages(['employee' => __('ui.payroll.employee_invalid')]);
                }
                $branchId = $employee->branches
                    ->first(fn ($branch) => ($branch->tenant_id !== null ? (int) $branch->tenant_id : null) === $bucket)
                    ?->id;
                $key = $branchId ?? 0;
                $byBranch[$key] = ($byBranch[$key] ?? Money::zero())->add($row->net());
            }
            $lines = [];
            $total = Money::zero();
            foreach ($byBranch as $branchId => $sum) {
                if ($sum->isZero()) {
                    continue;
                }
                $dim = $branchId === 0 ? null : $branchId;
                $lines[] = ['account' => Chart::BEBAN_GAJI, 'debit' => $sum->toDecimalString(), 'branch_id' => $dim];
                $lines[] = ['account' => $tender, 'credit' => $sum->toDecimalString(), 'branch_id' => $dim];
                $total = $total->add($sum);
            }
            if ($lines === []) {
                return null;
            }
            $journal = $this->posting->post('payroll', null, $this->postingDateFor($period), $lines, __('ui.payroll.payroll_journal', ['period' => $period]), $actor);
            Payroll::withoutGlobalScopes()->whereIn('id', $rows->pluck('id'))->update(['journal_id' => $journal->id]);
            $this->audit->log(action: 'payrolls.post', subjectType: 'payroll_period', newValues: ['period' => $period, 'total' => $total->toDecimalString(), 'rows' => $rows->count()], actor: $actor);

            return $journal;
        });
    }

    private function postingDateFor(string $period): string
    {
        $end = Carbon::createFromFormat('Y-m-d', $period.'-01')->endOfMonth();

        return ($end->isFuture() ? now() : $end)->toDateString();
    }
}
