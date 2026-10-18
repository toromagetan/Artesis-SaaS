<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F4.3 Expiry softlens (PRD 4.4): tanggal kedaluwarsa wajib pada
 * penerimaan softlens terlacak, lapisan kedaluwarsa tidak pernah
 * terkonsumsi penjualan, penjualan yang tak tertutup stok layak
 * jual ditolak selama stok kedaluwarsa masih tersimpan, dan data
 * peringatan (dashboard/daftar) tersedia dari lapisan.
 */
class SoftlensExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->supplier = Supplier::create(['name' => 'PT Lensa Uji', 'is_active' => true]);
    }

    private function trackedSoftlens(string $sku = 'SL-EXP-1'): Product
    {
        $product = Product::factory()->ofType('softlens')->create([
            'sku' => $sku, 'cost_price' => '30000', 'sale_price' => '90000',
        ]);
        $product->productAttributes()->create(['key' => 'expiry_tracked', 'value' => '1']);
        $product->productAttributes()->create(['key' => 'power', 'value' => '-2.00']);

        return $product->refresh();
    }

    private function receive(Product $product, int $qty, ?string $expiryDate): Receipt
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => '30000'],
        ]);
        $orders->markSent($this->owner, $po);
        $line = ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty, 'unit_cost_actual' => '30000'];
        if ($expiryDate !== null) {
            $line['expiry_date'] = $expiryDate;
        }

        return app(ReceiptService::class)->store($this->owner, $po, [$line]);
    }

    public function test_tracked_softlens_receipt_requires_expiry_date(): void
    {
        $product = $this->trackedSoftlens();
        try {
            $this->receive($product, 5, null);
            $this->fail('Penerimaan softlens terlacak tanpa tanggal kedaluwarsa harus ditolak.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, Receipt::count());

        $receipt = $this->receive($product, 5, now()->addYear()->toDateString());
        $this->assertSame(
            now()->addYear()->toDateString(),
            $receipt->items->first()->expiry_date->toDateString(),
        );
    }

    public function test_untracked_product_receipt_without_expiry_is_fine(): void
    {
        $product = Product::factory()->ofType('softlens')->create([
            'sku' => 'SL-EXP-X', 'cost_price' => '30000', 'sale_price' => '90000',
        ]);
        $receipt = $this->receive($product, 3, null);
        $this->assertNull($receipt->items->first()->expiry_date);
    }

    public function test_sale_blocked_when_only_expired_stock_exists(): void
    {
        $product = $this->trackedSoftlens();
        $this->receive($product, 5, now()->subDay()->toDateString());

        try {
            app(SalesService::class)->createSale($this->owner, [
                'customer_id' => $this->customer->id, 'tax_mode' => 'exclusive', 'tax_rate' => '0',
            ], [['product_id' => $product->id, 'qty' => 2]]);
            $this->fail('Penjualan softlens kedaluwarsa harus diblokir.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, Sale::count());
    }

    public function test_sale_consumes_fresh_layers_and_skips_expired(): void
    {
        $product = $this->trackedSoftlens();
        $this->receive($product, 5, now()->subDay()->toDateString());
        $this->receive($product, 5, now()->addYear()->toDateString());

        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'exclusive', 'tax_rate' => '0',
        ], [['product_id' => $product->id, 'qty' => 3]]);

        $layers = StockLayer::where('product_id', $product->id)->orderBy('id')->get();
        $this->assertSame(5, (int) $layers[0]->qty_remaining, 'Lapisan kedaluwarsa tidak boleh tersentuh.');
        $this->assertSame(2, (int) $layers[1]->qty_remaining);

        // Sisa layak jual 2 < minta 3, stok kedaluwarsa masih ada → blokir.
        try {
            app(SalesService::class)->createSale($this->owner, [
                'customer_id' => $this->customer->id, 'tax_mode' => 'exclusive', 'tax_rate' => '0',
            ], [['product_id' => $product->id, 'qty' => 3]]);
            $this->fail('Penjualan melampaui stok layak jual harus diblokir selama stok kedaluwarsa tersimpan.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_backorder_without_expired_stock_still_allowed(): void
    {
        $product = $this->trackedSoftlens();
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'exclusive', 'tax_rate' => '0',
        ], [['product_id' => $product->id, 'qty' => 2]]);
        $this->assertSame('active', $sale->status);
    }

    public function test_expiry_warnings_cover_expired_and_near_only(): void
    {
        $product = $this->trackedSoftlens();
        $this->receive($product, 2, now()->subDay()->toDateString());
        $this->receive($product, 3, now()->addDays(30)->toDateString());
        $this->receive($product, 4, now()->addDays(200)->toDateString());

        $fifo = app(FifoService::class);
        $rows = $fifo->expiringLayers([$this->branch->id], 90);
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]['expired']);
        $this->assertFalse($rows[1]['expired']);
        $this->assertSame(3, $rows[1]['qty']);

        $nearest = $fifo->nearestExpiries([$product->id], [$this->branch->id]);
        $this->assertSame(now()->subDay()->toDateString(), $nearest[$product->id]['date']);
        $this->assertTrue($nearest[$product->id]['expired']);
        $this->assertSame(2, $fifo->expiredQty($product->id, $this->branch->id));
        $this->assertSame(7, $fifo->sellableQty($product->id, $this->branch->id));
    }
}
