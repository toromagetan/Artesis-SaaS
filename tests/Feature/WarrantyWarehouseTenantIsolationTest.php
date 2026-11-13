<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InventoryService;
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
use Illuminate\Support\Facades\DB;
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
            'email_verified_at' => now(),            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
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
            'email_verified_at' => now(),            'name' => $role.' '.$tag, 'username' => strtolower($role).'-'.$tag,
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

    /**
     * Kasus + stok rusak pada partisi standalone (tenant NULL).
     * WAJIB dipanggil sebelum tenant mana pun dibuat: mode
     * standalone hanya ada selagi instalasi belum melayani tenant.
     *
     * @return array{User, Branch, WarrantyCase, BrokenStock}
     */
    private function standaloneCase(): array
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $warehouse = Branch::factory()->create(['name' => 'Gudang Standalone', 'is_main_warehouse' => true]);
        $product = Product::factory()->ofType('frame')->create([
            'sku' => 'WR-SA', 'name' => 'Frame Standalone', 'cost_price' => '40000', 'sale_price' => '100000',
        ]);
        $supplier = Supplier::create(['name' => 'Supplier Standalone', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        foreach ([$pusat, $warehouse] as $branch) {
            $po = $orders->create($owner, $supplier->id, $branch->id, [
                ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => '40000'],
            ]);
            $orders->markSent($owner, $po);
            app(ReceiptService::class)->store($owner, $po, [
                ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '40000'],
            ]);
        }
        $customer = Customer::factory()->create(['branch_id' => $pusat->id]);
        $sale = app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'exclusive', 'branch_id' => $pusat->id,
        ], [['product_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
        $case = $this->makeCase($owner, $sale);
        $this->warranty->sendOldItem($owner, $case);
        $broken = $this->warranty->receiveOldItem($owner, $case);

        return [$owner, $warehouse, $case, $broken];
    }

    public function test_standalone_owner_uses_standalone_partition(): void
    {
        [$owner, $warehouse, $case] = $this->standaloneCase();

        $this->assertSame($warehouse->id, $this->warranty->referenceWarehouseFor($owner)?->id);
        $response = $this->actingAs($owner)->get(route('warranties.index'));
        $response->assertOk();
        $response->assertSee($case->number);
        $this->assertSame($warehouse->id, $response->viewData('warehouse')?->id);
    }

    public function test_platform_and_unassigned_get_no_standalone_reference(): void
    {
        [, $warehouse, $case] = $this->standaloneCase();
        $this->tenantAdmin('a'); // instalasi kini melayani tenant

        // Platform: resolver tidak memberinya gudang acuan mana pun,
        // dan gerbang izin halaman garansi menolak aktor platform
        // (izin bisnis bukan grup platform).
        $this->assertNull($this->warranty->referenceWarehouseFor($this->platform));
        $this->actingAs($this->platform)->get(route('warranties.index'))->assertForbidden();

        // Unassigned: akun tanpa tenant_id pada instalasi bertenant.
        // Partisi NULL bukan miliknya — kasus dan antrean stok rusak
        // standalone tidak boleh terlihat.
        $unassigned = User::create([
            'email_verified_at' => now(),            'name' => 'Tanpa Tenant', 'username' => 'unassigned',
            'email' => 'unassigned@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $this->assertNull($this->warranty->referenceWarehouseFor($unassigned));

        $response = $this->actingAs($unassigned)->get(route('warranties.index'));
        $response->assertOk();
        $response->assertDontSee($case->number);
        $this->assertNull($response->viewData('warehouse'));
        $queueBranches = collect($response->viewData('brokenPending'))->pluck('branch_id');
        $this->assertFalse($queueBranches->contains($warehouse->id));
    }

    public function test_broken_stock_queue_is_partitioned(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $caseA = $this->makeCase($adminA, $saleA);
        $this->warranty->sendOldItem($adminA, $caseA);
        $brokenA = $this->warranty->receiveOldItem($adminA, $caseA);
        [, , $saleB] = $this->stockedSale($adminB, $tenantB, $branchB, $warehouseB, 'B');
        $caseB = $this->makeCase($adminB, $saleB);
        $this->warranty->sendOldItem($adminB, $caseB);
        $brokenB = $this->warranty->receiveOldItem($adminB, $caseB);

        $kasirA = $this->userWithRole($tenantA, 'Kasir', 'a', [$branchA->id]);
        $pendingA = collect($this->actingAs($kasirA)->get(route('warranties.index'))->assertOk()->viewData('brokenPending'))->pluck('id');
        $this->assertTrue($pendingA->contains($brokenA->id));
        $this->assertFalse($pendingA->contains($brokenB->id));

        $kasirB = $this->userWithRole($tenantB, 'Kasir', 'b', [$branchB->id]);
        $pendingB = collect($this->actingAs($kasirB)->get(route('warranties.index'))->assertOk()->viewData('brokenPending'))->pluck('id');
        $this->assertTrue($pendingB->contains($brokenB->id));
        $this->assertFalse($pendingB->contains($brokenA->id));
    }

    public function test_show_page_respects_partition(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $caseA = $this->makeCase($adminA, $saleA);
        [, , $saleB] = $this->stockedSale($adminB, $tenantB, $branchB, $warehouseB, 'B');
        $caseB = $this->makeCase($adminB, $saleB);

        $kasirA = $this->userWithRole($tenantA, 'Kasir', 'a', [$branchA->id]);
        $this->actingAs($kasirA)->get(route('warranties.show', $caseB))->assertNotFound();
        $this->actingAs($kasirA)->get(route('warranties.show', $caseA))->assertOk();
        $this->actingAs($adminB)->get(route('warranties.show', $caseA))->assertNotFound();
    }

    public function test_ship_replacement_uses_case_partition_warehouse(): void
    {
        [$tenantB, $adminB, $branchB, $warehouseB] = $this->tenantAdmin('b');
        [$tenantA, $adminA, $branchA, $warehouseA] = $this->tenantAdmin('a');
        [$productA, , $saleA] = $this->stockedSale($adminA, $tenantA, $branchA, $warehouseA, 'A');
        $case = $this->makeCase($adminA, $saleA);
        $this->warranty->sendOldItem($adminA, $case);
        $broken = $this->warranty->receiveOldItem($adminA, $case);
        $this->warranty->inspectBroken($adminA, $broken, 'broken', 'Retak', null);
        $this->warranty->prepareReplacement($adminA, $case->fresh(), 'stock', $productA, '0');

        // Aktor tenant asing ditolak mengirim pengganti kasus A.
        try {
            $this->warranty->shipReplacement($adminB, $case->fresh());
            $this->fail('Admin tenant B tidak boleh mengirim pengganti kasus tenant A.');
        } catch (AuthorizationException) {
            $this->assertSame('replacement_preparing', $case->fresh()->status);
        }

        $this->warranty->shipReplacement($adminA, $case->fresh());
        $this->assertSame('replacement_shipped', $case->fresh()->status);

        // Gerakan keluar tercatat di gudang A, stoknya berkurang.
        $movement = StockMovement::withoutGlobalScopes()
            ->where('branch_id', $warehouseA->id)->where('reason', 'warranty_out')
            ->where('reference_type', 'warranty_case')->where('reference_id', $case->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertSame(-1, (int) $movement->qty);
        $this->assertSame(4, app(InventoryService::class)->currentStock($productA->id, $warehouseA->id));

        // Jurnal beban garansi memakai cabang gudang A di semua baris.
        $journal = Journal::where('source_type', 'warranty_shipment')->where('source_id', $case->id)->first();
        $this->assertNotNull($journal);
        $lineBranches = $journal->lines()->pluck('branch_id')->unique()->map(fn ($id) => (int) $id)->values()->all();
        $this->assertSame([$warehouseA->id], $lineBranches);
    }

    public function test_dangling_tenant_account_gets_no_standalone_reference(): void
    {
        [, $warehouse, $case] = $this->standaloneCase();
        $this->tenantAdmin('a'); // instalasi kini melayani tenant

        // Akun tenant MENGGANTUNG: tenant_id menunjuk baris tenant
        // yang tidak ada. FK database melarang pembuatan biasa, jadi
        // fixture dibuat dengan pemeriksaan FK dimatikan sesaat —
        // persis keadaan rusak yang harus gagal tertutup.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            $dangling = User::create([
                'email_verified_at' => now(),                'name' => 'Tenant Menggantung', 'username' => 'dangling',
                'email' => 'dangling@uji.id', 'password' => bcrypt('rahasia-uji'),
                'is_active' => true, 'is_platform' => false, 'tenant_id' => 999999,
            ]);
            $dangling->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $context = TenantContext::for($dangling);
        $this->assertTrue($context->isTenant());
        $this->assertFalse($context->isValid());
        $this->assertNull($context->tenantId());
        $this->assertNull($this->warranty->referenceWarehouseFor($dangling));

        $response = $this->actingAs($dangling)->get(route('warranties.index'));
        $response->assertOk();
        $response->assertDontSee($case->number);
        $this->assertNull($response->viewData('warehouse'));
        $queueBranches = collect($response->viewData('brokenPending'))->pluck('branch_id');
        $this->assertFalse($queueBranches->contains($warehouse->id));
        $this->actingAs($dangling)->get(route('warranties.show', $case))->assertNotFound();
    }
}
