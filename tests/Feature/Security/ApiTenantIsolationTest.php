<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D-02 GAP-3 — API v1 baca lintas tenant. Token API diterbitkan
 * untuk pengguna milik satu tenant; seluruh endpoint baca
 * (products, customers, sales, stock) hanya boleh mengembalikan
 * data tenant pemilik token, tidak peduli penanda unik apa pun
 * yang dimiliki tenant lain.
 */
class ApiTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private string $tokenA;

    private string $tokenB;

    private Sale $saleA;

    private Sale $saleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');

        $this->saleA = $this->seedTenantSales($tenantA, $adminA, $branchA, 'A');
        $this->saleB = $this->seedTenantSales($tenantB, $adminB, $branchB, 'B');

        $scopes = ['products.read', 'customers.read', 'sales.read', 'stock.read'];
        $this->tokenA = app(ApiTokenService::class)->issue($adminA, 'Token Uji A', $scopes)['plain'];
        $this->tokenB = app(ApiTokenService::class)->issue($adminB, 'Token Uji B', $scopes)['plain'];
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
            'name' => 'Admin API '.$tag, 'username' => 'admin-api-'.$tag,
            'email' => 'admin-api-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function seedTenantSales(Tenant $tenant, User $admin, Branch $branch, string $tag): Sale
    {
        $supplier = Supplier::create(['name' => 'PT API '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $product = FrameDetail::factory()->create(['product_id' => Product::factory()->forType('frame')->create(['name' => 'Frame API '.$tag, 'brand' => 'Uji', 'tenant_id' => $tenant->id])->id, 'sku' => 'SKU-API-'.$tag, 'cost_price' => 100000, 'sale_price' => 250000, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $customer = Customer::create([
            'name' => 'Pelanggan API '.$tag, 'phone' => '0812'.$tag, 'branch_id' => $branch->id,
            'tenant_id' => $tenant->id,
        ]);

        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($admin, $supplier->id, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $product->id, 'qty' => 5, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($admin, $po);
        app(ReceiptService::class)->store($admin, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '100000'],
        ]);

        return app(SalesService::class)->createSale($admin, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-02',
            'tax_mode' => 'exclusive', 'branch_id' => $branch->id,
        ], [['detail_type' => 'frame', 'detail_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);
    }

    public function test_products_and_customers_endpoints_are_tenant_fenced(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertSee('SKU-API-A', false)
            ->assertDontSee('SKU-API-B', false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->getJson('/api/v1/customers')
            ->assertOk()
            ->assertSee('Pelanggan API A', false)
            ->assertDontSee('Pelanggan API B', false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertSee('SKU-API-B', false)
            ->assertDontSee('SKU-API-A', false);
    }

    public function test_sales_and_stock_endpoints_are_tenant_fenced(): void
    {
        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->getJson('/api/v1/sales')
            ->assertOk()
            ->assertSee($this->saleA->number, false)
            ->assertDontSee($this->saleB->number, false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenA)
            ->getJson('/api/v1/stock')
            ->assertOk()
            ->assertSee('Frame API A', false)
            ->assertDontSee('Frame API B', false);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenB)
            ->getJson('/api/v1/sales')
            ->assertOk()
            ->assertSee($this->saleB->number, false)
            ->assertDontSee($this->saleA->number, false);
    }
}
