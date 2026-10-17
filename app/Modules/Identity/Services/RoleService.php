<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pengelolaan peran & izin (Blueprint D.1).
 * Perubahan izin tercatat di audit_logs dan berlaku pada request
 * berikutnya (hasPermission selalu membaca basis data, tanpa cache sesi).
 */
class RoleService
{
    /**
     * @param  string[]  $permissionNames  string izin dari registry
     */
    public function syncPermissions(Role $role, array $permissionNames, ?User $actor = null): Role
    {
        foreach ($permissionNames as $name) {
            PermissionRegistry::assertExists($name);
        }

        // Kasus tepi D.1: seseorang tidak dapat mengubah izin peran yang
        // ia sandang sendiri (itu mengubah izinnya sendiri).
        if ($actor && $actor->roles()->where('roles.id', $role->id)->exists()) {
            throw ValidationException::withMessages([
                'permissions' => __('ui.roles.cannot_change_own_permissions'),
            ]);
        }

        return DB::transaction(function () use ($role, $permissionNames, $actor) {
            $old = $role->permissions()->pluck('name')->all();
            $ids = Permission::whereIn('name', $permissionNames)->pluck('id');
            $role->permissions()->sync($ids);

            app(AuditLogger::class)->log(
                action: 'roles.sync_permissions',
                subjectType: 'role',
                subjectId: $role->id,
                oldValues: ['permissions' => $old],
                newValues: ['permissions' => array_values($permissionNames)],
                actor: $actor,
            );

            return $role;
        });
    }
}
