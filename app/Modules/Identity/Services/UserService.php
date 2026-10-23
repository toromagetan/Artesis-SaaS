<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Services\OfflineDeviceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan pengguna (Blueprint D.1).
 * Kasus tepi wajib: pengguna tidak dapat menonaktifkan dirinya dan
 * tidak dapat mengubah peran/izin dirinya sendiri.
 */
class UserService
{
    /**
     * @param  array{name: string, username: string, email?: ?string, phone?: ?string, password: string, is_platform?: bool, roles?: string[], branches?: int[]}  $data
     */
    public function createUser(array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($data, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'is_platform' => (bool) ($data['is_platform'] ?? false),
            ]);

            $this->syncRolesAndBranches($user, $data, $actor, checkSelf: false);

            $this->audit($actor, 'users.create', $user, null, [
                'username' => $user->username,
                'roles' => $user->roles()->pluck('name')->all(),
            ]);

            return $user;
        });
    }

    /**
     * @param  array{name?: string, email?: ?string, phone?: ?string, password?: ?string, roles?: string[], branches?: int[]}  $data
     */
    public function updateUser(User $user, array $data, ?User $actor = null): User
    {
        return DB::transaction(function () use ($user, $data, $actor) {
            $old = [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
            ];

            $user->fill(array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
            ], fn ($v) => $v !== null));

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            $this->syncRolesAndBranches($user, $data, $actor, checkSelf: true);

            $this->audit($actor, 'users.update', $user, $old, [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles()->pluck('name')->all(),
            ]);

            return $user;
        });
    }

    public function deactivateUser(User $user, ?User $actor = null): User
    {
        if ($actor && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'user' => __('ui.users.cannot_deactivate_self'),
            ]);
        }

        // LNT-006 lapis kedua: penonaktifan mencabut seluruh perangkat
        // kasir offline milik pengguna dalam transaksi yang sama —
        // token perangkat tidak boleh hidup lebih lama dari akunnya.
        return DB::transaction(function () use ($user, $actor) {
            $user->update(['is_active' => false]);
            app(OfflineDeviceService::class)->revokeAllForUser($user, $actor);
            $this->audit($actor, 'users.deactivate', $user, ['is_active' => true], ['is_active' => false]);

            return $user;
        });
    }

    public function activateUser(User $user, ?User $actor = null): User
    {
        $user->update(['is_active' => true]);
        $this->audit($actor, 'users.activate', $user, ['is_active' => false], ['is_active' => true]);

        return $user;
    }

    /**
     * @param  array{roles?: string[], branches?: int[]}  $data
     */
    private function syncRolesAndBranches(User $user, array $data, ?User $actor, bool $checkSelf): void
    {
        if (array_key_exists('roles', $data)) {
            if ($checkSelf && $actor && $actor->id === $user->id) {
                throw ValidationException::withMessages([
                    'roles' => __('ui.users.cannot_change_own_roles'),
                ]);
            }
            $roleIds = Role::whereIn('name', $data['roles'])->pluck('id');
            $user->roles()->sync($roleIds);
        }

        if (array_key_exists('branches', $data)) {
            $branchIds = Branch::whereIn('id', $data['branches'])->pluck('id');
            $user->branches()->sync($branchIds);
        }
    }

    private function audit(?User $actor, string $action, User $subject, ?array $old, ?array $new): void
    {
        app(AuditLogger::class)->log(
            action: $action,
            subjectType: 'user',
            subjectId: $subject->id,
            oldValues: $old,
            newValues: $new,
            actor: $actor,
        );
    }
}
