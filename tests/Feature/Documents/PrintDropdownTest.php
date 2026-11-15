<?php

namespace Tests\Feature\Documents;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penyederhanaan cetak (owner 15 Nov 2026): setiap dokumen di kepala
 * halaman menjadi SATU dropdown — pilihan cetak menurut ukuran
 * kertas + Unduh PDF — bukan deretan tombol terpisah.
 */
class PrintDropdownTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_sale_page_groups_prints_into_one_dropdown_per_document(): void
    {
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $customer = Customer::factory()->create(['branch_id' => $branch->id, 'phone' => '0812-3456-7890']);
        $frame = FrameDetail::factory()->create(['sale_price' => '500000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $branch, 'initial_stock', 3, 'test_seed', 1);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-11-15', 'tax_mode' => 'inclusive',
        ], [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '500000'],
        ]);

        $html = $this->actingAs($this->owner)->get('/penjualan/'.$sale->id)->assertOk()->getContent();

        // Dua dokumen (orderan + invoice) = dua dropdown cetak.
        $this->assertSame(2, substr_count($html, 'data-lentera="cetak-dropdown"'));
        $this->assertStringContainsString('Cetak Invoice', $html);
        $this->assertStringContainsString('Cetak Orderan', $html);
        // Isi dropdown invoice: A5 + dua ukuran struk + PDF.
        $this->assertStringContainsString(route('sales.print-invoice', $sale), $html);
        $this->assertStringContainsString(route('sales.print-receipt', [$sale, 'lebar' => 58]), $html);
        $this->assertStringContainsString(route('sales.download-invoice', $sale), $html);
        $this->assertStringContainsString(route('sales.download-order', $sale), $html);
    }

    public function test_prescription_page_has_single_print_dropdown(): void
    {
        $customer = Customer::factory()->create();
        $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

        $html = $this->actingAs($this->owner)->get('/resep/'.$prescription->id)->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-lentera="cetak-dropdown"'));
        $this->assertStringContainsString(route('prescriptions.print', $prescription), $html);
        $this->assertStringContainsString(route('prescriptions.download', $prescription), $html);
    }
}
