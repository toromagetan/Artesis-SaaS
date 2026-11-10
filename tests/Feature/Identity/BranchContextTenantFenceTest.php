<?php

namespace Tests\Feature\Identity;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 — pagar tenant pada BranchContext: konteks cabang TIDAK PERNAH
 * memuat cabang tenant lain. Owner bertenant berwilayah seluruh
 * cabang tenantnya sendiri (bukan akses global); akun tanpa tenant
 * pada instalasi yang sudah bertenant gagal-tutup; instalasi mandiri
 * tanpa tenant mempertahankan perilaku historis Blueprint B.1.
 */
class BranchContextTenantFenceTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, Branch, User} tenant + cabang + Owner-nya */
    private function tenantOwner(string $key): array
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
        $owner = User::create([
            'name' => 'Owner '.$key, 'username' => 'owner-'.strtolower($key),
            'email' => 'owner-'.strtolower($key).'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        return [$tenant, $branch, $owner];
    }

    public function test_tenant_owner_context_contains_only_own_tenant_branches(): void
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');
        $branchA2 = Branch::create(['name' => 'Cabang Alpha 2', 'tenant_id' => $ownerA->tenant_id]);

        $context = BranchContext::for($ownerA);
        $this->assertFalse($context->hasGlobalAccess());
        $this->assertTrue($context->isTenantWide());
        $this->assertEqualsCanonicalizing(
            [$branchA->id, $branchA2->id],
            $context->managedBranchIds(),
        );
        $this->assertTrue($context->allowsBranch($branchA->id));
        $this->assertFalse($context->allowsBranch($branchB->id));
    }

    public function test_branch_scope_hides_other_tenant_sales_from_tenant_owner(): void
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');
        Sale::factory()->create(['branch_id' => $branchA->id]);
        Sale::factory()->create(['branch_id' => $branchB->id]);

        $this->actingAs($ownerA);
        $visible = Sale::query()->pluck('branch_id')->all();
        $this->assertSame([$branchA->id], $visible);
    }

    public function test_tenant_owner_dashboard_finance_shows_only_own_tenant(): void
    {
        // Bekukan hari ke tanggal 20 agar jurnal tanggal 05 selalu
        // masuk jendela 30 hari dashboard (anti bom waktu tanggal).
        $this->travelTo(now()->setDay(20)->setTime(12, 0));
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB, $ownerB] = $this->tenantOwner('Beta');

        $posting = app(PostingService::class);
        $month = now()->format('Y-m');
        $posting->post('sale', null, $month.'-05', [
            ['account' => '1-1100', 'debit' => '500000', 'branch_id' => $branchA->id],
            ['account' => '4-1100', 'credit' => '500000', 'branch_id' => $branchA->id],
        ], 'penjualan A', $ownerA);
        $posting->post('sale', null, $month.'-05', [
            ['account' => '1-1100', 'debit' => '900000', 'branch_id' => $branchB->id],
            ['account' => '4-1100', 'credit' => '900000', 'branch_id' => $branchB->id],
        ], 'penjualan B', $ownerB);

        $response = $this->actingAs($ownerA)->get(route('dashboard'));
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertArrayHasKey('finance', $cards);
        // Hanya angka tenant Alpha: laba 500rb dan kas 500rb —
        // 900rb milik Beta tidak boleh bocor ke keduanya.
        $this->assertSame('500000.00', $cards['finance']['net']->toDecimalString());
        $this->assertSame('500000.00', $cards['finance']['cash']->toDecimalString());
        $this->assertCount(1, $cards['finance']['by_branch']);
    }

    public function test_unassigned_owner_has_no_branch_access_when_tenants_exist(): void
    {
        $this->tenantOwner('Alpha');
        // Owner bawaan seeder: tenant_id null pada instalasi bertenant.
        $legacy = User::where('username', 'owner')->firstOrFail();
        $this->assertNull($legacy->tenant_id);

        $context = BranchContext::for($legacy);
        $this->assertFalse($context->hasGlobalAccess());
        $this->assertFalse($context->isTenantWide());
        $this->assertSame([], $context->managedBranchIds());
        $this->assertFalse($context->allowsBranch(1));
    }

    public function test_standalone_owner_keeps_global_access_without_tenants(): void
    {
        $branch = Branch::create(['name' => 'Cabang Mandiri']);
        $owner = User::where('username', 'owner')->firstOrFail();

        $context = BranchContext::for($owner);
        $this->assertTrue($context->hasGlobalAccess());
        $this->assertTrue($context->isTenantWide());
        $this->assertContains($branch->id, $context->managedBranchIds());
        $this->assertTrue($context->allowsBranch($branch->id));
    }

    public function test_non_owner_branches_are_intersected_with_own_tenant(): void
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB] = $this->tenantOwner('Beta');
        $staff = User::create([
            'name' => 'Staf Alpha', 'username' => 'staf-alpha',
            'email' => 'staf-alpha@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $ownerA->tenant_id,
        ]);
        $staff->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        // Lampiran pivot kotor: cabang sendiri + cabang tenant lain.
        $staff->branches()->attach([$branchA->id, $branchB->id]);

        $context = BranchContext::for($staff);
        $this->assertSame([$branchA->id], $context->managedBranchIds());
        $this->assertFalse($context->allowsBranch($branchB->id));
    }
}
