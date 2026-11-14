<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SoftlensDetail;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A1 — katalog dua tingkat persis Artesis: master → detail
 * berkekuatan sendiri + gambar. (Pengganti uji ADR-015:
 * ProductParent menjadi master Product.)
 */
class ProductTwoLevelTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->gudang = User::factory()->create();
        $this->gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
    }

    public function test_master_crud_and_detail_grouping_over_http(): void
    {
        // A2: master lahir dari formulir detail yang sama (tidak
        // ada lagi permukaan induk terpisah).
        $this->actingAs($this->owner)->post('/katalog/lens', [
            'sku' => 'LNS-G0', 'name' => 'Lensa Progresif Premium', 'brand' => 'Essilor',
            'sph' => -2, 'add' => 1.5, 'cost_price' => 100, 'sale_price' => 200,
        ])->assertRedirect();

        $master = Product::where('name', 'Lensa Progresif Premium')->firstOrFail();

        $this->actingAs($this->owner)->post('/katalog/lens', [
            'parent_id' => $master->id, 'sku' => 'LNS-G1', 'name' => 'Lensa Progresif Premium',
            'brand' => 'Essilor', 'sph' => -2, 'add' => 1.5, 'cost_price' => 100, 'sale_price' => 200,
        ])->assertRedirect();

        $detail = LensDetail::where('sku', 'LNS-G1')->firstOrFail();
        $this->assertSame($master->id, $detail->product_id);

        // Satu halaman kelola menampilkan master beserta SKU detailnya.
        $this->actingAs($this->owner)->get('/katalog/lens')
            ->assertOk()->assertSee('LNS-G1');

        // Nama master diperbarui lewat formulir ubah detail (A2).
        $this->actingAs($this->owner)->put("/produk/lens/{$detail->id}", [
            'type' => 'lens', 'sku' => 'LNS-G1', 'name' => 'Lensa Progresif Premium V2', 'brand' => 'Essilor',
            'cost_price' => 100, 'sale_price' => 200,
        ])->assertRedirect();
        $this->assertSame('Lensa Progresif Premium V2', $master->refresh()->name);

        $this->actingAs($this->owner)->post("/produk-induk/{$master->id}/alih-aktif")->assertRedirect();
        $this->assertFalse($master->refresh()->is_active);
    }

    public function test_detail_without_master_auto_groups_and_type_mismatch_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/katalog/frame', [
            'sku' => 'FRM-AUTO', 'name' => 'Frame Otomatis', 'brand' => 'MerkA',
            'cost_price' => 10, 'sale_price' => 20,
        ])->assertRedirect();

        $master = Product::where('name', 'Frame Otomatis')->firstOrFail();
        $this->assertSame('frame', $master->typeKey());

        $lensMaster = Product::factory()->forType('lens')->create();
        $this->actingAs($this->owner)->post('/katalog/frame', [
            'parent_id' => $lensMaster->id, 'sku' => 'FRM-SALAH', 'name' => 'Salah Tipe',
            'cost_price' => 10, 'sale_price' => 20,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_softlens_detail_keeps_power_color_and_expiry_tracking(): void
    {
        $detail = SoftlensDetail::factory()->create([
            'power' => -3.25, 'color' => 'Cokelat', 'expiry_tracked' => true,
        ]);

        $this->assertSame('-3.25', (string) $detail->power);
        $this->assertSame('Cokelat', $detail->color);
        $this->assertTrue($detail->expiry_tracked);
        $this->assertSame('Power -3.25 · Cokelat', $detail->detailLabel());
    }

    public function test_detail_image_upload_and_removal(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post('/katalog/frame', [
            'sku' => 'FRM-IMG', 'name' => 'Frame Gambar', 'cost_price' => 10, 'sale_price' => 20,
            'image' => UploadedFile::fake()->image('frame.jpg'),
        ])->assertRedirect();

        $detail = FrameDetail::where('sku', 'FRM-IMG')->firstOrFail();
        $this->assertNotNull($detail->image_path);
        Storage::disk('public')->assertExists($detail->image_path);

        $oldPath = $detail->image_path;
        $this->actingAs($this->owner)->put("/produk/frame/{$detail->id}", [
            'type' => 'frame', 'sku' => 'FRM-IMG', 'name' => 'Frame Gambar',
            'cost_price' => 10, 'sale_price' => 20, 'image_remove' => '1',
        ])->assertRedirect();

        $this->assertNull($detail->refresh()->image_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_gudang_cannot_create_master_or_detail(): void
    {
        $this->actingAs($this->gudang)->post('/katalog/lens', [
            'sku' => 'LNS-DITOLAK', 'name' => 'Ditolak', 'cost_price' => 1, 'sale_price' => 2,
        ])->assertForbidden();
    }
}
