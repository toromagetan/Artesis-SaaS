<?php

namespace Tests\Feature\Inventory;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F4.2 FIFO per cabang (ADR-014). Invariant yang dijaga di semua
 * skenario: nilai lapisan tersisa == saldo Persediaan buku besar
 * cabang, dan HPP jurnal == nilai konsumsi lapisan.
 */
class FifoCostingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $pusat;

    private Branch $cabangB;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $this->cabangB = Branch::create(['name' => 'Cabang B']);
        $this->customer = Customer::factory()->create(['branch_id' => $this->pusat->id]);
        $this->supplier = Supplier::create(['name' => 'PT FIFO Uji', 'is_active' => true]);
    }

    private function fifo(): FifoService
    {
        return app(FifoService::class);
    }

    /** Terima $qty unit produk ke Pusat pada harga aktual $unitCost. */
    private function receive(Product $product, int $qty, string $unitCost, ?Branch $branch = null): Receipt
    {
        $branch ??= $this->pusat;
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => $unitCost],
        ]);
        $orders->markSent($this->owner, $po);
        $receipt = app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty, 'unit_cost_actual' => $unitCost],
        ]);

        return $receipt;
    }

    private function sell(Product $product, int $qty, ?Branch $branch = null): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id,
            'tax_mode' => 'exclusive', 'tax_rate' => '0',
            'branch_id' => ($branch ?? $this->pusat)->id,
        ], [
            ['product_id' => $product->id, 'qty' => $qty],
        ]);
    }

    /** Saldo buku besar satu akun pada satu cabang (debit − kredit). */
    private function glBalance(string $accountCode, int $branchId): Money
    {
        $value = DB::table('journal_lines as jl')
            ->join('accounts as a', 'a.id', '=', 'jl.account_id')
            ->where('a.code', $accountCode)
            ->where('jl.branch_id', $branchId)
            ->selectRaw('COALESCE(SUM(jl.debit - jl.credit), 0) as balance')
            ->value('balance');

        return Money::fromDecimal((string) $value);
    }

    private function journalLines(string $sourceType, int $sourceId): array
    {
        $journal = Journal::where('source_type', $sourceType)->where('source_id', $sourceId)->first();
        if ($journal === null) {
            return [];
        }

        return $journal->lines()->with('account')->get()
            ->mapWithKeys(fn ($line) => [$line->account->code => $line])
            ->all();
    }

    public function test_fifo_layers_hpp_and_gl_reconcile(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-1', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 10, '80000');
        $this->receive($frame, 10, '100000');

        $sale = $this->sell($frame, 12);

        // HPP FIFO: 10 × 80.000 + 2 × 100.000 = 1.000.000 (bukan 12 × 75.000 master).
        $lines = $this->journalLines('sale', $sale->id);
        $this->assertSame('1000000.00', (string) $lines['5-1100']->debit);
        $this->assertSame('1000000.00', (string) $lines['1-1400']->credit);

        $layers = StockLayer::where('product_id', $frame->id)->orderBy('id')->get();
        $this->assertSame(0, (int) $layers[0]->qty_remaining);
        $this->assertSame(8, (int) $layers[1]->qty_remaining);

        $value = $this->fifo()->inventoryValue($frame->id, $this->pusat->id);
        $this->assertSame('800000.00', $value->toDecimalString());
        $this->assertTrue($this->glBalance('1-1400', $this->pusat->id)->equals($value));
    }

    public function test_backorder_hpp_deferred_until_covering_receipt(): void
    {
        $lens = Product::factory()->ofType('lens')->create([
            'sku' => 'LN-FIFO-1', 'cost_price' => '40000', 'sale_price' => '150000',
        ]);
        $sale = $this->sell($lens, 4);

        // Belum ada lapisan: HPP penjualan nol, tidak ada jurnal coverage.
        $lines = $this->journalLines('sale', $sale->id);
        $this->assertArrayNotHasKey('5-1100', $lines);
        $this->assertSame(0, Journal::where('source_type', 'fifo_coverage')->count());

        $receipt = $this->receive($lens, 10, '50000');

        // Penerimaan menutup backorder: HPP tertangguh 4 × 50.000.
        $coverage = $this->journalLines('fifo_coverage', $receipt->id);
        $this->assertSame('200000.00', (string) $coverage['5-1100']->debit);
        $this->assertSame('200000.00', (string) $coverage['1-1400']->credit);

        $value = $this->fifo()->inventoryValue($lens->id, $this->pusat->id);
        $this->assertSame('300000.00', $value->toDecimalString());
        $this->assertTrue($this->glBalance('1-1400', $this->pusat->id)->equals($value));
        $this->assertSame(6, app(InventoryService::class)->currentStock($lens->id, $this->pusat->id));
    }

    public function test_purchase_return_consumes_own_receipt_layers_first(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-2', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 10, '80000');
        $second = $this->receive($frame, 10, '100000');

        $retur = app(PurchaseReturnService::class)->store($this->owner, $second, [
            ['receipt_item_id' => $second->items->first()->id, 'qty' => 3],
        ], 'Kelebihan kirim');

        $layers = StockLayer::where('product_id', $frame->id)->orderBy('id')->get();
        $this->assertSame(10, (int) $layers[0]->qty_remaining);
        $this->assertSame(7, (int) $layers[1]->qty_remaining);

        // Nilai tercatat == nilai dokumen (3 × 100.000): tanpa baris selisih HPP.
        $lines = $this->journalLines('purchase_return', $retur->id);
        $this->assertSame('300000.00', (string) $lines['1-1400']->credit);
        $hppLines = Journal::where('source_type', 'purchase_return')->where('source_id', $retur->id)
            ->first()->lines()->with('account')->get()
            ->filter(fn ($line) => $line->account->code === '5-1100');
        $this->assertCount(0, $hppLines);
    }

    public function test_sale_return_restocks_at_original_fifo_cost(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-3', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 5, '80000');
        $sale = $this->sell($frame, 2);

        $retur = app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Tukar model', 'cash');

        // Restock masuk pada biaya asal 80.000 — bukan master 75.000.
        $lines = $this->journalLines('sale_return', $retur->id);
        $this->assertSame('80000.00', (string) $lines['1-1400']->debit);
        $this->assertSame('80000.00', (string) $lines['5-1100']->credit);
        $restockLayer = StockLayer::where('product_id', $frame->id)
            ->where('qty_received', 1)->firstOrFail();
        $this->assertSame('80000.00', (string) $restockLayer->unit_cost);
    }

    public function test_transfer_carries_layer_costs_to_destination(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-4', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 10, '80000');

        $transfers = app(TransferService::class);
        $transfer = $transfers->sendFromBranchStock($this->owner, $this->pusat->id, $this->cabangB->id, [
            ['product_id' => $frame->id, 'qty' => 4],
        ]);
        $transfers->receive($this->owner, $transfer);

        $destValue = $this->fifo()->inventoryValue($frame->id, $this->cabangB->id);
        $this->assertSame('320000.00', $destValue->toDecimalString());
        $destLayer = StockLayer::where('product_id', $frame->id)
            ->where('branch_id', $this->cabangB->id)->firstOrFail();
        $this->assertSame('80000.00', (string) $destLayer->unit_cost);
        $this->assertSame('480000.00', $this->fifo()->inventoryValue($frame->id, $this->pusat->id)->toDecimalString());
    }

    public function test_void_restores_layer_quantities(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-5', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 5, '80000');
        $this->receive($frame, 5, '100000');
        $sale = $this->sell($frame, 7);

        $layers = StockLayer::where('product_id', $frame->id)->orderBy('id')->get();
        $this->assertSame([0, 3], $layers->map(fn ($l) => (int) $l->qty_remaining)->all());

        app(SalesService::class)->voidSale($this->owner, $sale, 'Salah input');

        $layers = StockLayer::where('product_id', $frame->id)->orderBy('id')->get();
        $this->assertSame([5, 5], $layers->map(fn ($l) => (int) $l->qty_remaining)->all());
        $this->assertSame('900000.00', $this->fifo()->inventoryValue($frame->id, $this->pusat->id)->toDecimalString());
    }

    public function test_migration_rebuilds_layers_and_posts_opening_adjustment(): void
    {
        $frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-FIFO-6', 'cost_price' => '75000', 'sale_price' => '200000',
        ]);
        $this->receive($frame, 10, '80000');
        $this->receive($frame, 10, '100000');
        $sale = $this->sell($frame, 12);

        // Simulasikan buku pra-FIFO: HPP jurnal penjualan ditulis
        // ulang ke metode statis lama (12 × 75.000 = 900.000) dan
        // lapisan dibuang — seolah data berjalan sebelum F4.2.
        $journal = Journal::where('source_type', 'sale')->where('source_id', $sale->id)->firstOrFail();
        foreach ($journal->lines as $line) {
            if ((string) $line->debit === '1000000.00') {
                $line->update(['debit' => '900000.00']);
            }
            if ((string) $line->credit === '1000000.00') {
                $line->update(['credit' => '900000.00']);
            }
        }
        DB::table('stock_layer_consumptions')->delete();
        DB::table('stock_layers')->delete();
        DB::table('stock_movements')->update(['unit_cost' => null, 'total_cost' => null]);
        $this->assertSame('900000.00', $this->glBalance('1-1400', $this->pusat->id)->toDecimalString());

        Artisan::call('lentera:fifo-migrasi');

        // Lapisan terbangun ulang persis FIFO; GL disesuaikan ke nilai lapisan.
        $this->assertSame('800000.00', $this->fifo()->inventoryValue($frame->id, $this->pusat->id)->toDecimalString());
        $this->assertSame('800000.00', $this->glBalance('1-1400', $this->pusat->id)->toDecimalString());
        $opening = $this->journalLines('fifo_opening', $this->pusat->id);
        $this->assertSame('100000.00', (string) $opening['5-1100']->debit);
        $this->assertSame('100000.00', (string) $opening['1-1400']->credit);

        // Gerakan penjualan kembali tercatat biaya FIFO-nya.
        $movement = DB::table('stock_movements')
            ->where('reference_type', 'sale')->where('reference_id', $sale->id)
            ->where('reason', 'sale')->first();
        $this->assertSame('1000000.00', (string) $movement->total_cost);
    }
}
