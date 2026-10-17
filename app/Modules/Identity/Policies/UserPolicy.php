<?php

namespace App\Modules\Identity\Policies;

use App\Modules\Identity\Models\User;

/**
 * Pola Policy Lentera (lihat docs/POLA-POLICY.md):
 * izin registry dulu, lalu aturan sumber daya (di sini: larangan
 * menonaktifkan diri). Pagar cabang untuk model bercabang memakai
 * BranchScope + pemeriksaan allowsBranch pada policy-nya masing-masing.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function create(User $actor): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage');
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $actor->hasPermission('users.manage') && $actor->id !== $target->id;
    }
}
