<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Inventory\Services\OpnameService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Reporting\Services\AnalyticsService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GERBANG FASE 4 (Roadmap): satu siklus opname penuh terekonsiliasi
 * ke jurnal persediaan; angka persediaan (lapisan FIFO = buku besar
 * = laporan stok) cocok; angka analitik = angka laporan sumber
 * pada DUA periode uji (Sept & Okt 2026).
 */
class PhaseFourGateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Supplier $supplier;

    private Product $frame;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::set('tax.rate_default', '0');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->supplier = Supplier::create(['name' => 'PT Gerbang Empat', 'is_active' => true]);
        $this->frame = Product::factory()->ofType('frame')->create([
            'sku' => 'G4-F1', 'cost_price' => '80000', 'sale_price' => '150000',
        ]);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    private function receive(int $qty, string $unitCost, string $date): Receipt
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_cost' => $unitCost],
        ]);
        $orders->markSent($this->owner, $po);

        return app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty, 'unit_cost_actual' => $unitCost],
        ], $date);
    }

    private function sell(int $qty, string $date): void
    {
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => $date,
            'tax_mode' => 'exclusive', 'tax_rate' => '0', 'branch_id' => $this->branch->id,
        ], [['product_id' => $this->frame->id, 'qty' => $qty]], [
            ['method' => 'cash', 'amount' => (string) ($qty * 150000)],
        ]);
    }

    private function glInventoryCents(): int
    {
        $row = JournalLine::query()
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '1-1400')
            ->selectRaw('SUM(journal_lines.debit - journal_lines.credit) as balance')
            ->first();

        return (int) round(((string) ($row->balance ?? '0')) * 100);
    }

    public function test_full_opname_cycle_reconciles_and_analytics_match_sources_two_periods(): void
    {
        $fifo = app(FifoService::class);
        $reports = app(ReportService::class);
        $analytics = app(AnalyticsService::class);

        // --- Periode 1 (Sept): terima 10 @80rb, jual 4, opname kurang 1.
        $this->receive(10, '80000', '2026-09-05');
        $this->sell(4, '2026-09-10');

        $opnames = app(OpnameService::class);
        $opname = $opnames->schedule($this->owner, $this->branch->id, '2026-09-20');
        $opnames->startCounting($this->owner, $opname);
        $item = $opname->items()->where('product_id', $this->frame->id)->firstOrFail();
        $this->assertSame(6, (int) $item->system_qty);
        $opnames->saveCounts($this->owner, $opname, [$item->id => 5]);
        $opnames->submitReview($this->owner, $opname);
        $opnames->approve($this->owner, $opname);

        // Rekonsiliasi Sept: GL Persediaan == nilai lapisan == laporan stok.
        // 800.000 masuk − 320.000 HPP − 80.000 selisih opname = 400.000.
        $this->assertSame(40_000_000, $this->glInventoryCents());
        $this->assertSame(40_000_000, $fifo->inventoryValue($this->frame->id, $this->branch->id)->cents());
        $stockRows = $reports->stockReport([$this->branch->id]);
        $row = collect($stockRows)->firstWhere('product_id', $this->frame->id);
        $this->assertSame(5, $row['qty']);
        $this->assertSame(40_000_000, $row['value']->cents());

        // Analitik Sept == laporan sumber Sept.
        $salesSept = $analytics->salesAnalytics([$this->branch->id], '2026-09-01', '2026-09-30');
        $margin = collect($salesSept['category_margins'])->firstWhere('type', 'frame');
        $this->assertSame(60_000_000, $margin['revenue']->cents()); // 4 × 150.000
        $this->assertSame(32_000_000, $margin['cost']->cents());    // 4 × 80.000 FIFO
        $sourceSept = $reports->salesSummary('2026-09-01', '2026-09-30', [$this->branch->id]);
        $this->assertSame(
            $margin['revenue']->cents(),
            collect($sourceSept)->sum(fn ($r) => $r['total']->cents()),
        );

        // --- Periode 2 (Okt): terima 10 @100rb, jual 12 (5@80rb + 7@100rb).
        $this->receive(10, '100000', '2026-10-03');
        $this->sell(12, '2026-10-10');

        // Rekonsiliasi Okt: 400.000 + 1.000.000 − 1.100.000 HPP = 300.000 (3 @100rb).
        $this->assertSame(30_000_000, $this->glInventoryCents());
        $this->assertSame(30_000_000, $fifo->inventoryValue($this->frame->id, $this->branch->id)->cents());
        $row = collect($reports->stockReport([$this->branch->id]))->firstWhere('product_id', $this->frame->id);
        $this->assertSame(3, $row['qty']);
        $this->assertSame(30_000_000, $row['value']->cents());

        // Analitik Okt == laporan sumber Okt; margin memakai biaya FIFO campuran.
        $salesOct = $analytics->salesAnalytics([$this->branch->id], '2026-10-01', '2026-10-31');
        $marginOct = collect($salesOct['category_margins'])->firstWhere('type', 'frame');
        $this->assertSame(180_000_000, $marginOct['revenue']->cents()); // 12 × 150.000
        $this->assertSame(110_000_000, $marginOct['cost']->cents());    // 5×80rb + 7×100rb
        $this->assertSame(70_000_000, $marginOct['margin']->cents());
        $sourceOct = $reports->salesSummary('2026-10-01', '2026-10-31', [$this->branch->id]);
        $this->assertSame(
            $marginOct['revenue']->cents(),
            collect($sourceOct)->sum(fn ($r) => $r['total']->cents()),
        );

        // Analitik supplier dua periode: nilai beli = receipt aktual.
        $supplierRows = $analytics->supplierAnalytics([$this->branch->id], '2026-09-01', '2026-10-31');
        $supplier = collect($supplierRows)->firstWhere('supplier_name', 'PT Gerbang Empat');
        $this->assertSame(180_000_000, $supplier['purchase_value']->cents());
        $this->assertSame(20, $supplier['received_qty']);
    }
}
