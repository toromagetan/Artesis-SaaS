<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Catalog\Services\LensMatrixService;
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
 * Matriks stok lensa model Artesis sebagai alat bantu PO & opname
 * — bukan menu tersendiri (keputusan owner 2026-10-19): sumbu
 * rentang lengkap langkah 0,25; SV SPH×CYL; progresif SPH×ADD per
 * sisi mata; bifokal SPH×ADD; sel kosong dapat melahirkan varian.
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
            $variant->productAttributes()->create(['key' => 'index', 'value' => '1.56']);
            $this->variants[$sku] = $variant;
            if ($stock > 0) {
                $inventory->recordMovement($this->owner, $variant, $this->branch, 'initial_stock', $stock, 'test', 1);
            }
        }
    }

    public function test_po_page_shows_full_range_sv_matrix(): void
    {
        $response = $this->actingAs($this->owner)->get('/pembelian/po/baru?matriks_induk='.$this->parent->id.'&matriks_cabang='.$this->branch->id);
        $response->assertOk()
            ->assertSee('Matriks Stok Lensa')
            // Rentang lengkap Artesis: SPH +6,00 .. -15,00, CYL 0,00 .. -4,00.
            ->assertSee('+6.00')->assertSee('-15.00')->assertSee('-4.00')
            ->assertSee('LN-A')->assertSee('LN-D')
            ->assertSee('belum ada varian')
            ->assertSee("sel['p".$this->variants['LN-A']->id."']", false);
    }

    public function test_po_store_merges_matrix_cells_into_items(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Lensa', 'is_active' => true]);

        $this->actingAs($this->owner)->post('/pembelian/po', [
            'supplier_id' => $supplier->id,
            'cabang_tujuan' => $this->branch->id,
            'items' => [],
            'matrix_parent_id' => $this->parent->id,
            'matrix_selection' => json_encode([
                'p'.$this->variants['LN-A']->id => 10,
                'p'.$this->variants['LN-B']->id => 0,
                'p'.$this->variants['LN-D']->id => 4,
            ]),
        ])->assertRedirect();

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $items = $po->items->keyBy('product_id');
        $this->assertCount(2, $items);
        $this->assertSame(10, (int) $items[$this->variants['LN-A']->id]->qty_ordered);
        $this->assertSame('50000.00', (string) $items[$this->variants['LN-A']->id]->unit_cost);
        $this->assertSame(4, (int) $items[$this->variants['LN-D']->id]->qty_ordered);
    }

    public function test_po_store_creates_variant_from_empty_cell(): void
    {
        $supplier = Supplier::create(['name' => 'Supplier Lensa', 'is_active' => true]);

        $this->actingAs($this->owner)->post('/pembelian/po', [
            'supplier_id' => $supplier->id,
            'cabang_tujuan' => $this->branch->id,
            'items' => [],
            'matrix_parent_id' => $this->parent->id,
            'matrix_selection' => json_encode(['n|-3.00|-0.50|' => 5]),
        ])->assertRedirect();

        $created = Product::where('parent_id', $this->parent->id)
            ->where('sph', '-3.00')->where('cyl', '-0.50')->first();
        $this->assertNotNull($created);
        $this->assertStringStartsWith('MX'.$this->parent->id.'-', $created->sku);
        $this->assertSame('50000.00', (string) $created->cost_price);
        $this->assertSame('1.56', $created->attributeValue('index'));

        $po = PurchaseOrder::latest('id')->firstOrFail();
        $this->assertSame(5, (int) $po->items->firstWhere('product_id', $created->id)->qty_ordered);

        // Kekuatan bukan kelipatan 0,25 ditolak.
        $this->actingAs($this->owner)->post('/pembelian/po', [
            'supplier_id' => $supplier->id,
            'cabang_tujuan' => $this->branch->id,
            'items' => [],
            'matrix_parent_id' => $this->parent->id,
            'matrix_selection' => json_encode(['n|-3.10|-0.50|' => 5]),
        ])->assertSessionHasErrors('matrix_selection');
    }

    public function test_progressive_matrix_uses_add_columns_and_eye_rows(): void
    {
        $parent = ProductParent::create([
            'name' => 'Progresif Premium 1.67', 'brand' => 'Essilor', 'type' => 'lens', 'is_active' => true,
        ]);
        $variant = Product::create([
            'parent_id' => $parent->id, 'sku' => 'PG-R1', 'type' => 'lens',
            'name' => 'Progresif Premium 1.67', 'brand' => 'Essilor',
            'sph' => -1.00, 'add' => 1.50, 'eye_side' => 'R',
            'cost_price' => '300000', 'sale_price' => '500000', 'is_active' => true,
        ]);
        $variant->productAttributes()->create(['key' => 'lens_kind', 'value' => 'Progressive']);

        $service = app(LensMatrixService::class);
        $grid = $service->gridForParent($parent, $this->branch->id);
        $this->assertSame(LensMatrixService::KIND_PROGRESSIVE, $grid['kind']);
        $this->assertSame('ADD', $grid['col_label']);
        $this->assertEquals(0.75, $grid['cols'][0] + 0);
        $this->assertEquals(3.00, $grid['cols'][array_key_last($grid['cols'])] + 0);
        $labels = array_column($grid['rows'], 'label');
        $this->assertContains('+0.00 · R', $labels);
        $this->assertContains('+0.00 · L', $labels);
        $row = collect($grid['rows'])->firstWhere('label', '-1.00 · R');
        $this->assertSame('PG-R1', $row['cells'][$service->colKey(1.50)]['product']->sku);
        $rowL = collect($grid['rows'])->firstWhere('label', '-1.00 · L');
        $this->assertNull($rowL['cells'][$service->colKey(1.50)]);
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
        $this->assertSame(1, substr_count($html, 'name="counts['.$lensItem->id.']"'));

        $this->actingAs($this->owner)->post('/stok/opname/'.$opname->id.'/hitung', [
            'counts' => [$lensItem->id => 2],
        ])->assertRedirect();
        $this->assertSame(2, (int) $lensItem->fresh()->counted_qty);
        $this->assertSame(-1, (int) $lensItem->fresh()->difference);
    }
}
