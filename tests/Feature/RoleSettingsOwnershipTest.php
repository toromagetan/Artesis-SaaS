<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\RoleService;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Identity\Services\UserService;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * S-03 — Kepemilikan peran & pengaturan per tenant: peran templat
 * (tenant_id NULL) hanya diubah platform/instalasi mandiri; aktor
 * tenant hanya mengubah peran milik tenantnya, tidak dapat
 * menanam izin platform, dan penugasan peran berdasarkan nama
 * tidak pernah mengambil peran tenant lain. Pengaturan yang
 * ditulis aktor tenant menjadi milik tenantnya (baca: penimpaan
 * tenant dulu, lalu global).
 */
class RoleSettingsOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $admin = User::create([
            'email_verified_at' => now(),            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        return [$tenant, $admin];
    }

    public function test_tenant_admin_cannot_edit_template_role(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        $kasir = Role::where('name', 'Kasir')->firstOrFail();
        $before = $kasir->permissions()->pluck('name')->all();

        $this->actingAs($adminA)->get('/peran/'.$kasir->id.'/ubah')->assertForbidden();
        $this->actingAs($adminA)
            ->put('/peran/'.$kasir->id, ['permissions' => ['sales.view']])
            ->assertForbidden();

        $this->assertSame($before, $kasir->refresh()->permissions()->pluck('name')->all());
    }

    public function test_tenant_admin_creates_and_edits_own_role(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');

        $this->actingAs($adminA)
            ->post('/peran', ['name' => 'kasir-senior', 'label' => 'Kasir Senior'])
            ->assertRedirect(route('roles.index'));

        $own = Role::where('name', 'kasir-senior')->firstOrFail();
        $this->assertSame($tenantA->id, $own->tenant_id);

        $this->actingAs($adminA)
            ->put('/peran/'.$own->id, ['permissions' => ['sales.view', 'sales.create']])
            ->assertRedirect(route('roles.index'));
        $this->assertEqualsCanonicalizing(
            ['sales.view', 'sales.create'],
            $own->refresh()->permissions()->pluck('name')->all(),
        );
    }

    public function test_tenant_admin_cannot_touch_other_tenant_role(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        [$tenantB, $adminB] = $this->tenantAdmin('b');

        $this->actingAs($adminB)
            ->post('/peran', ['name' => 'peran-b', 'label' => 'Peran B'])
            ->assertRedirect(route('roles.index'));
        $roleB = Role::where('name', 'peran-b')->firstOrFail();
        $this->assertSame($tenantB->id, $roleB->tenant_id);

        // Tidak terlihat di daftar A, tidak dapat dibuka/diubah.
        $this->assertFalse(Role::visibleTo($adminA)->where('name', 'peran-b')->exists());
        $this->actingAs($adminA)->get('/peran/'.$roleB->id.'/ubah')->assertForbidden();
        $this->actingAs($adminA)
            ->put('/peran/'.$roleB->id, ['permissions' => ['sales.view']])
            ->assertForbidden();
    }

    public function test_platform_can_edit_template_role(): void
    {
        $kasir = Role::where('name', 'Kasir')->firstOrFail();

        app(RoleService::class)->syncPermissions($kasir, ['sales.view'], $this->platform);

        $this->assertSame(['sales.view'], $kasir->refresh()->permissions()->pluck('name')->all());
    }

    public function test_standalone_owner_can_edit_template_role(): void
    {
        // Tanpa tenant terdaftar: pemilik instalasi mandiri tetap
        // mengelola peran global seperti sebelum SaaS.
        $owner = User::where('username', 'owner')->firstOrFail();
        $kasir = Role::where('name', 'Kasir')->firstOrFail();

        app(RoleService::class)->syncPermissions($kasir, ['sales.view'], $owner);

        $this->assertSame(['sales.view'], $kasir->refresh()->permissions()->pluck('name')->all());
    }

    public function test_tenant_actor_cannot_grant_platform_permission(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        $this->actingAs($adminA)->post('/peran', ['name' => 'peran-a', 'label' => 'Peran A']);
        $own = Role::where('name', 'peran-a')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(RoleService::class)->syncPermissions($own, ['tenants.manage'], $adminA);
    }

    public function test_role_assignment_by_name_never_crosses_tenant(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB, $adminB] = $this->tenantAdmin('b');

        // B membuat peran bayangan bernama sama dengan templat.
        $shadowB = Role::create(['name' => 'Kasir', 'label' => 'Kasir B', 'tenant_id' => $tenantB->id]);
        $template = Role::where('name', 'Kasir')->whereNull('tenant_id')->firstOrFail();

        $users = app(UserService::class);
        $userA = $users->createUser([
            'name' => 'Staf A', 'username' => 'staf-a', 'email' => 'staf-a@uji.id',
            'password' => 'rahasia-uji', 'roles' => ['Kasir'],
        ], $adminA);
        $userB = $users->createUser([
            'name' => 'Staf B', 'username' => 'staf-b', 'email' => 'staf-b@uji.id',
            'password' => 'rahasia-uji', 'roles' => ['Kasir'],
        ], $adminB);

        // A mendapat templat; B mendapat peran bayangan miliknya.
        $this->assertTrue($userA->roles()->where('roles.id', $template->id)->exists());
        $this->assertFalse($userA->roles()->where('roles.id', $shadowB->id)->exists());
        $this->assertTrue($userB->roles()->where('roles.id', $shadowB->id)->exists());
        $this->assertSame($tenantA->id, $userA->tenant_id);
        $this->assertSame($tenantB->id, $userB->tenant_id);
    }

    public function test_settings_are_scoped_per_tenant(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');

        // Baris global ditulis sebagai proses sistem (fixture).
        TenantContext::asSystem(fn () => Setting::set('store.name', 'Nama Global'));
        $this->assertSame('Nama Global', Setting::value('store.name'));

        $this->actingAs($adminA);
        Setting::set('store.name', 'Toko A');
        $this->assertSame('Toko A', Setting::value('store.name'));

        $this->actingAs($adminB);
        $this->assertSame('Nama Global', Setting::value('store.name'));
        Setting::set('store.name', 'Toko B');
        $this->assertSame('Toko B', Setting::value('store.name'));

        $this->actingAs($adminA);
        $this->assertSame('Toko A', Setting::value('store.name'));

        $this->actingAs($this->platform);
        $this->assertSame('Nama Global', Setting::value('store.name'));
        $this->assertSame(3, Setting::where('key', 'store.name')->count());
    }
}
