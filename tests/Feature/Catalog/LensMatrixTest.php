<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\OpnameService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriks stok lensa SPH×CYL sebagai alat bantu PO & opname —
 * bukan menu tersendiri (keputusan owner 2026-10-19).
 */
class LensMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private ProductParent $parent;

    /** @var array<string, Product> */
    private array $variants = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->parent = ProductParent::create([
            'name' => 'FSV 1.56 Bluecromic', 'brand' => 'Essilor', 'type' => 'lens', 'is_active' => true,
        ]);
        $grid = [
            'LN-A' => [-1.00, 0.00, 6],
            'LN-B' => [-1.00, -0.50, 3],
            'LN-C' => [-2.00, 0.00, 0],
            'LN-D' => [-2.00, -0.50, 8],
        ];
        $inventory = app(InventoryService::class);
        foreach ($grid as $sku => [$sph, $cyl, $stock]) {
            $variant = Product::create([
                'parent_id' => $this->parent->id, 'sku' => $sku, 'type' => 'lens',
                'name' => 'FSV 1.56 Bluecromic', 'brand' => 'Essilor',
                'sph' => $sph, 'cyl' => $cyl,
                'cost_price' => '50000', 'sale_price' => '90000', 'is_active' => true,
            ]);
            $this->variants[$sku] = $variant;
            if ($stock > 0) {
                $inventory->recordMovement($this->owner, $variant, $this->branch, 'initial_stock', $stock, 'test', 1);
            }
        }
    }

    public function test_po_page_shows_matrix_grid_with_stock(): void
    {
        $response = $this->actingAs($this->owner)->get('/pembelian/po/baru?matriks_induk='.$this->parent->id.'&matriks_cabang='.$this->branch->id);
        $response->assertOk()
            ->assertSee('Matriks Stok Lensa')
            ->assertSee('LN-A')->assertSee('LN-D')
            ->assertSee('-1.00')->assertSee('-2.00')->assertSee('-0.50')
            ->assertSee('name="matrix_qty['.$this->variants['LN-A']->id.']"', false);
    }

    public function test_po_store_merges_matrix_cells_into_items(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Lensa', 'is_active' => true]);

        $this->actingAs($this->owner)->post('/pembelian/po', [
            'supplier_id' => $supplier->id,
            'cabang_tujuan' => $this->branch->id,
            'items' => [],
            'matrix_qty' => [
                $this->variants['LN-A']->id => 10,
                $this->variants['LN-B']->id => 0,
                $this->variants['LN-D']->id => 4,
            ],
        ])->assertRedirect();

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $items = $po->items->keyBy('product_id');
        $this->assertCount(2, $items);
        $this->assertSame(10, (int) $items[$this->variants['LN-A']->id]->qty_ordered);
        $this->assertSame('50000.00', (string) $items[$this->variants['LN-A']->id]->unit_cost);
        $this->assertSame(4, (int) $items[$this->variants['LN-D']->id]->qty_ordered);
    }

    public function test_opname_count_page_groups_lens_items_into_matrix_and_saves_counts(): void
    {
        $frame = Product::create([
            'sku' => 'FR-A', 'type' => 'frame', 'name' => 'Frame Uji',
            'cost_price' => '100000', 'sale_price' => '200000', 'is_active' => true,
        ]);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 7, 'test', 1);

        $service = app(OpnameService::class);
        $opname = $service->schedule($this->owner, $this->branch->id, now()->toDateString());
        $service->startCounting($this->owner, $opname);
        $opname = $opname->fresh();
        $lensItem = $opname->items->firstWhere('product_id', $this->variants['LN-B']->id);
        $this->assertNotNull($lensItem);
        $this->assertSame(3, (int) $lensItem->system_qty);

        $html = $this->actingAs($this->owner)->get('/stok/opname/'.$opname->id)
            ->assertOk()
            ->assertSee('Matriks Stok Lensa')
            ->assertSee('name="counts['.$lensItem->id.']"', false)
            ->getContent();
        // Input hitung item lensa hanya muncul sekali (di matriks,
        // bukan juga di tabel datar).
        $this->assertSame(1, substr_count($html, 'name="counts['.$lensItem->id.']"'));

        $this->actingAs($this->owner)->post('/stok/opname/'.$opname->id.'/hitung', [
            'counts' => [$lensItem->id => 2],
        ])->assertRedirect();
        $this->assertSame(2, (int) $lensItem->fresh()->counted_qty);
        $this->assertSame(-1, (int) $lensItem->fresh()->difference);
    }
}
