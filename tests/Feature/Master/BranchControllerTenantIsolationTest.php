<?php

namespace Tests\Feature\Master;

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
 * R1/R2 — isolasi tenant pada jalur HTTP pengelolaan cabang:
 * daftar, detail, formulir edit, pembaruan, alih aktif, dan
 * pembuatan cabang terpagar tenant di server (bukan sekadar UI).
 * Penolakan sumber daya di luar wilayah = 404; pembuatan oleh
 * aktor tanpa konteks = 403. Efek samping gudang utama hanya
 * berlaku dalam partisi kepemilikan yang sama.
 */
class BranchControllerTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, Branch, User} tenant + cabang + Owner-nya */
    private function tenantOwner(string $key): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$key, 'slug' => 'optik-'.strtolower($key),
            'email' => strtolower($key).'@uji.id',
        ]);
        // Paket best_offer: branches_max tanpa batas agar gerbang
        // paket tidak mengganggu skenario pengelolaan cabang.
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$key, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'name' => 'Owner '.$key, 'username' => 'owner-'.strtolower($key),
            'email' => 'owner-'.strtolower($key).'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        return [$tenant, $branch, $owner];
    }

    public function test_owner_index_lists_only_own_tenant_branches(): void
    {
        [, , $ownerA] = $this->tenantOwner('Alpha');
        $this->tenantOwner('Beta');

        $response = $this->actingAs($ownerA)->get(route('branches.index'));
        $response->assertOk();
        $names = collect($response->viewData('branches')->items())->pluck('name')->all();
        $this->assertSame(['Cabang Alpha'], $names);
        $response->assertDontSee('Cabang Beta');
    }

    public function test_owner_cannot_open_other_tenant_branch_detail(): void
    {
        [, , $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');

        $this->actingAs($ownerA)->get(route('branches.show', $branchB))->assertNotFound();
    }

    public function test_owner_cannot_open_other_tenant_branch_edit_form(): void
    {
        [, , $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');

        $this->actingAs($ownerA)->get(route('branches.edit', $branchB))->assertNotFound();
    }

    public function test_owner_cannot_update_other_tenant_branch(): void
    {
        [, , $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');

        $this->actingAs($ownerA)
            ->put(route('branches.update', $branchB), ['name' => 'Beta Diretas'])
            ->assertNotFound();
        $this->assertSame('Cabang Beta', $branchB->refresh()->name);
    }

    public function test_owner_cannot_toggle_other_tenant_branch(): void
    {
        [, , $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');
        $this->assertTrue($branchB->refresh()->is_active);

        $this->actingAs($ownerA)
            ->post(route('branches.toggle-active', $branchB))
            ->assertNotFound();
        $this->assertTrue($branchB->refresh()->is_active);
    }

    public function test_owner_manages_own_tenant_branch_fully(): void
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');

        $this->actingAs($ownerA)->get(route('branches.show', $branchA))->assertOk();
        $this->actingAs($ownerA)->get(route('branches.edit', $branchA))->assertOk();

        $this->actingAs($ownerA)
            ->put(route('branches.update', $branchA), ['name' => 'Alpha Baru'])
            ->assertRedirect(route('branches.index'));
        $this->assertSame('Alpha Baru', $branchA->refresh()->name);

        $this->actingAs($ownerA)
            ->post(route('branches.toggle-active', $branchA))
            ->assertRedirect();
        $this->assertFalse($branchA->refresh()->is_active);

        $this->actingAs($ownerA)
            ->post(route('branches.store'), ['name' => 'Alpha Kedua'])
            ->assertRedirect(route('branches.index'));
        $created = Branch::where('name', 'Alpha Kedua')->firstOrFail();
        $this->assertSame($ownerA->tenant_id, $created->tenant_id);
    }

    public function test_unassigned_owner_cannot_manage_branches_in_shared_install(): void
    {
        [, $branchA] = $this->tenantOwner('Alpha');
        // Owner bawaan seeder: tenant_id null pada instalasi bertenant.
        $legacy = User::where('username', 'owner')->firstOrFail();
        $this->assertNull($legacy->tenant_id);

        $response = $this->actingAs($legacy)->get(route('branches.index'));
        $response->assertOk();
        $this->assertSame(0, $response->viewData('branches')->total());

        $this->actingAs($legacy)->get(route('branches.show', $branchA))->assertNotFound();
        $this->actingAs($legacy)->get(route('branches.edit', $branchA))->assertNotFound();
        $this->actingAs($legacy)
            ->put(route('branches.update', $branchA), ['name' => 'Alpha Diretas'])
            ->assertNotFound();
        $this->actingAs($legacy)
            ->post(route('branches.toggle-active', $branchA))
            ->assertNotFound();
        $this->actingAs($legacy)
            ->post(route('branches.store'), ['name' => 'Cabang Liar'])
            ->assertForbidden();

        $this->assertDatabaseMissing('branches', ['name' => 'Cabang Liar']);
        $this->assertSame('Cabang Alpha', $branchA->refresh()->name);
        $this->assertTrue($branchA->refresh()->is_active);
    }

    public function test_standalone_owner_manages_standalone_branches(): void
    {
        // Tanpa satu pun tenant terdaftar: instalasi mandiri.
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::create(['name' => 'Cabang Mandiri']);

        $this->actingAs($owner)->get(route('branches.index'))->assertSee('Cabang Mandiri');
        $this->actingAs($owner)->get(route('branches.show', $branch))->assertOk();
        $this->actingAs($owner)->get(route('branches.edit', $branch))->assertOk();

        $this->actingAs($owner)
            ->put(route('branches.update', $branch), ['name' => 'Mandiri Baru'])
            ->assertRedirect(route('branches.index'));
        $this->assertSame('Mandiri Baru', $branch->refresh()->name);

        $this->actingAs($owner)
            ->post(route('branches.store'), ['name' => 'Mandiri Kedua'])
            ->assertRedirect(route('branches.index'));
        $this->assertNull(Branch::where('name', 'Mandiri Kedua')->firstOrFail()->tenant_id);

        $this->actingAs($owner)
            ->post(route('branches.toggle-active', $branch))
            ->assertRedirect();
        $this->assertFalse($branch->refresh()->is_active);
    }

    public function test_main_warehouse_switch_does_not_touch_other_tenant(): void
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');
        $branchA2 = Branch::create(['name' => 'Cabang Alpha 2', 'tenant_id' => $ownerA->tenant_id]);
        $branchA->update(['is_main_warehouse' => true]);
        $branchB->update(['is_main_warehouse' => true]);

        $this->actingAs($ownerA)
            ->put(route('branches.update', $branchA2), [
                'name' => 'Cabang Alpha 2', 'is_main_warehouse' => true,
            ])
            ->assertRedirect(route('branches.index'));

        // Satu partisi: cabang lama Alpha kehilangan tanda, sasaran
        // memperolehnya, gudang utama Beta TIDAK berubah sama sekali.
        $this->assertFalse($branchA->refresh()->is_main_warehouse);
        $this->assertTrue($branchA2->refresh()->is_main_warehouse);
        $this->assertTrue($branchB->refresh()->is_main_warehouse);
    }
}
