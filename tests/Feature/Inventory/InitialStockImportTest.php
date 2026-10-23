<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InitialStockImportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.8 Impor stok awal (D.8 + PRD 7.5 + uji G.1.4): policy Owner
 * non-platform (izin saja tidak cukup), pratinjau validasi penuh,
 * komitmen satu transaksi hanya dari pratinjau bersih, dan cabang
 * yang sudah diimpor tidak dapat diimpor ulang.
 */
class InitialStockImportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Product $frame;

    private Product $lens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->frame = Product::factory()->ofType('frame')->create(['sku' => 'FR-IMP-1', 'cost_price' => '100000']);
        $this->lens = Product::factory()->ofType('lens')->create(['sku' => 'LN-IMP-1', 'cost_price' => '50000']);
    }

    private function service(): InitialStockImportService
    {
        return app(InitialStockImportService::class);
    }

    public function test_preview_validation_and_clean_commit(): void
    {
        $branchB = Branch::create(['name' => 'Cabang Impor']);
        $csv = implode("\n", [
            'sku,cabang,qty',
            'FR-IMP-1,'.$this->branch->id.',10',          // cabang via ID
            'LN-IMP-1,Cabang Impor,4',                     // cabang via Nama
            'TIDAK-ADA,'.$this->branch->id.',1',           // SKU asing
            'FR-IMP-1,'.$this->branch->id.',2',            // duplikat (SKU, cabang)
            'LN-IMP-1,'.$this->branch->id.',-3',           // qty negatif
        ]);

        $preview = $this->service()->preview($csv);
        $this->assertSame(2, count($preview['valid']));
        $this->assertSame(3, count($preview['errors']));

        // Uji G.1.4: komitmen dari pratinjau kotor → nol baris terimpor.
        try {
            $this->service()->commit($this->owner, $preview['token']);
            $this->fail('Komitmen pratinjau kotor harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, StockMovement::count());
        }

        // Pratinjau bersih → komitmen satu transaksi.
        $clean = $this->service()->preview("sku,cabang,qty\nFR-IMP-1,{$this->branch->id},10\nLN-IMP-1,{$branchB->id},4\n");
        $this->assertSame([], $clean['errors']);
        $totals = $this->service()->commit($this->owner, $clean['token']);
        $stock = app(InventoryService::class);
        $this->assertSame(10, $stock->currentStock($this->frame->id, $this->branch->id));
        $this->assertSame(4, $stock->currentStock($this->lens->id, $branchB->id));
        $this->assertSame('1000000.00', $totals[$this->branch->id]); // 10 × 100.000
        $this->assertSame('200000.00', $totals[$branchB->id]);       // 4 × 50.000
        $this->assertSame(2, StockMovement::where('reference_type', 'initial_stock_import')->count());

        // Cabang yang sama tidak dapat diimpor ulang.
        $lagi = $this->service()->preview("sku,cabang,qty\nFR-IMP-1,{$this->branch->id},5\n");
        $this->assertSame(1, count($lagi['errors'])); // branch_done pada validasi
        $this->assertSame(0, count($lagi['valid']));
    }

    public function test_policy_owner_only_permission_alone_is_not_enough(): void
    {
        // Admin yang DIBERI izin stock.import_initial secara manual
        // tetap ditolak policy (bukan Owner).
        $admin = User::factory()->create();
        $adminRole = Role::where('name', 'admin')->firstOrFail();
        $admin->roles()->sync([$adminRole->id]);
        $permission = Permission::where('name', 'stock.import_initial')->firstOrFail();
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);
        $this->assertTrue($admin->fresh()->hasPermission('stock.import_initial'));
        $this->actingAs($admin)->get('/stok/impor')->assertForbidden();

        // Aktor platform: ditolak walau global.
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get('/stok/impor')->assertForbidden();

        // Owner: lolos, template terunduh.
        $this->actingAs($this->owner)->get('/stok/impor')->assertOk();
        $response = $this->actingAs($this->owner)->get('/stok/impor/template');
        $response->assertOk();
        $this->assertStringContainsString('sku,cabang,qty', $response->getContent());
    }

    public function test_http_upload_preview_commit_flow(): void
    {
        $file = UploadedFile::fake()->createWithContent('stok.csv',
            "sku,cabang,qty\nFR-IMP-1,{$this->branch->id},7\n");
        $response = $this->actingAs($this->owner)->post('/stok/impor/pratinjau', ['file' => $file]);
        $response->assertOk()->assertSee('FR-IMP-1');

        // Ambil token dari cache lewat pratinjau service tidak langsung:
        // ulangi alur service untuk token, lalu komitmen via HTTP.
        $preview = $this->service()->preview("sku,cabang,qty\nLN-IMP-1,{$this->branch->id},3\n");
        $this->actingAs($this->owner)->post('/stok/impor/komitmen', ['token' => $preview['token']])
            ->assertRedirect(route('stock.index'));
        $this->assertSame(3, app(InventoryService::class)->currentStock($this->lens->id, $this->branch->id));
    }

    public function test_upload_rejects_non_spreadsheet_extension(): void
    {
        // Pengerasan audit 23 Okt 2026 (A5): unggahan impor wajib
        // berekstensi xlsx/csv — berkas teks biasa ditolak validasi.
        $file = UploadedFile::fake()->createWithContent('stok.txt', "sku,cabang,qty\nFR-IMP-1,{$this->branch->id},7\n");
        $this->actingAs($this->owner)->post('/stok/impor/pratinjau', ['file' => $file])
            ->assertSessionHasErrors('file');
    }
}
