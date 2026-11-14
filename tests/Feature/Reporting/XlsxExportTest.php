<?php

namespace Tests\Feature\Reporting;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekspor XLSX asli: laporan penjualan ?format=xlsx mengunduh
 * workbook (header PK + content-type spreadsheet) dari jalur
 * data yang sama dengan CSV; CSV lama tetap bekerja.
 */
class XlsxExportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        // Fixture tingkat instalasi: konteks sistem dinyatakan eksplisit
        // (penulisan global tanpa aktor wajib TenantContext::asSystem).
        TenantContext::asSystem(fn () => Setting::set('tax.rate_default', '0'));
        $frame = FrameDetail::factory()->create(['cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $branch, 'initial_stock', 5, 'test', 1);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1, 'unit_price' => '100000']],
            [['method' => 'cash', 'amount' => '100000']]);
    }

    public function test_sales_report_xlsx_download(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.sales', ['format' => 'xlsx']));
        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_sales_report_csv_still_works(): void
    {
        $response = $this->actingAs($this->owner)->get(route('reports.sales', ['ekspor' => 1]));
        $response->assertOk();
        $this->assertStringContainsString('Cabang', $response->streamedContent());
    }
}
