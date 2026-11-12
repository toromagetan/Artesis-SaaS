<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
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
 * D-02 GAP-2 — Pencarian global (/cari) lintas tenant. Endpoint
 * pencarian menggabungkan produk, pelanggan, pengguna, dan transaksi
 * dalam satu layar; hasil untuk aktor tenant B tidak boleh memuat
 * satu pun penanda milik tenant A, dan sebaliknya — sementara
 * pencarian atas data sendiri tetap bekerja (kontrol positif).
 */
class GlobalSearchTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $this->adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $this->adminB, $branchB] = $this->tenantAdmin('b');

        Product::create([
            'type' => 'lens', 'name' => 'Lensa Progresif Alpha', 'brand' => 'Uji',
            'sku' => 'SKU-CARI-A', 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $tenantA->id,
        ]);
        Product::create([
            'type' => 'lens', 'name' => 'Lensa Progresif Beta', 'brand' => 'Uji',
            'sku' => 'SKU-CARI-B', 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $tenantB->id,
        ]);
        Customer::create([
            'name' => 'Pelanggan Alpha Unik', 'phone' => '0812111', 'branch_id' => $branchA->id,
            'tenant_id' => $tenantA->id,
        ]);
        Customer::create([
            'name' => 'Pelanggan Beta Unik', 'phone' => '0812222', 'branch_id' => $branchB->id,
            'tenant_id' => $tenantB->id,
        ]);
    }

    /** @return array{Tenant, User, Branch} */
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
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin Cari '.$tag, 'username' => 'admin-cari-'.$tag,
            'email' => 'admin-cari-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    public function test_search_never_surfaces_other_tenant_products_or_customers(): void
    {
        $this->actingAs($this->adminB)->get('/cari?q=Alpha')
            ->assertOk()
            ->assertDontSee('Lensa Progresif Alpha')
            ->assertDontSee('SKU-CARI-A')
            ->assertDontSee('Pelanggan Alpha Unik');

        $this->actingAs($this->adminA)->get('/cari?q=Beta')
            ->assertOk()
            ->assertDontSee('Lensa Progresif Beta')
            ->assertDontSee('SKU-CARI-B')
            ->assertDontSee('Pelanggan Beta Unik');
    }

    public function test_search_finds_own_tenant_data_control(): void
    {
        $this->actingAs($this->adminA)->get('/cari?q=Alpha')
            ->assertOk()
            ->assertSee('Lensa Progresif Alpha')
            ->assertSee('Pelanggan Alpha Unik');

        $this->actingAs($this->adminB)->get('/cari?q=Beta')
            ->assertOk()
            ->assertSee('Lensa Progresif Beta')
            ->assertSee('Pelanggan Beta Unik');
    }
}
