<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-02 — Isolasi master per tenant (keputusan OD-2: semua data
 * per tenant, tidak boleh menyeberang). Produk + induk produk,
 * pelanggan, resep, asuransi, dan supplier terisolasi antar-tenant
 * di web, service, dan API; di dalam satu tenant master tetap
 * berlaku lintas cabang (ADR-012 intra-tenant); instalasi
 * mandiri tidak berubah.
 */
class MasterTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
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
            'email_verified_at' => now(),            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function product(Tenant $tenant, string $name): Product
    {
        return Product::create([
            'type' => 'frame', 'name' => $name, 'brand' => 'Uji',
            'sku' => 'SKU-'.$name, 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $tenant->id,
        ]);
    }

    public function test_products_do_not_cross_tenants_on_web(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB, $adminB] = $this->tenantAdmin('b');
        $own = $this->product($tenantA, 'Frame Milik A');
        $foreign = $this->product($tenantB, 'Frame Milik B');

        $this->actingAs($adminA)->get('/katalog/frame')
            ->assertOk()->assertSee('Frame Milik A')->assertDontSee('Frame Milik B');
        $this->actingAs($adminA)->get('/produk/'.$foreign->id)->assertNotFound();
        $this->actingAs($adminA)->get('/produk/'.$foreign->id.'/ubah')->assertNotFound();
        $this->actingAs($adminA)->get('/produk/'.$own->id)->assertOk();

        $this->actingAs($adminB)->get('/katalog/frame')
            ->assertOk()->assertSee('Frame Milik B')->assertDontSee('Frame Milik A');
    }

    public function test_product_creation_stamps_tenant_and_parent_is_per_tenant(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB, $adminB] = $this->tenantAdmin('b');
        $service = app(ProductService::class);

        $parentA = $service->createParent($adminA, ['type' => 'frame', 'name' => 'Induk Sama', 'brand' => 'Uji']);
        $parentB = $service->createParent($adminB, ['type' => 'frame', 'name' => 'Induk Sama', 'brand' => 'Uji']);

        $this->assertSame($tenantA->id, $parentA->tenant_id);
        $this->assertSame($tenantB->id, $parentB->tenant_id);
        $this->assertNotSame($parentA->id, $parentB->id);
    }

    public function test_customers_and_prescriptions_do_not_cross_tenants(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');

        $this->actingAs($adminA);
        $customerA = Customer::create(['name' => 'Pelanggan A', 'phone' => '0811000001', 'branch_id' => $branchA->id]);
        $prescriptionA = Prescription::factory()->create([
            'customer_id' => $customerA->id, 'branch_id' => $branchA->id,
        ]);
        $this->assertSame($tenantA->id, $customerA->tenant_id);
        $this->assertSame($tenantA->id, $prescriptionA->tenant_id);

        $this->actingAs($adminB);
        $this->assertFalse(Customer::where('name', 'Pelanggan A')->exists());
        $this->assertFalse(Prescription::whereKey($prescriptionA->id)->exists());
        $this->actingAs($adminB)->get('/pelanggan/'.$customerA->id)->assertNotFound();
        $this->actingAs($adminB)->get('/resep/'.$prescriptionA->id)->assertNotFound();

        // Di dalam tenant yang sama tetap terlihat (lintas cabang).
        $this->actingAs($adminA);
        $this->assertTrue(Customer::where('name', 'Pelanggan A')->exists());
        $this->actingAs($adminA)->get('/pelanggan/'.$customerA->id)->assertOk();
    }

    public function test_suppliers_and_insurances_do_not_cross_tenants(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');

        $this->actingAs($adminA);
        Supplier::create(['name' => 'Supplier A']);
        Insurance::create(['name' => 'Asuransi A', 'claim_value' => 100000]);

        $this->actingAs($adminB);
        $this->assertFalse(Supplier::where('name', 'Supplier A')->exists());
        $this->assertFalse(Insurance::where('name', 'Asuransi A')->exists());

        $this->actingAs($this->platform);
        $this->assertTrue(Supplier::where('name', 'Supplier A')->exists());
        $this->assertTrue(Insurance::where('name', 'Asuransi A')->exists());
    }

    public function test_api_reads_are_tenant_fenced(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $this->product($tenantA, 'Frame API A');
        $this->product($tenantB, 'Frame API B');
        $this->actingAs($adminA);
        Customer::create(['name' => 'Pelanggan API A', 'phone' => '0811000002', 'branch_id' => $branchA->id]);
        $this->actingAs($adminB);
        Customer::create(['name' => 'Pelanggan API B', 'phone' => '0811000003', 'branch_id' => $branchB->id]);
        Sale::factory()->create(['branch_id' => $branchB->id]);

        $issued = app(ApiTokenService::class)->issue($adminA, 'Token A', ['products.read', 'customers.read', 'sales.read']);
        $headers = ['Authorization' => 'Bearer '.$issued['plain']];

        $products = $this->withHeaders($headers)->getJson('/api/v1/products')->assertOk()->json('data');
        $this->assertSame(['Frame API A'], array_column($products, 'name'));

        $customers = $this->withHeaders($headers)->getJson('/api/v1/customers')->assertOk()->json('data');
        $this->assertSame(['Pelanggan API A'], array_column($customers, 'name'));

        $sales = $this->withHeaders($headers)->getJson('/api/v1/sales')->assertOk();
        $this->assertSame(0, $sales->json('meta.total'));
    }

    public function test_standalone_install_is_unchanged(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $product = Product::factory()->create(['name' => 'Frame Mandiri']);
        $customer = Customer::factory()->create(['name' => 'Pelanggan Mandiri']);

        $this->actingAs($owner)->get('/katalog/frame')->assertOk()->assertSee('Frame Mandiri');
        $this->assertTrue(Customer::where('name', 'Pelanggan Mandiri')->exists());
        $this->assertNull($product->tenant_id);
        $this->assertNull($customer->tenant_id);
    }
}
