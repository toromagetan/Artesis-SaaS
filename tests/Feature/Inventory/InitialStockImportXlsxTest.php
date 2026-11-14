<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InitialStockImportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Support\XlsxWriter;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * A5 — impor awal yang bisa dikerjakan owner sendiri dari Excel:
 * unggahan XLSX asli (bukan CSV yang diganti nama) mengalir lewat
 * pratinjau → komitmen yang sama dengan jalur CSV.
 */
class InitialStockImportXlsxTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private FrameDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::factory()->create(['name' => 'Cabang A5']);
        $master = Product::factory()->forType('frame')->create(['name' => 'Master A5']);
        $this->detail = FrameDetail::factory()->create(['product_id' => $master->id, 'sku' => 'FR-A5-1']);
    }

    public function test_xlsx_upload_previews_and_commits(): void
    {
        $bytes = XlsxWriter::make([['title' => 'Template Stok Awal', 'rows' => [
            ['sku', 'cabang', 'qty'],
            ['FR-A5-1', $this->branch->id, 9],
        ]]]);
        $file = UploadedFile::fake()->createWithContent('stok-awal.xlsx', $bytes);

        $response = $this->actingAs($this->owner)->post('/stok/impor/pratinjau', ['file' => $file]);
        $response->assertOk()->assertSee('FR-A5-1');

        $service = app(InitialStockImportService::class);
        $preview = $service->previewUpload($file, $this->owner);
        $this->assertSame([], $preview['errors']);
        $service->commit($this->owner, $preview['token']);

        $this->assertSame(9, app(InventoryService::class)->currentStock('frame', $this->detail->id, $this->branch->id));
    }

    public function test_xlsx_with_unknown_sku_reports_row_error(): void
    {
        $bytes = XlsxWriter::make([['title' => 'Sheet1', 'rows' => [
            ['sku', 'cabang', 'qty'],
            ['TIDAK-ADA', $this->branch->id, 3],
        ]]]);
        $preview = app(InitialStockImportService::class)
            ->previewUpload(UploadedFile::fake()->createWithContent('x.xlsx', $bytes), $this->owner);

        $this->assertSame([], $preview['valid']);
        $this->assertCount(1, $preview['errors']);
    }
}
