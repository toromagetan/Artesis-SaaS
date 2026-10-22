<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
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
 * M.4 Inventory inti: buku besar gerakan satu-satunya penulis service,
 * integrasi stok penjualan/void/retur, aturan negatif per tipe.
 */
class InventorySalesIntegrationTest extends TestCase
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

    private function inventory(): InventoryService
    {
        return app(InventoryService::class);
    }

    private function seedStock(Product $product, int $qty): void
    {
        $this->inventory()->recordMovement(
            $this->owner, $product, $this->branch, 'initial_stock', $qty, 'test_seed', 1,
        );
    }

    public function test_sale_consumes_stock_and_records_balance_chain(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        $this->seedStock($frame, 5);

        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $frame->id, 'qty' => 2],
        ]);

        $this->assertSame(3, $this->inventory()->currentStock($frame->id, $this->branch->id));
        $movements = StockMovement::orderBy('id')->get();
        $this->assertSame([5, 3], $movements->pluck('balance_after')->all());
        $this->assertSame('sale', $movements->last()->reason);
        $this->assertSame(-2, $movements->last()->qty);
    }

    public function test_frame_sale_beyond_stock_is_rejected_and_sale_rolls_back(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        $this->seedStock($frame, 1);

        try {
            app(SalesService::class)->createSale($this->owner, [
                'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
            ], [
                ['product_id' => $frame->id, 'qty' => 2],
            ]);
            $this->fail('Stok frame tidak cukup harus ditolak');
        } catch (ValidationException) {
            // Penjualan batal total: tidak ada sale, tidak ada gerakan sale.
            $this->assertSame(0, Sale::count());
            $this->assertSame(1, StockMovement::count()); // hanya initial_stock
            $this->assertSame(1, $this->inventory()->currentStock($frame->id, $this->branch->id));
        }
    }

    public function test_lens_may_go_negative_as_backorder(): void
    {
        $lens = Product::factory()->ofType('rx')->create(['sale_price' => '100000']);

        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
            // ADR-019: item RX mewajibkan resep pelanggan yang sama.
            'prescription_id' => \App\Modules\Clinical\Models\Prescription::factory()->create(['customer_id' => $this->customer->id])->id,
        ], [
            ['product_id' => $lens->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);

        $this->assertSame(-1, $this->inventory()->currentStock($lens->id, $this->branch->id));
    }

    public function test_void_restores_stock_with_sale_void_movement(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        $this->seedStock($frame, 4);

        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $frame->id, 'qty' => 3],
        ]);
        $this->assertSame(1, $this->inventory()->currentStock($frame->id, $this->branch->id));

        app(SalesService::class)->voidSale($this->owner, $sale, 'Batal');
        $this->assertSame(4, $this->inventory()->currentStock($frame->id, $this->branch->id));
        $this->assertSame('sale_void', StockMovement::orderByDesc('id')->first()->reason);
    }

    public function test_resale_return_restocks_but_damaged_does_not(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        $this->seedStock($frame, 2);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $frame->id, 'qty' => 2],
        ]);
        $item = $sale->items->first();
        $this->assertSame(0, $this->inventory()->currentStock($frame->id, $this->branch->id));

        app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'damaged'],
        ], 'Retur campuran', 'cash');

        // Hanya unit resale yang kembali ke stok.
        $this->assertSame(1, $this->inventory()->currentStock($frame->id, $this->branch->id));
        $this->assertSame(1, StockMovement::where('reason', 'sale_return')->count());
    }

    public function test_movement_rows_are_immutable_by_design(): void
    {
        $frame = Product::factory()->ofType('frame')->create();
        $movement = $this->inventory()->recordMovement(
            $this->owner, $frame, $this->branch, 'initial_stock', 7, 'test_seed', 1,
        );
        $this->assertNull($movement->updated_at);
        $this->assertArrayNotHasKey('updated_at', $movement->getAttributes());
    }

    public function test_stock_pages_render_with_balances(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['name' => 'Frame Stok Uji']);
        $this->seedStock($frame, 9);

        $this->actingAs($this->owner)->get('/stok')
            ->assertOk()
            ->assertSee('Frame Stok Uji')
            ->assertSee('9');
        $this->actingAs($this->owner)->get('/stok/pergerakan')
            ->assertOk()
            ->assertSee('Stok Awal');
    }
}
