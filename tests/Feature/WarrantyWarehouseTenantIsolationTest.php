<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\BrokenStock;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\WarrantyCase;
use App\Modules\Sales\Services\SalesService;
use App\Modules\Sales\Services\WarrantyService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Koreksi B-02 — gudang utama garansi adalah konsep PER PARTISI
 * TENANT, bukan pilihan global instalasi. Dua tenant SaaS nyata;
 * cabang tenant B sengaja dibuat LEBIH DULU agar gudang B ber-id
 * lebih kecil — pilihan global lama akan selalu jatuh ke B.
 *
 * Dibuktikan: resolusi gudang mengikuti pemilik kasus; aktor
 * tenant lain tidak dapat memproses tahap gudang kasus asing;
 * pembuatan PO pengganti menuntut izin stock.transfer + wilayah
 * gudang partisi sendiri + supplier separtisi; daftar kasus
 * hanya menggabungkan gudang partisi aktor sendiri.
 */
class WarrantyWarehouseTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private WarrantyService $warranty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        // Tulis global tanpa aktor wajib konteks sistem (aturan PR #33).
        TenantContext::asSystem(fn () => Setting::set('tax.rate_default', '0'));
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->warranty = app(WarrantyService::class);
    }

    /** @return array{Tenant, User, Branch, Branch} tenant, admin, cabang jual, gudang */
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
        $warehouse = Branch::create([
            'name' => 'Gudang '.$tag, 'tenant_id' => $tenant->id, 'is_main_warehouse' => true,
        ]);
        $admin = User::create([
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach([$branch->id, $warehouse->id]);

        return [$tenant, $admin, $branch, $warehouse];
    }

    private function userWithRole(Tenant $tenant, string $role, string $tag, array $branchIds): User
    {
        $user = User::create([
            'name' => $role.' '.$tag, 'username' => strtolower($role).'-'.$tag,
            'email' => strtolower($role).'-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        $user->branches()->attach($branchIds);

        return $user;
    }

    /** Produk berstok di cabang jual + gudang, lalu satu penjualan qty 1 di $saleBranch. */
    private function stockedSale(User $admin, Tenant $tenant, Branch $saleBranch, Branch $warehouse, string $tag): array
    {
        $product = Product::factory()->ofType('frame')->create([
            'sku' => 'WR-'.$tag, 'name' => 'Frame '.$tag,
            'cost_price' => '40000', 'sale_price' => '100000',
            'tenant_id' => $tenant->id,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $orders = app(PurchaseOrderService::class);
        foreach ([$saleBranch, $warehouse] as $branch) {
            $po = $orders->create($admin, $supplier->id, $branch->id, [
                ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => '40000'],
            ]);
            $orders->markSent($admin, $po);
            app(ReceiptService::class)->store($admin, $po, [
                ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '40000'],
            ]);
        }
        $customer = Customer::factory()->create(['branch_id' => $saleBranch->id, 'tenant_id' => $tenant->id]);
        $sale = app(SalesService::class)->createSale($admin, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'exclusive', 'branch_id' => $saleBranch->id,
        ], [['product_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);

        return [$product, $supplier, $sale];
    }

    private function makeCase(User $admin, Sale $sale): WarrantyCase
    {
        return $this->warranty->createCase($admin, $sale, $sale->items->first(), 1, 'Layar retak', null);
    }

    /** Kasus sampai tahap old_item_received dengan sumber pengganti purchase_order. */
    private function caseReadyForPo(User $admin, Sale $sale, Product $product): WarrantyCase
    {
        $case = $this->makeCase($admin, $sale);
        $this->warranty->sendOldItem($admin, $case);
        $broken = $this->warranty->receiveOldItem($admin, $case);
        $this->warranty->inspectBroken($admin, $broken, 'broken', 'Retak', null);
        $this->warranty->prepareReplacement($admin, $case->fresh(), 'purchase_order', $product, '0');

        return $case->fresh();
    }

    public function test_warehouse_resolution_follows_case_tenant_partition(): void
    {
        // B dibuat lebih dulu: gudang B ber-id lebih kecil.
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        $this->assertLessThan($warehouseA->id, $warehouseB->id);

        $this->assertSame($warehouseA->id, $this->warranty->warehouseBranchFor($tenantA->id)->id);
        $this->assertSame($warehouseB->id, $this->warranty->warehouseBranchFor($tenantB->id)->id);

        [, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $caseA = $this->makeCase($adminA, $saleA);
        $this->warranty->sendOldItem($adminA, $caseA);
        $brokenA = $this->warranty->receiveOldItem($adminA, $caseA);
        $this->assertSame($warehouseA->id, $brokenA->branch_id, 'Stok rusak kasus A harus masuk gudang A, bukan gudang global.');

        [, , $saleB] = $this->stockedSale($adminB, $tenantB, $branchB, $warehouseB, 'B');
        $caseB = $this->makeCase($adminB, $saleB);
        $this->warranty->sendOldItem($adminB, $caseB);
        $brokenB = $this->warranty->receiveOldItem($adminB, $caseB);
        $this->assertSame($warehouseB->id, $brokenB->branch_id);
    }

    public function test_foreign_tenant_cannot_process_warehouse_steps(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $caseA = $this->makeCase($adminA, $saleA);
        $this->warranty->sendOldItem($adminA, $caseA);

        try {
            $this->warranty->receiveOldItem($adminB, $caseA);
            $this->fail('Aktor tenant B tidak boleh menerima barang lama kasus tenant A.');
        } catch (AuthorizationException) {
            $this->assertSame(0, BrokenStock::count());
        }
    }

    public function test_replacement_po_requires_stock_transfer_permission(): void
    {
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [$productA, $supplierA, $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $case = $this->caseReadyForPo($adminA, $saleA, $productA);
        // Kasir: punya sales.view/create/pay, TIDAK punya stock.transfer.
        $kasir = $this->userWithRole($tenantA, 'Kasir', 'a', [$branchA->id, $warehouseA->id]);

        try {
            $this->warranty->createReplacementPo($kasir, $case, $supplierA);
            $this->fail('Kasir tanpa stock.transfer tidak boleh membuat PO pengganti.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertNull($case->fresh()->replacement->purchase_order_id);
        }
    }

    public function test_replacement_po_cross_tenant_and_supplier_partition(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [$productA, $supplierA, $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        [, $supplierB] = $this->stockedSale($adminB, $tenantB, $branchB, $warehouseB, 'B');
        $case = $this->caseReadyForPo($adminA, $saleA, $productA);

        // Admin tenant B (punya stock.transfer di wilayahnya) tetap
        // ditolak: gudang kasus ini bukan wilayahnya.
        try {
            $this->warranty->createReplacementPo($adminB, $case, $supplierB);
            $this->fail('Admin tenant B tidak boleh membuat PO untuk kasus tenant A.');
        } catch (AuthorizationException) {
            $this->assertNull($case->fresh()->replacement->purchase_order_id);
        }

        // Supplier tenant lain ditolak untuk kasus A.
        try {
            $this->warranty->createReplacementPo($adminA, $case, $supplierB);
            $this->fail('Supplier tenant B tidak boleh dipakai untuk kasus tenant A.');
        } catch (ValidationException) {
            $this->assertNull($case->fresh()->replacement->purchase_order_id);
        }

        // Jalur sah: PO tercatat di gudang A dengan supplier A.
        $this->warranty->createReplacementPo($adminA, $case, $supplierA);
        $poId = $case->fresh()->replacement->purchase_order_id;
        $this->assertNotNull($poId);
        $po = PurchaseOrder::withoutGlobalScopes()->findOrFail($poId);
        $this->assertSame($warehouseA->id, (int) $po->branch_id);
        $this->assertSame($supplierA->id, (int) $po->supplier_id);
    }

    public function test_index_visibility_uses_actor_partition(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');

        // Kasus B dijual DI cabang gudang B — pola yang pada kode
        // lama bocor ke daftar semua tenant lewat gabungan gudang
        // global. Kasus A dijual di cabang biasa A.
        [, , $saleB] = $this->stockedSale($adminB, $tenantB, $warehouseB, $warehouseB, 'B');
        $caseB = $this->makeCase($adminB, $saleB);
        [, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $caseA = $this->makeCase($adminA, $saleA);

        $kasirA = $this->userWithRole($tenantA, 'Kasir', 'a', [$branchA->id]);
        $response = $this->actingAs($kasirA)->get(route('warranties.index'));
        $response->assertOk();
        $response->assertSee($caseA->number);
        $response->assertDontSee($caseB->number);
    }
}
