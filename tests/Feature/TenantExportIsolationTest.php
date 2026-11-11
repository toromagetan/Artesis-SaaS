<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Platform\Services\TenantExportService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use ZipArchive;

/**
 * B-03 — Ekspor data per tenant. Kriteria roadmap: berkas ekspor
 * tenant A tidak memuat SATU BARIS PUN milik tenant B, termasuk
 * baris rantai anak (item penjualan, baris jurnal, item
 * penerimaan, item transfer). Kolom kredensial dibuang; tabel
 * kredensial/operasional/global tidak pernah disertakan; registry
 * klasifikasi wajib lengkap terhadap skema nyata. Aktor: hanya
 * Owner tenant sendiri atau platform atas nama tenant itu.
 */
class TenantExportIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private TenantExportService $exports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        TenantContext::asSystem(fn () => Setting::set('tax.rate_default', '0'));
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->exports = app(TenantExportService::class);
    }

    /** @return array{Tenant, User, Branch, Branch} tenant, owner, cabang jual, gudang */
    private function tenantOwner(string $tag): array
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
        $warehouse = Branch::create(['name' => 'Gudang '.$tag, 'tenant_id' => $tenant->id, 'is_main_warehouse' => true]);
        $owner = User::create([
            'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach([$branch->id, $warehouse->id]);

        return [$tenant, $owner, $branch, $warehouse];
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

    /** Rantai data nyata: PO + penerimaan, penjualan (+item, pembayaran, jurnal), transfer antar-cabang. */
    private function seedBusinessData(User $owner, Tenant $tenant, Branch $branch, Branch $warehouse, string $tag): array
    {
        $product = Product::factory()->ofType('frame')->create([
            'sku' => 'EX-'.$tag, 'name' => 'Frame '.$tag,
            'cost_price' => '40000', 'sale_price' => '100000', 'tenant_id' => $tenant->id,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($owner, $supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => 5, 'unit_cost' => '40000'],
        ]);
        $orders->markSent($owner, $po);
        app(ReceiptService::class)->store($owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '40000'],
        ]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id, 'tenant_id' => $tenant->id]);
        $sale = app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'exclusive', 'branch_id' => $branch->id,
        ], [['product_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
        app(TransferService::class)->sendFromBranchStock($owner, $branch->id, $warehouse->id, [
            ['product_id' => $product->id, 'qty' => 1],
        ]);

        return [$product, $sale];
    }

    public function test_registry_classifies_every_live_table(): void
    {
        $database = (string) DB::connection()->getDatabaseName();
        $live = [];
        foreach (Schema::getTables() as $meta) {
            if (($meta['schema'] ?? $database) === $database) {
                $live[] = (string) $meta['name'];
            }
        }
        $classified = TenantExportService::classifiedTables();
        $this->assertSame($classified, array_unique($classified), 'Registry tidak boleh ganda.');
        $this->assertSame([], array_values(array_diff($live, $classified)), 'Setiap tabel nyata wajib terklasifikasi di registry ekspor.');
        $this->assertSame([], array_values(array_diff($classified, $live)), 'Registry tidak boleh memuat tabel yang tidak ada.');
    }

    public function test_export_contains_only_own_tenant_rows_including_children(): void
    {
        [$tenantB, $ownerB, $branchB, $warehouseB] = $this->tenantOwner('b');
        [$tenantA, $ownerA, $branchA, $warehouseA] = $this->tenantOwner('a');
        [, $saleA] = $this->seedBusinessData($ownerA, $tenantA, $branchA, $warehouseA, 'A');
        [, $saleB] = $this->seedBusinessData($ownerB, $tenantB, $branchB, $warehouseB, 'B');

        $payload = $this->exports->buildPayload($tenantA, $ownerA);
        $tables = $payload['tables'];
        $this->assertSame('tenant_export', $payload['kind']);
        $this->assertSame($tenantA->id, $payload['tenant_id']);

        // Baris identitas: tepat satu baris tenant A.
        $this->assertCount(1, $tables['tenants']);
        $this->assertSame($tenantA->id, (int) $tables['tenants'][0]['id']);

        // Tabel bertenant_id: semuanya milik A.
        foreach (['products', 'customers', 'suppliers', 'journals', 'users', 'settings', 'roles'] as $table) {
            foreach ($tables[$table] as $row) {
                $this->assertSame($tenantA->id, (int) $row['tenant_id'], "Baris {$table} bukan milik tenant A.");
            }
        }
        // Tabel bercabang: semuanya cabang A.
        $branchIdsA = [$branchA->id, $warehouseA->id];
        foreach (['sales', 'stock_movements', 'journal_lines', 'audit_logs', 'purchase_orders', 'warranty_cases'] as $table) {
            foreach ($tables[$table] as $row) {
                $this->assertContains((int) $row['branch_id'], $branchIdsA, "Baris {$table} di luar cabang tenant A.");
            }
        }
        // Rantai anak penjualan: item & pembayaran milik penjualan A, B absen.
        $saleItemIdsA = collect($tables['sale_items'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->assertContains((int) $saleA->items->first()->id, $saleItemIdsA);
        $this->assertNotContains((int) $saleB->items->first()->id, $saleItemIdsA);
        foreach ($tables['sale_items'] as $row) {
            $this->assertSame($saleA->id, (int) $row['sale_id']);
        }
        foreach ($tables['sale_payments'] as $row) {
            $this->assertSame($saleA->id, (int) $row['sale_id']);
        }
        // Rantai anak pembelian & transfer terisi dan terpagar.
        $this->assertNotEmpty($tables['purchase_order_items']);
        $this->assertNotEmpty($tables['receipt_items']);
        $this->assertNotEmpty($tables['stock_transfer_items']);
        foreach ($tables['stock_transfers'] as $row) {
            $this->assertContains((int) $row['from_branch_id'], $branchIdsA);
        }

        // Kredensial dibuang dari baris pengguna; tabel terlarang absen.
        $this->assertNotEmpty($tables['users']);
        foreach ($tables['users'] as $row) {
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('remember_token', $row);
        }
        foreach (['api_tokens', 'member_portal_tokens', 'password_reset_tokens', 'sessions', 'plans', 'plan_features', 'permissions', 'product_categories', 'document_counters', 'ai_settings', 'wa_gateways', 'jobs', 'cache'] as $excluded) {
            $this->assertArrayNotHasKey($excluded, $tables, "Tabel {$excluded} tidak boleh masuk ekspor tenant.");
        }
    }

    public function test_created_archive_is_verified_and_isolated_on_disk(): void
    {
        [$tenantA, $ownerA, $branchA, $warehouseA] = $this->tenantOwner('a');
        $this->seedBusinessData($ownerA, $tenantA, $branchA, $warehouseA, 'A');

        $name = $this->exports->create($ownerA, $tenantA);
        $this->assertTrue($this->exports->verify($tenantA, $name));
        $path = $this->exports->path($tenantA, $name);
        $this->assertNotNull($path);
        $this->assertStringContainsString('tenant-exports/'.$tenantA->id, $path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $data = json_decode((string) $zip->getFromName('backup.json'), true);
        $zip->close();
        $this->assertSame($tenantA->id, (int) $data['tenant_id']);
        $this->assertNotEmpty($data['tables']['sales']);
    }

    public function test_http_actor_matrix(): void
    {
        [$tenantB, $ownerB, $branchB, $warehouseB] = $this->tenantOwner('b');
        [$tenantA, $ownerA, $branchA, $warehouseA] = $this->tenantOwner('a');
        $this->seedBusinessData($ownerA, $tenantA, $branchA, $warehouseA, 'A');
        $adminA = $this->userWithRole($tenantA, 'Admin', 'a', [$branchA->id]);
        $kasirA = $this->userWithRole($tenantA, 'Kasir', 'a', [$branchA->id]);

        // Owner tenant sendiri: halaman terbuka, ekspor terbuat, unduhan jalan.
        $this->actingAs($ownerA)->get(route('tenant-exports.index'))->assertOk();
        $this->actingAs($ownerA)->post(route('tenant-exports.store'))->assertRedirect();
        $entries = $this->exports->entries($tenantA);
        $this->assertNotEmpty($entries);
        $fileName = $entries[0]['name'];
        $this->actingAs($ownerA)->get(route('tenant-exports.download', ['berkas' => $fileName]))->assertOk();

        // Peran non-Owner pada tenant yang sama: ditolak.
        $this->actingAs($adminA)->get(route('tenant-exports.index'))->assertForbidden();
        $this->actingAs($kasirA)->post(route('tenant-exports.store'))->assertForbidden();

        // Owner tenant lain tidak dapat mengambil berkas A: dalam
        // konteksnya berkas itu memang tidak ada (404).
        $this->actingAs($ownerB)->get(route('tenant-exports.download', ['berkas' => $fileName]))->assertNotFound();

        // Platform: pemilih tampil; atas nama A terbuat & terunduh.
        $this->actingAs($this->platform)->get(route('tenant-exports.index'))->assertOk();
        $this->actingAs($this->platform)->post(route('tenant-exports.store'), ['tenant_id' => $tenantA->id])->assertRedirect();
        $this->actingAs($this->platform)->get(route('tenant-exports.download', ['tenant_id' => $tenantA->id, 'berkas' => $fileName]))->assertOk();
        // Platform tanpa tenant sasaran pada unduhan: ditolak.
        $this->actingAs($this->platform)->get(route('tenant-exports.download', ['berkas' => $fileName]))->assertForbidden();

        // Akun tanpa tenant (unassigned — instalasi sudah bertenant).
        $unassigned = User::create([
            'name' => 'Tanpa Tenant', 'username' => 'unassigned-x',
            'email' => 'unassigned-x@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->actingAs($unassigned)->get(route('tenant-exports.index'))->assertForbidden();
    }
}
