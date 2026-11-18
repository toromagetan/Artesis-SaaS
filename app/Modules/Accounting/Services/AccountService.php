<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelola bagan akun (F1.1, Blueprint D.9).
 * - Ember per tenant (ISO-2): akun dibuat di ember aktor; mengubah
 *   dan menonaktifkan hanya akun ember sendiri — akun tenant lain
 *   dan templat platform tidak dapat disentuh aktor tenant.
 * - Kode unik per ember; kode/kelompok/induk TERKUNCI sesudah akun
 *   memiliki baris jurnal (mengubahnya akan merusak laporan
 *   historis) — yang masih boleh diubah hanya nama.
 * - Induk wajib sekelompok, SEEMBER, dan tidak boleh berinduk lagi
 *   (maksimal dua tingkat) — mencegah siklus & hierarki tak berujung.
 * - Tidak ada hapus: hanya nonaktifkan. Menonaktifkan akun yang
 *   masih memiliki anak aktif DITOLAK.
 */
class AccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Account
    {
        $bucket = Account::bucketOf($actor);

        return DB::transaction(function () use ($actor, $data, $bucket) {
            $this->assertParentValid($data['parent_id'] ?? null, $data['group'], null, $bucket);

            $account = Account::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'group' => $data['group'],
                'parent_id' => $data['parent_id'] ?? null,
                'is_active' => true,
                'tenant_id' => $bucket,
            ]);

            $this->audit->log(action: 'accounts.create', subjectType: 'account', subjectId: $account->id,
                newValues: ['code' => $account->code, 'name' => $account->name, 'group' => $account->group], actor: $actor);

            return $account;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Account $account, array $data): Account
    {
        $this->assertOwnBucket($actor, $account);

        return DB::transaction(function () use ($actor, $account, $data) {
            // Kunci baris akun + baca-pengunci baris jurnal
            // (verifikasi audit 18 Nov 2026 temuan E): pemeriksaan
            // hasLines tanpa kunci adalah TOCTOU — posting pertama
            // dapat mendarat di antara pemeriksaan dan penyimpanan,
            // lalu kode/grup berubah di atas akun yang sudah
            // terpakai. PostingService mengambil kunci baris akun
            // yang sama di dalam transaksinya, sehingga perubahan
            // akun dan posting terserialkan: keputusan di sini
            // membaca keadaan terkini di bawah kunci, bukan snapshot.
            $account = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $hasLines = $account->lines()->lockForUpdate()->exists();
            $old = ['code' => $account->code, 'name' => $account->name, 'group' => $account->group];

            if ($hasLines) {
                foreach (['code', 'group', 'parent_id'] as $locked) {
                    if (array_key_exists($locked, $data) && (string) ($data[$locked] ?? '') !== (string) ($account->{$locked} ?? '')) {
                        throw ValidationException::withMessages([$locked => __('ui.accounting.account_locked_has_lines')]);
                    }
                }
            } else {
                $this->assertParentValid($data['parent_id'] ?? $account->parent_id, $data['group'] ?? $account->group, $account->id, $account->tenant_id);
                $account->code = $data['code'] ?? $account->code;
                $account->group = $data['group'] ?? $account->group;
                $account->parent_id = $data['parent_id'] ?? $account->parent_id;
            }

            $account->name = $data['name'] ?? $account->name;
            $account->save();

            $this->audit->log(action: 'accounts.update', subjectType: 'account', subjectId: $account->id,
                oldValues: $old, newValues: ['code' => $account->code, 'name' => $account->name, 'group' => $account->group], actor: $actor);

            return $account;
        });
    }

    public function setActive(User $actor, Account $account, bool $active): Account
    {
        $this->assertOwnBucket($actor, $account);

        if (! $active && $account->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['account' => __('ui.accounting.account_has_active_children')]);
        }

        $account->update(['is_active' => $active]);

        $this->audit->log(action: $active ? 'accounts.activate' : 'accounts.deactivate', subjectType: 'account',
            subjectId: $account->id, newValues: ['is_active' => $active], actor: $actor);

        return $account;
    }

    /** Akun di luar ember aktor tidak boleh diubah aktor itu (ISO-2). */
    private function assertOwnBucket(User $actor, Account $account): void
    {
        if ($account->tenant_id !== Account::bucketOf($actor)) {
            throw new AuthorizationException('Akun berada di luar bagan aktor.');
        }
    }

    private function assertParentValid(?int $parentId, string $group, ?int $selfId, ?int $bucket): void
    {
        if ($parentId === null) {
            return;
        }
        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages(['parent_id' => __('ui.accounting.parent_invalid')]);
        }
        $parent = Account::find($parentId);
        if ($parent === null || $parent->group !== $group || $parent->parent_id !== null
            || $parent->tenant_id !== $bucket) {
            throw ValidationException::withMessages(['parent_id' => __('ui.accounting.parent_invalid')]);
        }
    }
}
