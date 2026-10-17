<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Kelola bagan akun (F1.1, Blueprint D.9).
 * - Kode unik; kode/kelompok/induk TERKUNCI sesudah akun memiliki
 *   baris jurnal (mengubahnya akan merusak laporan historis) — yang
 *   masih boleh diubah hanya nama.
 * - Induk wajib sekelompok dan tidak boleh berinduk lagi (maksimal
 *   dua tingkat) — mencegah siklus & hierarki tak berujung.
 * - Tidak ada hapus: hanya nonaktifkan. Menonaktifkan akun yang
 *   masih memiliki anak aktif DITOLAK.
 */
class AccountService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): Account
    {
        return DB::transaction(function () use ($actor, $data) {
            $this->assertParentValid($data['parent_id'] ?? null, $data['group'], null);

            $account = Account::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'group' => $data['group'],
                'parent_id' => $data['parent_id'] ?? null,
                'is_active' => true,
            ]);

            $this->audit->log(action: 'accounts.create', subjectType: 'account', subjectId: $account->id,
                newValues: ['code' => $account->code, 'name' => $account->name, 'group' => $account->group], actor: $actor);

            return $account;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, Account $account, array $data): Account
    {
        return DB::transaction(function () use ($actor, $account, $data) {
            $hasLines = $account->lines()->exists();
            $old = ['code' => $account->code, 'name' => $account->name, 'group' => $account->group];

            if ($hasLines) {
                foreach (['code', 'group', 'parent_id'] as $locked) {
                    if (array_key_exists($locked, $data) && (string) ($data[$locked] ?? '') !== (string) ($account->{$locked} ?? '')) {
                        throw ValidationException::withMessages([$locked => __('ui.accounting.account_locked_has_lines')]);
                    }
                }
            } else {
                $this->assertParentValid($data['parent_id'] ?? $account->parent_id, $data['group'] ?? $account->group, $account->id);
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
        if (! $active && $account->children()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['account' => __('ui.accounting.account_has_active_children')]);
        }

        $account->update(['is_active' => $active]);

        $this->audit->log(action: $active ? 'accounts.activate' : 'accounts.deactivate', subjectType: 'account',
            subjectId: $account->id, newValues: ['is_active' => $active], actor: $actor);

        return $account;
    }

    private function assertParentValid(?int $parentId, string $group, ?int $selfId): void
    {
        if ($parentId === null) {
            return;
        }
        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages(['parent_id' => __('ui.accounting.parent_invalid')]);
        }
        $parent = Account::find($parentId);
        if ($parent === null || $parent->group !== $group || $parent->parent_id !== null) {
            throw ValidationException::withMessages(['parent_id' => __('ui.accounting.parent_invalid')]);
        }
    }
}
