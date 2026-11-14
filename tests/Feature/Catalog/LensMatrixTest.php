<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\LensMatrixService;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A1 — matriks stok lensa di atas LensDetail (persis Artesis):
 * grid rentang lengkap per jenis, saringan index/coating, saldo
 * dari gerakan morph. Integrasi matriks ke halaman PO & opname
 * diuji pada modul A1.2/A1.4 (alurnya dirujuk-ulang ke detail).
 */
class LensMatrixTest extends TestCase
{
    use RefreshDatabase;

    private LensMatrixService $matrix;

    private Product $master;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);
        $this->matrix = app(LensMatrixService::class);
        $this->master = Product::factory()->forType('lens')->create(['name' => 'Lensa Matriks']);
        $this->branch = Branch::factory()->create();
    }

    private function lens(array $attrs): LensDetail
    {
        return LensDetail::factory()->create(['product_id' => $this->master->id, ...$attrs]);
    }

    public function test_sv_grid_is_full_range_with_cells_for_existing_details(): void
    {
        $a = $this->lens(['sku' => 'M-A', 'sph' => -1.00, 'cyl' => -0.50]);
        $this->lens(['sku' => 'M-B', 'sph' => 2.00, 'cyl' => 0.00]);
        $userId = User::where('username', 'owner')->value('id');
        DB::table('stock_movements')->insert([
            'detail_id' => $a->id, 'detail_type' => 'lens', 'branch_id' => $this->branch->id,
            'reason' => 'initial_stock', 'qty' => 7, 'balance_after' => 7,
            'reference_type' => 'test', 'reference_id' => 1, 'created_by' => $userId,
        ]);

        $grid = $this->matrix->gridForParent($this->master, $this->branch->id);

        $this->assertSame(LensMatrixService::KIND_SV, $grid['kind']);
        $this->assertSame('CYL', $grid['col_label']);
        $this->assertCount(85, $grid['rows']); // SPH +6,00..-15,00 langkah 0,25
        $this->assertCount(17, $grid['cols']); // CYL 0,00..-4,00

        $row = collect($grid['rows'])->firstWhere('label', '-1.00');
        $cell = $row['cells'][$this->matrix->colKey(-0.50)];
        $this->assertSame($a->id, $cell['product']->id);
        $this->assertSame(7, $cell['stock']);

        $emptyRow = collect($grid['rows'])->firstWhere('label', '-3.25');
        $this->assertNull($emptyRow['cells'][$this->matrix->colKey(-0.50)]);
    }

    public function test_progressive_grid_uses_add_columns_and_eye_rows(): void
    {
        $this->lens(['sku' => 'M-P', 'type' => 'Progresif', 'sph' => 1.00, 'add' => 2.00, 'eye_side' => 'R']);

        $this->assertSame(LensMatrixService::KIND_PROGRESSIVE, $this->matrix->kindForParent($this->master));

        $grid = $this->matrix->gridForParent($this->master, $this->branch->id);
        $this->assertSame('ADD', $grid['col_label']);
        $labels = collect($grid['rows'])->pluck('label');
        $this->assertTrue($labels->contains('+1.00 · R'));
        $this->assertTrue($labels->contains('+1.00 · L'));
    }

    public function test_filter_options_come_from_columns_and_attributes(): void
    {
        $d = $this->lens(['sku' => 'M-F', 'sph' => -0.50, 'index' => '1.67']);
        $d->detailAttributes()->create(['key' => 'coating', 'value' => 'bluecut']);
        $this->lens(['sku' => 'M-G', 'sph' => -0.75, 'index' => '1.56']);

        $options = $this->matrix->filterOptions($this->master);
        $this->assertSame(['1.56', '1.67'], $options['indexes']);
        $this->assertSame(['bluecut'], $options['coatings']);

        $grid = $this->matrix->gridForParent($this->master, $this->branch->id, index: '1.67');
        $row = collect($grid['rows'])->firstWhere('label', '-0.75');
        $this->assertNull($row['cells'][$this->matrix->colKey(0.00)]);
    }

    public function test_matrix_parents_lists_only_lens_masters_with_powered_details(): void
    {
        $this->lens(['sku' => 'M-H', 'sph' => -1.25]);
        $empty = Product::factory()->forType('lens')->create(['name' => 'Tanpa Kekuatan']);
        LensDetail::factory()->create(['product_id' => $empty->id, 'sph' => null]);

        $parents = $this->matrix->matrixParents();
        $this->assertTrue($parents->contains($this->master));
        $this->assertFalse($parents->contains($empty));
    }

    public function test_snapshot_groups_collect_lens_items_by_master(): void
    {
        $detail = $this->lens(['sku' => 'M-S', 'sph' => -2.00, 'cyl' => -1.00]);
        $item = (object) ['detail' => $detail];
        $other = (object) ['detail' => FrameDetail::factory()->create()];

        $groups = $this->matrix->snapshotGroups([$item, $other]);

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['parent']->is($this->master));
        $this->assertSame('CYL', $groups[0]['col_label']);
    }
}
