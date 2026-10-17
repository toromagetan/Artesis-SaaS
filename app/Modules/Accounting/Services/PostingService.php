<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Identity\Models\User;
use App\Support\Money;
use App\Support\Numbering;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya penulis jurnal (Blueprint D.9). Dipanggil listener
 * peristiwa domain (atau jurnal manual berizin — Fase 1).
 *
 * Penjaga integritas:
 * - periode (YYYY-MM dari tanggal jurnal) terkunci → DITOLAK;
 * - Σdebit wajib == Σkredit (Money integer-sen);
 * - setiap baris: debit XOR kredit (tidak keduanya, tidak nol-nol);
 * - akun wajib ada & aktif;
 * - postOnce(): idempoten per (source_type, source_id).
 *
 * Koreksi hanya lewat reverse(): jurnal cermin bertanggal hari ini
 * dengan reversal_of_id ke jurnal asal; jurnal yang sudah dibalik
 * atau jurnal balik itu sendiri tidak dapat dibalik lagi.
 */
class PostingService
{
    public function isPeriodLocked(string $period): bool
    {
        $row = DB::table('period_locks')->where('period', $period)->first();

        return $row !== null && $row->unlocked_by === null;
    }

    public function lockPeriod(User $actor, string $period): void
    {
        DB::table('period_locks')->updateOrInsert(
            ['period' => $period],
            ['locked_by' => $actor->id, 'locked_at' => now(), 'unlocked_by' => null, 'unlock_reason' => null, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function unlockPeriod(User $actor, string $period, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.unlock_reason_required')]);
        }
        DB::table('period_locks')->where('period', $period)->update([
            'unlocked_by' => $actor->id, 'unlock_reason' => $reason, 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{account: string, branch_id?: ?int, debit?: string, credit?: string, memo?: ?string}>  $lines
     */
    public function post(string $sourceType, ?int $sourceId, CarbonInterface|string $date, array $lines, ?string $description = null, ?User $actor = null): Journal
    {
        $dateString = $date instanceof CarbonInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
        $period = substr($dateString, 0, 7);
        if ($this->isPeriodLocked($period)) {
            throw ValidationException::withMessages(['period' => __('ui.accounting.period_locked', ['period' => $period])]);
        }

        $prepared = [];
        $totalDebit = Money::zero();
        $totalCredit = Money::zero();
        foreach ($lines as $line) {
            $debit = Money::fromDecimal((string) ($line['debit'] ?? '0'));
            $credit = Money::fromDecimal((string) ($line['credit'] ?? '0'));
            if ($debit->isZero() && $credit->isZero()) {
                continue; // baris nol dilewati
            }
            if ($debit->isPositive() && $credit->isPositive()) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.line_both_sides')]);
            }
            $account = Account::where('code', $line['account'])->first();
            if ($account === null || ! $account->is_active) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.account_missing', ['code' => $line['account']])]);
            }
            $totalDebit = $totalDebit->add($debit);
            $totalCredit = $totalCredit->add($credit);
            $prepared[] = [
                'account_id' => $account->id,
                'branch_id' => $line['branch_id'] ?? null,
                'debit' => $debit->toDecimalString(),
                'credit' => $credit->toDecimalString(),
                'memo' => $line['memo'] ?? null,
            ];
        }
        if ($prepared === []) {
            throw ValidationException::withMessages(['lines' => __('ui.accounting.journal_empty')]);
        }
        if ($totalDebit->cmp($totalCredit) !== 0) {
            throw ValidationException::withMessages(['lines' => __('ui.accounting.journal_unbalanced')]);
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $dateString, $prepared, $description, $actor) {
            $journal = Journal::create([
                'number' => Numbering::next('JU', now()),
                'journal_date' => $dateString,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'description' => $description,
                'created_by' => $actor?->id,
            ]);
            foreach ($prepared as $line) {
                $journal->lines()->create($line);
            }

            return $journal;
        });
    }

    /** Idempoten: jurnal untuk (source_type, source_id) yang sama tidak digandakan. */
    public function postOnce(string $sourceType, ?int $sourceId, CarbonInterface|string $date, array $lines, ?string $description = null, ?User $actor = null): Journal
    {
        $existing = Journal::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->post($sourceType, $sourceId, $date, $lines, $description, $actor);
    }

    public function reverse(Journal $journal, string $reason, ?User $actor = null): Journal
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.reverse_reason_required')]);
        }
        if ($journal->source_type === 'reversal') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.reverse_of_reversal')]);
        }
        if ($journal->isReversed()) {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.already_reversed')]);
        }

        $lines = $journal->lines->map(fn ($line) => [
            'account' => $line->account->code,
            'branch_id' => $line->branch_id,
            'debit' => (string) $line->credit,
            'credit' => (string) $line->debit,
            'memo' => $line->memo,
        ])->all();

        $reversal = $this->post(
            'reversal', $journal->id, now(),
            $lines,
            __('ui.accounting.reversal_description', ['number' => $journal->number, 'reason' => $reason]),
            $actor,
        );
        $reversal->update(['reversal_of_id' => $journal->id]);

        return $reversal;
    }
}
