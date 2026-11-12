<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
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
 * D-02 GAP-2 — Pencarian global (/cari) lintas tenant. Endpoint
 * pencarian mengembalikan tujuh kelompok: pengguna, cabang, produk,
 * pelanggan, penjualan, PR, dan PO — dan test ini membuktikan
 * ketujuh kelompok itu tidak menyeberang antar-tenant: hasil untuk
 * aktor tenant B tidak boleh memuat satu pun penanda milik tenant
 * A, dan sebaliknya — sementara pencarian atas data sendiri tetap
 * bekerja (kontrol positif).
 */
class GlobalSearchTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    private Sale $saleA;

    private PurchaseOrder $poA;

    private PurchaseRequest $prA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
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

        [$this->saleA, $this->poA] = $this->seedTransactions($tenantA, $this->adminA, $branchA, 'A');
        $this->seedTransactions($tenantB, $this->adminB, $branchB, 'B');

        $this->prA = PurchaseRequest::create([
            'number' => 'PR-CARI-A', 'branch_id' => $branchA->id,
            'requester_id' => $this->adminA->id, 'status' => 'draft',
        ]);
        PurchaseRequest::create([
            'number' => 'PR-CARI-B', 'branch_id' => $branchB->id,
            'requester_id' => $this->adminB->id, 'status' => 'draft',
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

    public function test_search_users_and_transactions_do_not_cross_tenants(): void
    {
        // Kelompok pengguna: aktor hanya menemukan pengguna yang
        // berbagi cabang terkelola dengannya — pengguna tenant lain
        // tidak pernah muncul.
        $this->actingAs($this->adminB)->get('/cari?q=Admin Cari')
            ->assertOk()
            ->assertSee('Admin Cari b')
            ->assertDontSee('Admin Cari a');

        // Kelompok transaksi: nomor dokumen adalah urutan global
        // instalasi (unik per dokumen), tetapi halaman hasil
        // menggemakan teks kueri ke kotak pencarian sehingga asersi
        // atas nomor mentah tidak berarti. Penanda yang dipakai di
        // sini adalah pemilik dokumen pada baris hasil (nama
        // pelanggan/supplier) dan tautan ke halaman detailnya.
        $saleShow = '/penjualan/'.$this->saleA->id;
        $poShow = '/pembelian/po/'.$this->poA->id;

        $this->actingAs($this->adminB)->get('/cari?q='.urlencode($this->saleA->number))
            ->assertOk()
            ->assertDontSee('Pelanggan Alpha Unik')
            ->assertDontSee($saleShow);
        $this->actingAs($this->adminB)->get('/cari?q='.urlencode($this->poA->number))
            ->assertOk()
            ->assertDontSee('PT Cari A')
            ->assertDontSee($poShow);

        $this->actingAs($this->adminA)->get('/cari?q='.urlencode($this->saleA->number))
            ->assertOk()
            ->assertSee('Pelanggan Alpha Unik')
            ->assertSee($saleShow);
        $this->actingAs($this->adminA)->get('/cari?q='.urlencode($this->poA->number))
            ->assertOk()
            ->assertSee('PT Cari A')
            ->assertSee($poShow);
    }

    public function test_search_branches_and_purchase_requests_do_not_cross_tenants(): void
    {
        // Kelompok cabang: aktor hanya menemukan cabang terkelolanya
        // sendiri.
        $this->actingAs($this->adminB)->get('/cari?q=Cabang')
            ->assertOk()
            ->assertSee('Cabang b')
            ->assertDontSee('Cabang a');
        $this->actingAs($this->adminA)->get('/cari?q=Cabang')
            ->assertOk()
            ->assertSee('Cabang a')
            ->assertDontSee('Cabang b');

        // Kelompok PR: halaman hasil menggemakan teks kueri, jadi
        // penanda yang dipakai adalah tautan detail PR dan nama
        // cabang pemiliknya pada baris hasil.
        $prShow = '/pembelian/pr/'.$this->prA->id;
        $this->actingAs($this->adminB)->get('/cari?q=PR-CARI-A')
            ->assertOk()
            ->assertDontSee($prShow)
            ->assertDontSee('Cabang a');
        $this->actingAs($this->adminA)->get('/cari?q=PR-CARI-A')
            ->assertOk()
            ->assertSee($prShow);
    }

    /** @return array{Sale, PurchaseOrder} */
    private function seedTransactions(Tenant $tenant, User $admin, Branch $branch, string $tag): array
    {
        $supplier = Supplier::create(['name' => 'PT Cari '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $product = Product::create([
            'type' => 'frame', 'name' => 'Frame Cari '.$tag, 'brand' => 'Uji',
            'sku' => 'SKU-CARITRX-'.$tag, 'cost_price' => 100000, 'sale_price' => 250000,
            'is_active' => true, 'tenant_id' => $tenant->id,
        ]);
        $customer = Customer::where('tenant_id', $tenant->id)->firstOrFail();

        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($admin, $supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($admin, $po);
        app(ReceiptService::class)->store($admin, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '100000'],
        ]);

        $sale = app(SalesService::class)->createSale($admin, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-02',
            'tax_mode' => 'exclusive', 'branch_id' => $branch->id,
        ], [['product_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);

        return [$sale, $po];
    }
}
