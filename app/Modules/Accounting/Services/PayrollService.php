<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use Carbon\Carbon;
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
        if ($employee === null) {
            throw ValidationException::withMessages(['employee' => __('ui.payroll.employee_invalid')]);
        }
        $net = Money::fromDecimal($base)->add(Money::fromDecimal($allowance))->sub(Money::fromDecimal($deduction));
        if ($net->isNegative()) {
            throw ValidationException::withMessages(['net' => __('ui.payroll.net_negative')]);
        }

        return DB::transaction(function () use ($actor, $employee, $period, $base, $allowance, $deduction, $net) {
            $row = Payroll::where('employee_user_id', $employee->id)->where('period', $period)->lockForUpdate()->first();
            if ($row !== null && $row->isPosted()) {
                throw ValidationException::withMessages(['period' => __('ui.payroll.entry_locked')]);
            }
            $row = Payroll::updateOrCreate(
                ['employee_user_id' => $employee->id, 'period' => $period],
                [
                    'base_amount' => Money::fromDecimal($base)->toDecimalString(),
                    'allowance_amount' => Money::fromDecimal($allowance)->toDecimalString(),
                    'deduction_amount' => Money::fromDecimal($deduction)->toDecimalString(),
                    'net_amount' => $net->toDecimalString(),
                    'created_by' => $actor->id,
                ],
            );
            $this->audit->log(action: 'payrolls.save', subjectType: 'payroll', subjectId: $row->id, newValues: ['period' => $period, 'net' => $net->toDecimalString()], actor: $actor);

            return $row;
        });
    }

    /** Posting semua entri belum terjurnal pada satu periode. */
    public function postPeriod(User $actor, string $period, string $method): ?Journal
    {
        return DB::transaction(function () use ($actor, $period, $method) {
            $rows = Payroll::where('period', $period)->whereNull('journal_id')->with('employee.branches')->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return null;
            }
            $tender = Chart::tenderAccount($method);
            $byBranch = [];
            foreach ($rows as $row) {
                $branchId = $row->employee?->branches->first()?->id;
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
            Payroll::whereIn('id', $rows->pluck('id'))->update(['journal_id' => $journal->id]);
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
