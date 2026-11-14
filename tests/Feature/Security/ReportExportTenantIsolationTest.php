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
 * D-02 GAP-4 — Isi ekspor laporan lintas tenant. Ekspor CSV laporan
 * penjualan memakai jalur data yang sama dengan layar dan berbentuk
 * agregat per cabang; berkas yang diunduh aktor tenant A hanya
 * boleh memuat baris cabang dan total tenant A sendiri — transaksi
 * tenant B dibedakan lewat jumlah dan nilai khasnya.
 */
class ReportExportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    private Sale $saleA;

    private Sale $saleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $this->adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $this->adminB, $branchB] = $this->tenantAdmin('b');

        $this->saleA = $this->seedSale($tenantA, $this->adminA, $branchA, 'A', 1);
        $this->saleB = $this->seedSale($tenantB, $this->adminB, $branchB, 'B', 2);
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
            'email_verified_at' => now(),            'name' => 'Admin Ekspor '.$tag, 'username' => 'admin-ekspor-'.$tag,
            'email' => 'admin-ekspor-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function seedSale(Tenant $tenant, User $admin, Branch $branch, string $tag, int $qty): Sale
    {
        $supplier = Supplier::create(['name' => 'PT Ekspor '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $product = FrameDetail::factory()->create(['product_id' => Product::factory()->forType('frame')->create(['name' => 'Frame Ekspor '.$tag, 'brand' => 'Uji', 'tenant_id' => $tenant->id])->id, 'sku' => 'SKU-EKSPOR-'.$tag, 'cost_price' => 100000, 'sale_price' => 250000, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $customer = Customer::create([
            'name' => 'Pelanggan Ekspor '.$tag, 'phone' => '0813'.$tag, 'branch_id' => $branch->id,
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
            'customer_id' => $customer->id, 'sale_date' => '2026-10-10',
            'tax_mode' => 'exclusive', 'branch_id' => $branch->id,
        ], [['detail_type' => 'frame', 'detail_id' => $product->id, 'qty' => $qty]], [['method' => 'cash', 'amount' => (string) (250000 * $qty)]]);
    }

    public function test_sales_report_csv_export_contains_only_own_tenant_rows(): void
    {
        // CSV laporan penjualan berbentuk agregat per cabang (tanpa
        // nomor transaksi): isolasi dibuktikan lewat cabang, jumlah
        // transaksi, dan total yang khas per tenant — A menjual 1
        // senilai 250rb, B menjual 2 senilai 500rb; kebocoran apa pun
        // akan mengubah hitungan dan total pihak lain.
        $csvA = $this->actingAs($this->adminA)
            ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&ekspor=1')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Cabang a",1,250000.00', $csvA);
        $this->assertStringContainsString('TOTAL,1,', $csvA);
        $this->assertStringNotContainsString('Cabang b', $csvA);
        $this->assertStringNotContainsString('555000.00', $csvA);

        $csvB = $this->actingAs($this->adminB)
            ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&ekspor=1')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('"Cabang b",1,500000.00', $csvB);
        $this->assertStringContainsString('TOTAL,1,', $csvB);
        $this->assertStringNotContainsString('Cabang a', $csvB);
        $this->assertStringNotContainsString('277500.00', $csvB);
    }

    public function test_sales_report_xlsx_export_contains_only_own_tenant_rows(): void
    {
        // XLSX memakai jalur data yang sama dengan CSV (ReportService);
        // isi workbook dibaca dari shared strings + sel lembar kerja.
        $xlsxA = $this->extractXlsxText(
            $this->actingAs($this->adminA)
                ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&format=xlsx')
                ->assertOk()
                ->streamedContent(),
        );

        $this->assertStringContainsString('Cabang a', $xlsxA);
        $this->assertStringContainsString('277500', $xlsxA);
        $this->assertStringNotContainsString('Cabang b', $xlsxA);
        $this->assertStringNotContainsString('555000', $xlsxA);

        $xlsxB = $this->extractXlsxText(
            $this->actingAs($this->adminB)
                ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&format=xlsx')
                ->assertOk()
                ->streamedContent(),
        );

        $this->assertStringContainsString('Cabang b', $xlsxB);
        $this->assertStringNotContainsString('Cabang a', $xlsxB);
        $this->assertStringNotContainsString('277500', $xlsxB);
    }

    private function extractXlsxText(string $binary): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-uji-');
        file_put_contents($path, $binary);

        $text = '';
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Ekspor XLSX harus berupa arsip zip yang valid');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === 'xl/sharedStrings.xml' || str_starts_with((string) $name, 'xl/worksheets/')) {
                $text .= (string) $zip->getFromIndex($i);
            }
        }
        $zip->close();
        unlink($path);

        return $text;
    }
}
