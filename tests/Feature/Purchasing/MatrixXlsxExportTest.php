<?php

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

/**
 * Ekspor XLSX matriks PO: grid rentang lengkap induk lensa
 * menjadi workbook — header SPH\CYL + stok per sel.
 */
class MatrixXlsxExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_matrix_export_downloads_grid_workbook(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $parent = ProductParent::create([
            'name' => 'FSV 1.56 Bluecromic', 'brand' => 'Essilor', 'type' => 'lens', 'is_active' => true,
        ]);
        $variant = Product::create([
            'parent_id' => $parent->id, 'sku' => 'LN-A', 'type' => 'lens',
            'name' => 'FSV 1.56 Bluecromic', 'brand' => 'Essilor',
            'sph' => -1.00, 'cyl' => 0.00, 'cost_price' => '50000', 'sale_price' => '100000', 'is_active' => true,
        ]);
        app(InventoryService::class)->recordMovement($owner, $variant, $branch, 'initial_stock', 6, 'test', 1);

        $response = $this->actingAs($owner)->get(route('po.matrix-export', [
            'matriks_induk' => $parent->id, 'matriks_cabang' => $branch->id,
        ]));
        $response->assertOk();
        $bytes = $response->streamedContent();
        $this->assertStringStartsWith('PK', $bytes);

        $tmp = tempnam(sys_get_temp_dir(), 'matrix-xlsx-');
        file_put_contents($tmp, $bytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp));
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('SPH \\ CYL', $sheet);
        $this->assertStringContainsString('-1.00', $sheet);
        $this->assertStringContainsString('<v>6</v>', $sheet);
    }
}
