<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\Money;
use App\Support\Numbering;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
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

    /**
     * Kunci periode berlaku PER TENANT (Modul B1): $tenantId null
     * memeriksa ember instalasi mandiri (tenant_id NULL).
     */
    public function isPeriodLocked(string $period, ?int $tenantId = null): bool
    {
        $row = DB::table('period_locks')
            ->where('period', $period)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        return $row !== null && $row->unlocked_by === null;
    }

    /** Baris kunci (periode, tenant) untuk dipakai di dalam transaksi. */
    private function lockRowQuery(string $period, ?int $tenantId)
    {
        return DB::table('period_locks')
            ->where('period', $period)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId));
    }

    /**
     * Tenant pemilik sebuah posting — GAGAL-TERTUTUP untuk semua
     * mode (putaran koreksi audit independen, temuan 2):
     *
     * - Penimpa eksplisit ($tenantOverrideSet): hanya dari entitas
     *   sumber yang tervalidasi pemanggil (mis. aset pada depresiasi
     *   konsol, jurnal asal pada pembalikan). Tenant penimpa wajib
     *   benar-benar ada; aktor bertenant/mandiri yang menyertai
     *   wajib seember dengan penimpa; aktor platform/unassigned
     *   tetap ditolak.
     * - Aktor bertenant sah: tenantnya, dan SETIAP cabang baris
     *   wajib milik tenant itu.
     * - Aktor standalone: ember NULL, dan setiap cabang baris wajib
     *   bercap tenant NULL.
     * - Aktor platform / unassigned / tenant menggantung: DITOLAK
     *   (AuthorizationException) — bukan pelaku bisnis, tidak boleh
     *   memperoleh hak posting lewat penurunan cabang.
     * - Tanpa aktor (listener domain / konsol): diturunkan dari
     *   cabang baris — SEMUA cabang wajib ada dan seember; tanpa
     *   baris bercabang sama sekali, posting hanya sah bila
     *   instalasi benar-benar mandiri (belum ada tenant), selain
     *   itu ditolak karena identitas tenant sumber tidak dapat
     *   dibuktikan.
     *
     * @param  array<int, array{branch_id?: ?int}>  $lines
     */
    private function tenantIdFor(?User $actor, array $lines, ?int $tenantOverride = null, bool $tenantOverrideSet = false): ?int
    {
        if ($tenantOverrideSet) {
            if ($tenantOverride !== null && ! Tenant::query()->whereKey($tenantOverride)->exists()) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.branch_outside_company')]);
            }
            if ($actor !== null) {
                $context = TenantContext::for($actor);
                $actorBucket = match (true) {
                    $context->isValid() => $context->tenantId(),
                    $context->isStandalone() => null,
                    default => throw new AuthorizationException('Aktor platform/unassigned tidak dapat membuat jurnal bisnis.'),
                };
                if ($actorBucket !== $tenantOverride) {
                    throw new AuthorizationException('Tenant sumber jurnal di luar konteks aktor.');
                }
            }
            $this->assertLinesBelongToBucket($lines, $tenantOverride);

            return $tenantOverride;
        }

        if ($actor !== null) {
            $context = TenantContext::for($actor);
            if ($context->isValid()) {
                $tenantId = $context->tenantId();
                $this->assertLinesBelongToBucket($lines, $tenantId);

                return $tenantId;
            }
            if ($context->isStandalone()) {
                $this->assertLinesBelongToBucket($lines, null);

                return null;
            }

            throw new AuthorizationException('Aktor platform/unassigned tidak dapat membuat jurnal bisnis.');
        }

        // Tanpa aktor: identitas tenant dibuktikan dari cabang baris.
        $bucket = null;
        $seen = false;
        foreach ($lines as $line) {
            if (empty($line['branch_id'])) {
                continue;
            }
            $branch = Branch::whereKey((int) $line['branch_id'])->first();
            if ($branch === null) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.branch_outside_company')]);
            }
            $branchTenant = $branch->tenant_id !== null ? (int) $branch->tenant_id : null;
            if (! $seen) {
                $bucket = $branchTenant;
                $seen = true;
            } elseif ($branchTenant !== $bucket) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.branch_outside_company')]);
            }
        }
        if (! $seen) {
            // Tanpa baris bercabang, ember hanya dapat dibuktikan
            // bila instalasi BENAR-BENAR mandiri (belum ada tenant
            // terdaftar — definisi standalone TenantContext): maka
            // ember NULL tidak ambigu. Pada instalasi bersama,
            // posting tanpa aktor & tanpa cabang ditolak; pemanggil
            // wajib meneruskan tenant entitas sumbernya sebagai
            // penimpa tervalidasi (mis. pembayaran supplier).
            if (Tenant::query()->count() === 0) {
                return null;
            }

            throw new AuthorizationException('Posting tanpa aktor memerlukan cabang baris atau tenant sumber eksplisit.');
        }

        return $bucket;
    }

    /**
     * Setiap baris bercabang wajib menunjuk cabang yang ADA dan
     * bertenant sama dengan ember jurnal.
     *
     * @param  array<int, array{branch_id?: ?int}>  $lines
     */
    private function assertLinesBelongToBucket(array $lines, ?int $tenantId): void
    {
        foreach ($lines as $line) {
            if (empty($line['branch_id'])) {
                continue;
            }
            $branch = Branch::whereKey((int) $line['branch_id'])->first();
            if ($branch === null || ($branch->tenant_id !== null ? (int) $branch->tenant_id : null) !== $tenantId) {
                throw ValidationException::withMessages(['lines' => __('ui.accounting.branch_outside_company')]);
            }
        }
    }

    /** Tenant ember kunci untuk aksi kunci/buka oleh seorang aktor. */
    private function lockTenantFor(User $actor): ?int
    {
        $context = TenantContext::for($actor);
        if ($context->isValid()) {
            return $context->tenantId();
        }
        if ($context->isStandalone()) {
            return null;
        }

        throw new AuthorizationException('Kunci periode hanya untuk aktor bisnis bertenant atau instalasi mandiri.');
    }

    public function lockPeriod(User $actor, string $period): void
    {
        $tenantId = $this->lockTenantFor($actor);
        // Satu transaksi + kunci baris yang sama dengan yang diambil
        // post() (LNT-002): penutupan periode dan penandaan jurnal
        // terjadi atomik terhadap posting yang sedang berjalan.
        // Kunci & penandaan hanya menyentuh ember tenant aktor
        // (Modul B1) — tenant lain tidak ikut terkunci.
        DB::transaction(function () use ($actor, $period, $tenantId) {
            $row = $this->lockRowQuery($period, $tenantId)->lockForUpdate()->first();
            $values = ['locked_by' => $actor->id, 'locked_at' => now(), 'unlocked_by' => null, 'unlock_reason' => null, 'updated_at' => now()];
            if ($row === null) {
                DB::table('period_locks')->insert($values + ['period' => $period, 'tenant_id' => $tenantId, 'created_at' => now()]);
            } else {
                $this->lockRowQuery($period, $tenantId)->update($values);
            }
            Journal::withoutGlobalScopes()
                ->whereRaw('substr(journal_date, 1, 7) = ?', [$period])
                ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
                ->update(['period_locked' => true]);
        });
        $this->audit->log(action: 'periods.lock', subjectType: 'period_lock', newValues: ['period' => $period], actor: $actor);
    }

    public function unlockPeriod(User $actor, string $period, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('ui.accounting.unlock_reason_required')]);
        }
        $tenantId = $this->lockTenantFor($actor);
        DB::transaction(function () use ($actor, $period, $reason, $tenantId) {
            $this->lockRowQuery($period, $tenantId)->lockForUpdate()->first();
            $this->lockRowQuery($period, $tenantId)->update([
                'unlocked_by' => $actor->id, 'unlock_reason' => $reason, 'updated_at' => now(),
            ]);
            Journal::withoutGlobalScopes()
                ->whereRaw('substr(journal_date, 1, 7) = ?', [$period])
                ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'), fn ($q) => $q->where('tenant_id', $tenantId))
                ->update(['period_locked' => false]);
        });
        $this->audit->log(action: 'periods.unlock', subjectType: 'period_lock', newValues: ['period' => $period, 'reason' => $reason], actor: $actor);
    }

    /**
     * @param  array<int, array{account: string, branch_id?: ?int, debit?: string, credit?: string, memo?: ?string}>  $lines
     */
    public function post(string $sourceType, ?int $sourceId, CarbonInterface|string $date, array $lines, ?string $description = null, ?User $actor = null, ?string $idempotencyKey = null, ?int $tenantOverride = null, bool $tenantOverrideSet = false): Journal
    {
        $dateString = $date instanceof CarbonInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);
        $period = substr($dateString, 0, 7);

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

        $tenantId = $this->tenantIdFor($actor, $prepared, $tenantOverride, $tenantOverrideSet);
        if ($this->isPeriodLocked($period, $tenantId)) {
            throw ValidationException::withMessages(['period' => __('ui.accounting.period_locked', ['period' => $period])]);
        }

        return DB::transaction(function () use ($sourceType, $sourceId, $dateString, $period, $prepared, $description, $actor, $idempotencyKey, $tenantId) {
            // LNT-002: kunci baris periode & periksa ulang DI DALAM
            // transaksi. Pemeriksaan di luar saja menyisakan balapan:
            // posting yang lolos sesaat sebelum lockPeriod menutup
            // periode dapat masuk ke periode yang sudah terkunci.
            // lockForUpdate pada baris period_locks (unik per
            // (periode, tenant)) menserialkan posting terhadap
            // lock/unlock DALAM ember tenant yang sama (Modul B1).
            $lock = $this->lockRowQuery($period, $tenantId)->lockForUpdate()->first();
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
                'tenant_id' => $tenantId,
            ]);
            foreach ($prepared as $line) {
                $journal->lines()->create($line);
            }

            return $journal;
        });
    }

    /** Idempoten: jurnal untuk (source_type, source_id) yang sama tidak digandakan. */
    public function postOnce(string $sourceType, ?int $sourceId, CarbonInterface|string $date, array $lines, ?string $description = null, ?User $actor = null, ?int $tenantOverride = null, bool $tenantOverrideSet = false): Journal
    {
        $existing = Journal::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($existing !== null) {
            return $existing;
        }
        if ($sourceId === null) {
            return $this->post($sourceType, $sourceId, $date, $lines, $description, $actor, null, $tenantOverride, $tenantOverrideSet);
        }

        // Keunikan ditegakkan basis data lewat idempotency_key
        // (migrasi 000044): balapan dua pembuat bersamaan berakhir
        // dengan jurnal pemenang, bukan jurnal ganda.
        try {
            return $this->post($sourceType, $sourceId, $date, $lines, $description, $actor, $sourceType.':'.$sourceId, $tenantOverride, $tenantOverrideSet);
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

            // Jurnal balik MEWARISI tenant jurnal asal — jurnal asal
            // adalah entitas sumber tervalidasi, jadi tenantnya
            // diteruskan sebagai penimpa eksplisit (putaran koreksi
            // temuan 2: pembalikan jurnal tanpa cabang pun tidak
            // boleh kehilangan ember tenantnya).
            $reversal = $this->post(
                'reversal', $locked->id, now(),
                $lines,
                __('ui.accounting.reversal_description', ['number' => $locked->number, 'reason' => $reason]),
                $actor,
                null,
                $locked->tenant_id !== null ? (int) $locked->tenant_id : null,
                true,
            );
            $reversal->update(['reversal_of_id' => $locked->id]);

            return $reversal;
        });
    }
}
