<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Support\Barcode;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F4.4 Label barcode: enkoder EAN-13 & Code 128 (SVG murni),
 * halaman pemilih, mode produk (salinan per produk) dan mode
 * batch penerimaan, format barcode per produk, pagar izin.
 */
class BarcodeLabelTest extends TestCase
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
    }

    public function test_ean13_check_digit_and_validation(): void
    {
        // Vektor baku: 590123412345 → digit pemeriksa 7.
        $this->assertSame(7, Barcode::ean13CheckDigit('590123412345'));
        $this->assertTrue(Barcode::isValidEan13('5901234123457'));
        $this->assertFalse(Barcode::isValidEan13('5901234123458'));
        $this->assertSame('5901234123457', Barcode::normalizeEan13('590123412345'));
        $svg = Barcode::ean13Svg('5901234123457');
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('width="226"', $svg); // 113 modul × 2
    }

    public function test_code128_structure_and_checksum(): void
    {
        foreach (Barcode::code128Patterns() as $i => $pattern) {
            $sum = array_sum(str_split($pattern));
            $this->assertSame($i === 106 ? 13 : 11, $sum, "Pola Code 128 #{$i} harus berjumlah ".($i === 106 ? 13 : 11).' modul.');
            $this->assertSame($i === 106 ? 7 : 6, strlen($pattern));
        }
        // "ABC": A=33, B=34, C=35 → checksum (104 + 1×33 + 2×34 + 3×35) mod 103 = 310 mod 103 = 1.
        $this->assertSame([104, 33, 34, 35, 1, 106], Barcode::code128Symbols('ABC'));
        $this->assertStringContainsString('<svg', Barcode::code128Svg('SKU-001'));
    }

    public function test_label_print_by_products_uses_ean13_when_barcode_numeric(): void
    {
        $ean = Product::factory()->create([
            'sku' => 'FR-EAN', 'name' => 'Frame EAN', 'barcode' => '5901234123457', 'sale_price' => '150000',
        ]);
        $plain = Product::factory()->create([
            'sku' => 'FR-PLAIN', 'name' => 'Frame Polos', 'sale_price' => '200000',
        ]);

        $response = $this->actingAs($this->owner)->post(route('labels.print'), [
            'mode' => 'products',
            'selected' => [$ean->id, $plain->id],
            'copies' => [$ean->id => 2, $plain->id => 1],
        ]);
        $response->assertOk();
        $html = $response->getContent();
        $this->assertSame(3, substr_count($html, 'class="label"'));
        $this->assertStringContainsString('5901234123457', $html);
        $this->assertStringContainsString('FR-PLAIN', $html);
        $this->assertSame(3, substr_count($html, '<svg'));
    }

    public function test_label_print_by_receipt_batch(): void
    {
        $supplier = Supplier::create(['name' => 'PT Label', 'is_active' => true]);
        $product = Product::factory()->create(['sku' => 'FR-BATCH', 'name' => 'Frame Batch', 'sale_price' => '100000']);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $product->id, 'qty' => 3, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($this->owner, $po);
        $receipt = app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 3, 'unit_cost_actual' => '50000'],
        ]);

        $response = $this->actingAs($this->owner)->post(route('labels.print'), [
            'mode' => 'receipt', 'receipt_id' => $receipt->id, 'multiplier' => 2,
        ]);
        $response->assertOk();
        $this->assertSame(6, substr_count($response->getContent(), 'class="label"'));
    }

    public function test_label_pages_require_stock_view(): void
    {
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->get(route('labels.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('labels.index'))->assertOk();
    }
}
