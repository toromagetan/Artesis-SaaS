<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use App\Support\Numbering;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
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
    public function __construct(private readonly AuditLogger $audit) {}

    public function isPeriodLocked(string $period): bool
    {
        $row = DB::table('period_locks')->where('period', $period)->first();

        return $row !== null && $row->unlocked_by === null;
    }

    public function lockPeriod(User $actor, string $period): void
    {
        // Satu transaksi + kunci baris yang sama dengan yang diambil
        // post() (LNT-002): penutupan periode dan penandaan jurnal
        // terjadi atomik terhadap posting yang sedang berjalan.
        DB::transaction(function () use ($actor, $period) {
            $row = DB::table('period_locks')->where('period', $period)->lockForUpdate()->first();
            $values = ['locked_by' => $actor->id, 'locked_at' => now(), 'unlocked_by' => null, 'unlock_reason' => null, 'updated_at' => now()];
            if ($row === null) {
                DB::table('period_locks')->insert($values + ['period' => $period, 'created_at' => now()]);
            } else {
                DB::table('period_locks')->where('period', $period)->update($values);
            }
            Journal::whereRaw('substr(journal_date, 1, 7) = ?', [$period])->update(['period_locked' => true]);
        });
        $this->audit->log(action: 'periods.lock', subjectType: 'period_lock', newValues: ['period' => $period], actor: $actor);
    }

    public function unlockPeriod(User $actor, string $period, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.unlock_reason_required')]);
        }
        DB::transaction(function () use ($actor, $period, $reason) {
            DB::table('period_locks')->where('period', $period)->lockForUpdate()->first();
            DB::table('period_locks')->where('period', $period)->update([
                'unlocked_by' => $actor->id, 'unlock_reason' => $reason, 'updated_at' => now(),
            ]);
            Journal::whereRaw('substr(journal_date, 1, 7) = ?', [$period])->update(['period_locked' => false]);
        });
        $this->audit->log(action: 'periods.unlock', subjectType: 'period_lock', newValues: ['period' => $period, 'reason' => $reason], actor: $actor);
    }

    /**
     * @param  array<int, array{account: string, branch_id?: ?int, debit?: string, credit?: string, memo?: ?string}>  $lines
     */
    public function post(string $sourceType, ?int $sourceId, CarbonInterface|string $date, array $lines, ?string $description = null, ?User $actor = null, ?string $idempotencyKey = null): Journal
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

        return DB::transaction(function () use ($sourceType, $sourceId, $dateString, $period, $prepared, $description, $actor, $idempotencyKey) {
            // LNT-002: kunci baris periode & periksa ulang DI DALAM
            // transaksi. Pemeriksaan di luar saja menyisakan balapan:
            // posting yang lolos sesaat sebelum lockPeriod menutup
            // periode dapat masuk ke periode yang sudah terkunci.
            // lockForUpdate pada baris period_locks (unik per
            // periode) menserialkan posting terhadap lock/unlock.
            $lock = DB::table('period_locks')->where('period', $period)->lockForUpdate()->first();
            if ($lock !== null && $lock->unlocked_by === null) {
                throw ValidationException::withMessages(['period' => __('ui.accounting.period_locked', ['period' => $period])]);
            }

            $journal = Journal::create([
                'number' => Numbering::next('JU', now()),
                'journal_date' => $dateString,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'idempotency_key' => $idempotencyKey,
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
        if ($sourceId === null) {
            return $this->post($sourceType, $sourceId, $date, $lines, $description, $actor);
        }

        // Keunikan ditegakkan basis data lewat idempotency_key
        // (migrasi 000044): balapan dua pembuat bersamaan berakhir
        // dengan jurnal pemenang, bukan jurnal ganda.
        try {
            return $this->post($sourceType, $sourceId, $date, $lines, $description, $actor, $sourceType.':'.$sourceId);
        } catch (QueryException $e) {
            $winner = Journal::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            if ($winner !== null) {
                return $winner;
            }
            throw $e;
        }
    }

    public function reverse(Journal $journal, string $reason, ?User $actor = null): Journal
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.reverse_reason_required')]);
        }
        if ($journal->source_type === 'reversal') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.reverse_of_reversal')]);
        }

        // LNT-005: kunci baris jurnal asal di dalam transaksi dan
        // periksa "sudah dibalik" DI BAWAH kunci itu — pemeriksaan
        // baca-lalu-tulis tanpa kunci membiarkan dua pembalikan
        // bersamaan sama-sama lolos (pembalikan ganda). Indeks unik
        // reversal_of_id (migrasi 000045) adalah pagar basis data
        // terakhir di belakang kunci ini.
        return DB::transaction(function () use ($journal, $reason, $actor) {
            $locked = Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            if ($locked->isReversed()) {
                throw ValidationException::withMessages(['reason' => __('ui.accounting.already_reversed')]);
            }

            $lines = $locked->lines->map(fn ($line) => [
                'account' => $line->account->code,
                'branch_id' => $line->branch_id,
                'debit' => (string) $line->credit,
                'credit' => (string) $line->debit,
                'memo' => $line->memo,
            ])->all();

            $reversal = $this->post(
                'reversal', $locked->id, now(),
                $lines,
                __('ui.accounting.reversal_description', ['number' => $locked->number, 'reason' => $reason]),
                $actor,
            );
            $reversal->update(['reversal_of_id' => $locked->id]);

            return $reversal;
        });
    }
}
