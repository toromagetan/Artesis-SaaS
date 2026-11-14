<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A3 — halaman matriks stok lensa persis Artesis di atas
 * LensDetail: grid rentang lengkap per master & cabang,
 * saringan, ekspor Excel, dan pagar cabang/izin.
 */
class LensMatrixPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Product $master;

    private LensDetail $detail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);

        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::factory()->create(['name' => 'Cabang Matriks']);
        $this->master = Product::factory()->forType('lens')->create(['name' => 'Lensa Halaman Matriks']);
        $this->detail = LensDetail::factory()->create([
            'product_id' => $this->master->id, 'sku' => 'LNS-PAGE-1',
            'type' => 'SV', 'sph' => -1.00, 'cyl' => -0.50, 'eye_side' => 'Both',
        ]);
        DB::table('stock_movements')->insert([
            'detail_id' => $this->detail->id, 'detail_type' => 'lens', 'branch_id' => $this->branch->id,
            'reason' => 'initial_stock', 'qty' => 7, 'balance_after' => 7,
            'reference_type' => 'test', 'reference_id' => 1, 'created_by' => $this->owner->id,
        ]);
    }

    public function test_page_renders_full_range_grid_with_stock(): void
    {
        $this->actingAs($this->owner)->get('/stok/matriks-lensa')
            ->assertOk()
            ->assertSee('Matriks Stok Lensa')
            ->assertSee('Lensa Halaman Matriks')
            ->assertSee('LNS-PAGE-1')
            ->assertSee('-15.00');
    }

    public function test_export_downloads_xlsx(): void
    {
        $response = $this->actingAs($this->owner)
            ->get('/stok/matriks-lensa.xlsx?induk='.$this->master->id.'&cabang='.$this->branch->id);
        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_master_without_powered_details_is_not_selectable(): void
    {
        $emptyMaster = Product::factory()->forType('lens')->create(['name' => 'Master Tanpa Daya']);

        // Master tanpa detail berkekuatan tidak masuk daftar
        // matriks: halaman jatuh ke master matriks pertama.
        $this->actingAs($this->owner)->get('/stok/matriks-lensa?induk='.$emptyMaster->id)
            ->assertOk()
            ->assertSee('Lensa Halaman Matriks');
    }

    public function test_page_requires_stock_view_permission(): void
    {
        $kasir = User::create(['name' => 'Kasir', 'username' => 'kasir-matriks', 'password' => bcrypt('password'), 'is_active' => true]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $this->actingAs($kasir)->get('/stok/matriks-lensa')->assertForbidden();
    }
}
