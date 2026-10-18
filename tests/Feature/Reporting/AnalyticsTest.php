<?php

namespace Tests\Feature\Reporting;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Reporting\Services\AnalyticsService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4.5 Analitik: angka rekonsiliasi dengan sumber — lead time
 * dari sent_at→received_date, nilai beli dari receipt aktual,
 * tingkat retur dari retur pembelian, margin kategori dari biaya
 * FIFO gerakan sale, tren bulanan per cabang, pagar cabang.
 */
class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        // Tarif pajak dibaca dari Setting (bukan data penjualan) —
        // nolkan agar aritmetika analitik bersih.
        \App\Modules\Master\Models\Setting::set('tax.rate_default', '0');
    }

    public function test_supplier_and_sales_analytics_reconcile_with_sources(): void
    {
        $supplierA = Supplier::create(['name' => 'PT Analitik A', 'is_active' => true]);
        $supplierB = Supplier::create(['name' => 'PT Analitik B', 'is_active' => true]);
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'AN-F1', 'cost_price' => '50000', 'sale_price' => '100000',
        ]);
        $orders = app(PurchaseOrderService::class);
        $receipts = app(ReceiptService::class);

        // Supplier A: dua PO — lead 5 hari dan 10 hari → rata-rata 7.50.
        $receiptA1 = null;
        foreach ([['2026-09-01', '2026-09-06'], ['2026-09-01', '2026-09-11']] as [$sent, $received]) {
            $po = $orders->create($this->owner, $supplierA->id, $this->branch->id, [
                ['product_id' => $frame->id, 'qty' => 10, 'unit_cost' => '50000'],
            ]);
            $orders->markSent($this->owner, $po);
            $po->forceFill(['order_date' => $sent])->save();
            $r = $receipts->store($this->owner, $po, [
                ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 10, 'unit_cost_actual' => '50000'],
            ], $received);
            $receiptA1 ??= $r;
        }
        // Supplier B: satu PO — lead 2 hari, nilai 400.000.
        $poB = $orders->create($this->owner, $supplierB->id, $this->branch->id, [
            ['product_id' => $frame->id, 'qty' => 5, 'unit_cost' => '80000'],
        ]);
        $orders->markSent($this->owner, $poB);
        $poB->forceFill(['order_date' => '2026-09-03'])->save();
        $receipts->store($this->owner, $poB, [
            ['purchase_order_item_id' => $poB->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '80000'],
        ], '2026-09-05');

        // Retur pembelian 2 unit dari receipt A pertama (di periode).
        app(PurchaseReturnService::class)->store($this->owner, $receiptA1, [
            ['receipt_item_id' => $receiptA1->items->first()->id, 'qty' => 2],
        ], 'Rusak');

        // Penjualan: Sept 3×100.000 (HPP FIFO 3×50.000), Okt 2×100.000.
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $sales = app(SalesService::class);
        $sales->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-09-15',
            'tax_mode' => 'exclusive', 'tax_rate' => '0', 'branch_id' => $this->branch->id,
        ], [['product_id' => $frame->id, 'qty' => 3]], [['method' => 'cash', 'amount' => '300000']]);
        $sales->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-02',
            'tax_mode' => 'exclusive', 'tax_rate' => '0', 'branch_id' => $this->branch->id,
        ], [['product_id' => $frame->id, 'qty' => 2]], [['method' => 'cash', 'amount' => '200000']]);

        $analytics = app(AnalyticsService::class);
        $suppliers = $analytics->supplierAnalytics([$this->branch->id], '2026-09-01', '2026-10-31');
        $this->assertCount(2, $suppliers);
        $a = collect($suppliers)->firstWhere('supplier_name', 'PT Analitik A');
        $this->assertSame(2, $a['po_count']);
        $this->assertSame('7.50', $a['lead_time_days']);
        $this->assertSame(100_000_000, $a['purchase_value']->cents());
        $this->assertSame(20, $a['received_qty']);
        $this->assertSame(2, $a['returned_qty']);
        $this->assertSame(1000, $a['return_rate_bp']); // 10.00%
        $b = collect($suppliers)->firstWhere('supplier_name', 'PT Analitik B');
        $this->assertSame('2.00', $b['lead_time_days']);
        $this->assertSame(40_000_000, $b['purchase_value']->cents());

        $salesData = $analytics->salesAnalytics([$this->branch->id], '2026-09-01', '2026-09-30');
        $this->assertSame('AN-F1', $salesData['top_products'][0]['sku']);
        $this->assertSame(3, $salesData['top_products'][0]['qty']);
        $frameMargin = collect($salesData['category_margins'])->firstWhere('type', 'frame');
        $this->assertSame(30_000_000, $frameMargin['revenue']->cents());
        $this->assertSame(15_000_000, $frameMargin['cost']->cents());
        $this->assertSame(15_000_000, $frameMargin['margin']->cents());

        $both = $analytics->salesAnalytics([$this->branch->id], '2026-09-01', '2026-10-31');
        $trend = collect($both['branch_trend']);
        $this->assertSame(30_000_000, $trend->firstWhere('month', '2026-09')['revenue']->cents());
        $this->assertSame(20_000_000, $trend->firstWhere('month', '2026-10')['revenue']->cents());
        $this->assertSame(50_000_000, $both['top_customers'][0]['revenue']->cents());

        // Pagar cabang: cabang lain kosong → semua kosong.
        $other = Branch::create(['name' => 'Cabang Analitik Lain']);
        $this->assertSame([], $analytics->supplierAnalytics([$other->id], '2026-09-01', '2026-10-31'));
        $this->assertSame([], $analytics->salesAnalytics([$other->id], '2026-09-01', '2026-10-31')['top_products']);
    }

    public function test_analytics_page_and_export(): void
    {
        $this->actingAs($this->owner)
            ->get(route('reports.analytics'))
            ->assertOk()
            ->assertSee(__('ui.analytics.title'));
        $response = $this->actingAs($this->owner)
            ->get(route('reports.analytics', ['ekspor' => 1, 'dari' => '2026-09-01', 'sampai' => '2026-09-30']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
