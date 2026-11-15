<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengaturan PPN tampil/tidak (owner 15 Nov 2026): rata-rata optik
 * belum PKP sehingga tidak boleh menarik PPN. tax.enabled = '0'
 * mematikan pajak di seluruh rantai — hitungan penjualan, jurnal
 * PPN Keluaran, kasir, dan baris pajak pada dokumen. Bawaan aktif
 * (perilaku lama untuk toko PKP tidak berubah).
 */
class TaxToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private FrameDetail $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->frame = FrameDetail::factory()->create(['sale_price' => '500000']);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 10, 'test_seed', 1);
    }

    private function sell(): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-11-15', 'tax_mode' => 'inclusive',
        ], [
            ['detail_type' => 'frame', 'detail_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '500000'],
        ]);
    }

    private function ppnLineCount(Sale $sale): int
    {
        return JournalLine::join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->where('accounts.code', '2-1400')
            ->where('journals.source_type', 'sale')
            ->where('journals.source_id', $sale->id)
            ->count();
    }

    public function test_tax_applies_by_default_control(): void
    {
        $sale = $this->sell();

        $this->assertGreaterThan(0, (float) $sale->tax_amount);
        $this->assertSame(1, $this->ppnLineCount($sale));
        $this->actingAs($this->owner)->get('/penjualan/'.$sale->id)
            ->assertOk()->assertSee('Pajak (inclusive)');
    }

    public function test_tax_disabled_removes_tax_from_sale_journal_and_documents(): void
    {
        $this->actingAs($this->owner);
        Setting::set('tax.enabled', '0');

        $sale = $this->sell();

        $this->assertSame('0.00', (string) $sale->tax_amount);
        $this->assertSame('500000.00', (string) $sale->total_amount);
        $this->assertSame(0, $this->ppnLineCount($sale));
        $this->actingAs($this->owner)->get('/penjualan/'.$sale->id)
            ->assertOk()->assertDontSee('Pajak (inclusive)');
        $this->actingAs($this->owner)->get('/penjualan/baru')
            ->assertOk()->assertSee('taxEnabled: false', false);
    }

    public function test_tax_setting_saved_via_general_settings(): void
    {
        $this->actingAs($this->owner)->put('/pengaturan/umum', [
            'store_name' => 'Optik Uji',
            'tax_enabled' => '0',
            'tax_rate_default' => '11',
            'tax_mode' => 'inclusive',
            'discount_threshold' => '20',
            'technician_commission_rate' => '5',
        ])->assertRedirect();

        $this->assertSame('0', Setting::value('tax.enabled'));
    }
}
