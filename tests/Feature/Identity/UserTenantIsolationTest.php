<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isolasi tenant pada pengelolaan pengguna: pengguna platform tidak pernah
 * tampil/terjangkau dari pengelolaan pengguna tenant, dan satu tenant tidak
 * dapat melihat, membuka, mengubah, atau menempelkan cabang tenant lain.
 * Aktor instalasi mandiri (tanpa tenant) tetap melihat seluruh pengguna
 * bisnis — tetapi bukan aktor platform.
 */
class UserTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $standaloneOwner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->standaloneOwner = User::where('username', 'owner')->firstOrFail();
    }

    /** @return array{Tenant, Branch, User} tenant + cabang + admin (peran Owner) */
    private function tenantAdmin(string $key): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$key, 'slug' => 'optik-'.strtolower($key),
            'email' => strtolower($key).'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'team')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$key, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin '.$key, 'username' => 'admin-'.strtolower($key),
            'email' => 'admin-'.strtolower($key).'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $branch, $admin];
    }

    /** @return string[] username pada data view daftar pengguna */
    private function listedUsernames($response): array
    {
        return collect($response->viewData('users')->items())->pluck('username')->all();
    }

    public function test_tenant_owner_list_shows_only_own_tenant_business_users(): void
    {
        [, , $adminA] = $this->tenantAdmin('Alpha');
        [, , $adminB] = $this->tenantAdmin('Beta');

        $response = $this->actingAs($adminA)->get('/pengguna')->assertOk();

        $usernames = $this->listedUsernames($response);
        $this->assertContains('admin-alpha', $usernames);
        $this->assertNotContains('admin-beta', $usernames, 'Pengguna tenant lain ikut tampil di daftar.');
        $this->assertNotContains('platform', $usernames, 'Pengguna platform ikut tampil di daftar tenant.');
        $this->assertNotContains($this->standaloneOwner->username, $usernames);
        $this->assertNotContains($adminB->username, $usernames);
    }

    public function test_tenant_owner_print_list_is_scoped_the_same_way(): void
    {
        [, , $adminA] = $this->tenantAdmin('Alpha');
        $this->tenantAdmin('Beta');

        $response = $this->actingAs($adminA)->get('/pengguna/cetak')->assertOk();

        $usernames = collect($response->viewData('users'))->pluck('username')->all();
        $this->assertContains('admin-alpha', $usernames);
        $this->assertNotContains('admin-beta', $usernames);
        $this->assertNotContains('platform', $usernames);
    }

    public function test_tenant_owner_cannot_open_platform_or_foreign_user(): void
    {
        [, , $adminA] = $this->tenantAdmin('Alpha');
        [, , $adminB] = $this->tenantAdmin('Beta');

        foreach ([$this->platform, $adminB] as $target) {
            $this->actingAs($adminA)->get('/pengguna/'.$target->id)->assertForbidden();
            $this->actingAs($adminA)->get('/pengguna/'.$target->id.'/ubah')->assertForbidden();
            $this->actingAs($adminA)->get('/pengguna/'.$target->id.'/cetak')->assertForbidden();
        }
    }

    public function test_tenant_owner_cannot_modify_platform_or_foreign_user(): void
    {
        [, , $adminA] = $this->tenantAdmin('Alpha');
        [, , $adminB] = $this->tenantAdmin('Beta');

        foreach ([$this->platform, $adminB] as $target) {
            $this->actingAs($adminA)->put('/pengguna/'.$target->id, [
                'name' => 'Diubah Paksa',
                'email' => 'paksa@uji.id',
            ])->assertForbidden();
            $this->actingAs($adminA)->post('/pengguna/'.$target->id.'/alih-aktif')->assertForbidden();
            $this->assertSame('Admin Beta', $adminB->refresh()->name);
            $this->assertTrue($target->refresh()->is_active);
        }
    }

    public function test_tenant_owner_cannot_assign_foreign_branch_to_new_user(): void
    {
        [, , $adminA] = $this->tenantAdmin('Alpha');
        [, $branchB] = $this->tenantAdmin('Beta');

        $this->actingAs($adminA)->post('/pengguna', [
            'name' => 'Staf Silang',
            'username' => 'staf-silang',
            'email' => 'silang@uji.id',
            'password' => 'rahasia123',
            'roles' => ['Kasir'],
            'branches' => [$branchB->id],
        ])->assertSessionHasErrors('branches.0');

        $this->assertDatabaseMissing('users', ['username' => 'staf-silang']);
    }

    public function test_tenant_owner_can_still_manage_own_tenant_user(): void
    {
        [, $branchA, $adminA] = $this->tenantAdmin('Alpha');

        $this->actingAs($adminA)->post('/pengguna', [
            'name' => 'Staf Alpha',
            'username' => 'staf-alpha',
            'email' => 'staf@uji.id',
            'password' => 'rahasia123',
            'roles' => ['Kasir'],
            'branches' => [$branchA->id],
        ])->assertRedirect(route('users.index'));

        $staff = User::where('username', 'staf-alpha')->firstOrFail();
        $this->assertSame($adminA->tenant_id, $staff->tenant_id);
        $this->actingAs($adminA)->get('/pengguna/'.$staff->id)->assertOk();
    }

    public function test_standalone_owner_list_excludes_platform_user(): void
    {
        $response = $this->actingAs($this->standaloneOwner)->get('/pengguna')->assertOk();

        $usernames = $this->listedUsernames($response);
        $this->assertContains($this->standaloneOwner->username, $usernames);
        $this->assertNotContains('platform', $usernames, 'Pengguna platform tampil di instalasi mandiri.');
    }
}
