<?php

namespace Tests\Feature\Reporting;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.10 Laporan inti: penjualan (void tersisih, retur mengurangi
 * neto), pembelian NETO Opsi 2 (bruto + retur + neto), stok +
 * dalam perjalanan, utang INKLUSI, ekspor == layar (G.1.3), dan
 * pagar cabang pada laporan.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Branch $branchB;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->branchB = Branch::create(['name' => 'Cabang Laporan']);
        $this->frame = Product::factory()->ofType('frame')->create([
            'name' => 'Frame Laporan', 'cost_price' => '50000', 'sale_price' => '100000',
        ]);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 50, 'test_seed', 1);
        // Tarif pajak 0 agar angka laporan bulat & tegas.
        Setting::where('key', 'tax.rate_default')->update(['value' => '0']);
    }

    private function reports(): ReportService
    {
        return app(ReportService::class);
    }

    private function sell(int $qty, string $pay, string $date = '2026-10-10', ?Branch $branch = null): Sale
    {
        $branch ??= $this->branch;
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);

        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'sale_date' => $date,
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_price' => '100000'],
        ], $pay === '0' ? [] : [['method' => 'cash', 'amount' => $pay]]);
    }

    public function test_sales_report_excludes_void_and_nets_returns(): void
    {
        $sale = $this->sell(2, '200000');                    // total 200rb lunas
        $this->sell(1, '40000');                              // total 100rb, piutang 60rb
        $voided = $this->sell(1, '100000');
        app(SalesService::class)->voidSale($this->owner, $voided, 'batal uji');
        app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'uji retur laporan', 'cash');

        $rows = $this->reports()->salesSummary('2026-10-01', '2026-10-31', [$this->branch->id]);
        $row = $rows[0];
        $this->assertSame(2, $row['transactions']);           // void tidak dihitung
        $this->assertSame('300000.00', $row['total']->toDecimalString());
        $this->assertSame('100000.00', $row['returns']->toDecimalString());
        $this->assertSame('200000.00', $row['net']->toDecimalString());
        // Terbayar neto refund retur tunai: (200rb − 100rb refund) + 40rb.
        $this->assertSame('140000.00', $row['paid']->toDecimalString());
        $this->assertSame('60000.00', $row['receivable']->toDecimalString());
    }

    public function test_purchases_net_and_payables_inclusion(): void
    {
        $supplier = Supplier::create(['name' => 'PT Laporan', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 10, 'unit_cost' => '40000'],
        ]);
        $orders->markSent($this->owner, $po);
        // Tanggal penerimaan di LUAR bulan laporan lain — utang tetap inklusif.
        $receipt = app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 10],
        ], '2026-08-05');
        app(PurchaseReturnService::class)->store($this->owner, $receipt, [
            ['receipt_item_id' => $receipt->items->first()->id, 'qty' => 2],
        ], 'rusak sebagian');

        // Jendela laporan mencakup penerimaan (Agu) & retur (Okt):
        // retur dihitung pada periode terjadinya, penerimaan pada
        // tanggal terimanya — keduanya tampil dalam satu laporan.
        $net = $this->reports()->purchasesNet('2026-08-01', '2026-10-31', [$this->branch->id]);
        $this->assertCount(1, $net);
        $this->assertSame('400000.00', $net[0]['gross']->toDecimalString());
        $this->assertSame('80000.00', $net[0]['returns']->toDecimalString());
        $this->assertSame('320000.00', $net[0]['net']->toDecimalString());

        $payables = $this->reports()->supplierPayables([$this->branch->id]);
        $this->assertSame('320000.00', $payables['grand_total']->toDecimalString());
        $this->assertSame($receipt->number, $payables['suppliers'][0]['receipts'][0]['number']);
    }

    public function test_stock_report_value_and_in_transit(): void
    {
        app(TransferService::class)->sendFromBranchStock($this->owner, $this->branch->id, $this->branchB->id, [
            ['product_id' => $this->frame->id, 'qty' => 5],
        ]);
        $rows = $this->reports()->stockReport([$this->branch->id, $this->branchB->id]);
        $byBranch = collect($rows)->keyBy('branch_id');
        $this->assertSame(45, $byBranch[$this->branch->id]['qty']);
        $this->assertSame('2250000.00', $byBranch[$this->branch->id]['value']->toDecimalString());
        // Cabang tujuan belum punya gerakan: baris sintetis menampilkan
        // stok dalam perjalanan (PRD 7.3).
        $this->assertSame(0, $byBranch[$this->branchB->id]['qty']);
        $this->assertSame(5, $byBranch[$this->branchB->id]['in_transit']);
    }

    public function test_export_matches_screen_and_branch_fence(): void
    {
        $this->sell(1, '100000');

        $response = $this->actingAs($this->owner)
            ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&ekspor=1');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('TOTAL,1,', $csv);
        $this->assertStringContainsString('100000.00', $csv);

        // Layar menampilkan total yang sama.
        $this->actingAs($this->owner)
            ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31')
            ->assertOk()
            ->assertSee('Rp100.000');

        // Pengguna tercabang (peran Cabang memegang reports.sales):
        // memilih cabang di luar kewenangan → kosong, bukan bocor.
        $kasirB = User::factory()->create();
        $kasirB->roles()->sync([Role::where('name', 'cabang')->firstOrFail()->id]);
        $kasirB->branches()->sync([$this->branchB->id]);
        $this->actingAs($kasirB)
            ->get('/laporan/penjualan?dari=2026-10-01&sampai=2026-10-31&cabang='.$this->branch->id)
            ->assertOk()
            ->assertDontSee('Rp100.000');
        // Cabang sendiri kosong (tidak ada penjualan di B).
        $rows = $this->reports()->salesSummary('2026-10-01', '2026-10-31', [$this->branchB->id]);
        $this->assertSame('0.00', $rows[0]['total']->toDecimalString());
    }
}
