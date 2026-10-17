<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.3 Retur penjualan (PRD Detail 2.5): prorata integer, batas qty
 * kumulatif, refund sebagai pembayaran negatif, kredit toko.
 */
class SaleReturnTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $branch->id]);
    }

    private function paidSale(int $qty, string $price): Sale
    {
        $product = Product::factory()->ofType('accessory')->create(['sale_price' => $price]);

        // Sejak M.4 penjualan aksesori wajib punya stok.
        app(InventoryService::class)->recordMovement(
            $this->owner, $product, Branch::where('name', 'Pusat')->firstOrFail(),
            'initial_stock', $qty, 'test_seed', 1,
        );

        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $product->id, 'qty' => $qty],
        ], [
            ['method' => 'cash', 'amount' => (string) ((int) $price * $qty)],
        ]);
    }

    public function test_return_prorates_line_total_with_integer_math(): void
    {
        $sale = $this->paidSale(3, '100000'); // line_total 300.000
        $item = $sale->items->first();

        $return = app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Ukuran tidak cocok', 'cash');

        $this->assertMatchesRegularExpression('/^SR-\d{6}-0001$/', $return->number);
        $this->assertSame('100000.00', (string) $return->total_amount);

        // Refund tunai: pembayaran negatif + paid_amount berkurang.
        $sale->refresh();
        $this->assertSame('200000.00', (string) $sale->paid_amount);
        $refundRow = $sale->payments()->where('amount', '<', 0)->first();
        $this->assertNotNull($refundRow);
        $this->assertSame('RETUR '.$return->number, $refundRow->reference);
    }

    public function test_return_qty_cannot_exceed_remaining_sold(): void
    {
        $sale = $this->paidSale(2, '50000');
        $item = $sale->items->first();
        $service = app(SaleReturnService::class);

        $service->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 2, 'condition' => 'resale'],
        ], 'Retur semua', 'cash');

        $this->expectException(ValidationException::class);
        $service->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Retur lagi', 'cash');
    }

    public function test_store_credit_does_not_touch_payments(): void
    {
        $sale = $this->paidSale(1, '75000');
        $item = $sale->items->first();
        $paymentsBefore = $sale->payments()->count();

        app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'damaged'],
        ], 'Barang rusak', 'store_credit');

        $sale->refresh();
        $this->assertSame($paymentsBefore, $sale->payments()->count());
        $this->assertSame('75000.00', (string) $sale->paid_amount);
    }

    public function test_prorata_rounding_half_up(): void
    {
        // line_total 100.000 untuk qty 3 → retur 1 = 33.333,33 (bukan 33.333,34 / float drift)
        $product = Product::factory()->ofType('accessory')->create(['sale_price' => '33333.33']);
        app(InventoryService::class)->recordMovement(
            $this->owner, $product, Branch::where('name', 'Pusat')->firstOrFail(),
            'initial_stock', 3, 'test_seed', 1,
        );
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $product->id, 'qty' => 3],
        ]);
        $item = $sale->items->first();
        $this->assertSame('99999.99', (string) $item->line_total);

        $return = app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Prorata', 'cash');
        // 9.999.999 sen × 1 / 3 = 3.333.333 sen tepat
        $this->assertSame('33333.33', (string) $return->total_amount);
    }
}
