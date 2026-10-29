<?php

namespace Database\Seeders;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\PlanService;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;

/**
 * Penanaman SaaS (M3): definisi paket dari PlanFeatureRegistry dan
 * peran 'Platform' (plans.manage + tenants.manage) untuk aktor
 * platform. Idempoten; jalankan SETELAH IdentitySeeder (yang menanam
 * registry izin, peran bawaan, dan pengguna platform).
 *
 * Panel platform tetap digerbang ganda: middleware 'platform'
 * (is_platform) + izin di grup platform.
 */
class SaasSeeder extends Seeder
{
    public function run(): void
    {
        app(PlanService::class)->seedDefaultPlans();

        $role = Role::updateOrCreate(['name' => 'Platform'], ['label' => 'Platform']);
        $permissionIds = [];
        foreach (['plans.manage', 'tenants.manage'] as $name) {
            PermissionRegistry::assertExists($name);
            $permission = Permission::updateOrCreate(
                ['name' => $name],
                ['group_name' => PermissionRegistry::groupOf($name)],
            );
            $permissionIds[] = $permission->id;
        }
        $role->permissions()->sync($permissionIds);

        $platform = User::where('username', 'platform')->where('is_platform', true)->first();
        if ($platform !== null) {
            $platform->roles()->syncWithoutDetaching([$role->id]);
        }
    }
}
