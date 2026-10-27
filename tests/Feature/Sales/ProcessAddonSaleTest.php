<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ADD-ON PROSES (koreksi owner 2026-10-27, paritas Artesis PR #20):
 * aksesoris yang merupakan pekerjaan menyertai lensa RX (coating dsb.)
 * BUKAN barang ready stok — ditandai is_process_item pada master produk.
 * Add-on proses: dibebaskan dari pagar stok ketat aksesoris, masuk PR
 * otomatis bersama item RX-nya (qty penuh, pola RX), dan dapat
 * dibuatkan PO. Aksesoris biasa TIDAK berubah: stok kurang = penjualan
 * ditolak. PR campuran Lentera memang sah (convertToPo tanpa gate
 * campuran) sehingga tidak ada pemisahan PR di sini.
 */
class ProcessAddonSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    private function saleWith(array $items): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'prescription_id' => Prescription::factory()->create(['customer_id' => $this->customer->id])->id,
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-27', 'tax_mode' => 'inclusive',
        ], $items);
    }

    private function processAddon(array $attrs = []): Product
    {
        return Product::factory()->ofType('accessory')->create(array_merge([
            'sale_price' => '150000', 'cost_price' => '75000', 'is_process_item' => true,
        ], $attrs));
    }

    public function test_rx_sale_with_process_addon_creates_auto_pr_containing_both(): void
    {
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);
        $addon = $this->processAddon();

        $sale = $this->saleWith([
            ['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
            ['product_id' => $addon->id, 'qty' => 2],
        ]);

        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('submitted', $pr->status);
        $items = $pr->items()->get()->keyBy('product_id');
        $this->assertSame(1, (int) $items[$rx->id]->qty);
        $this->assertSame(2, (int) $items[$addon->id]->qty);
        // Backorder tercatat seperti tipe lunak lain (saldo boleh negatif).
        $this->assertSame(-2, app(InventoryService::class)->currentStock($addon->id, $this->branch->id));
    }

    public function test_process_addon_only_sale_flows_through_pr_to_po(): void
    {
        $addon = $this->processAddon();
        $supplier = Supplier::create(['name' => 'PT Lab Uji', 'is_active' => true]);

        $sale = $this->saleWith([['product_id' => $addon->id, 'qty' => 3]]);
        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame(3, (int) $pr->items()->firstOrFail()->qty);

        $prs = app(PurchaseRequestService::class);
        $prs->approve($this->owner, $pr);
        $po = $prs->convertToPo($this->owner, [$pr->refresh()], $supplier->id);
        $this->assertSame($addon->id, (int) $po->items()->firstOrFail()->product_id);
        $this->assertSame('converted', $pr->refresh()->status);
    }

    public function test_plain_accessory_without_stock_is_still_rejected(): void
    {
        $plain = Product::factory()->ofType('accessory')->create(['sale_price' => '25000']);

        try {
            $this->saleWith([['product_id' => $plain->id, 'qty' => 1]]);
            $this->fail('Aksesoris biasa tanpa stok harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('qty', $e->errors());
        }
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, PurchaseRequest::count());
    }

    public function test_frame_shortage_is_still_rejected(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 1, 'test_seed', 1);

        $this->expectException(ValidationException::class);
        $this->saleWith([['product_id' => $frame->id, 'qty' => 2]]);
    }

    public function test_process_flag_only_applies_to_accessories_via_service(): void
    {
        $service = app(ProductService::class);
        $base = ['sku' => 'SKU-UNIQ', 'name' => 'Produk Uji', 'cost_price' => '1000', 'sale_price' => '2000', 'is_process_item' => true];

        $frame = $service->create($this->owner, [...$base, 'sku' => 'FRM-FLAG', 'type' => 'frame']);
        $this->assertFalse($frame->is_process_item);

        $addon = $service->create($this->owner, [...$base, 'sku' => 'ACC-FLAG', 'type' => 'accessory']);
        $this->assertTrue($addon->is_process_item);

        $updated = $service->update($this->owner, $addon, ['type' => 'accessory', 'is_process_item' => false]);
        $this->assertFalse($updated->is_process_item);
    }

    public function test_process_flag_saved_via_http_form_and_badge_shown_on_index(): void
    {
        $response = $this->actingAs($this->owner)->post(route('products.store', ['type' => 'accessory']), [
            'sku' => 'ACC-HTTP-1', 'name' => 'Coating Uji', 'cost_price' => '75000', 'sale_price' => '150000',
            'is_process_item' => '1',
        ]);
        $response->assertRedirect();
        $product = Product::where('sku', 'ACC-HTTP-1')->firstOrFail();
        $this->assertTrue($product->is_process_item);

        $index = $this->actingAs($this->owner)->get(route('products.index', ['type' => 'accessory']));
        $index->assertOk();
        $index->assertSee('Proses');
    }
}
