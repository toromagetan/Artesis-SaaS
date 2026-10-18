<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * ADR-015 — katalog dua tingkat: induk → varian berkekuatan
 * sendiri + gambar, menyamai model produk Artesis.
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

    public function test_parent_crud_and_variant_grouping_over_http(): void
    {
        $this->actingAs($this->owner)->post('/katalog/lens/induk', [
            'name' => 'Lensa Progresif Premium', 'brand' => 'Essilor',
        ])->assertRedirect();
        $parent = ProductParent::where('name', 'Lensa Progresif Premium')->firstOrFail();
        $this->assertSame('lens', $parent->type);

        // Dua varian berkekuatan berbeda di bawah induk yang sama.
        foreach ([['LN-P1', '-2.00', '-0.50'], ['LN-P2', '-2.00', '-1.00']] as [$sku, $sph, $cyl]) {
            $this->actingAs($this->owner)->post('/katalog/lens', [
                'parent_id' => $parent->id, 'sku' => $sku, 'name' => 'Lensa Progresif Premium',
                'brand' => 'Essilor', 'cost_price' => '620000', 'sale_price' => '1150000',
                'sph' => $sph, 'cyl' => $cyl, 'add' => '2.00', 'eye_side' => 'R',
            ])->assertRedirect();
        }
        $this->assertSame(2, $parent->variants()->count());
        $variant = Product::where('sku', 'LN-P1')->firstOrFail();
        $this->assertSame('-2.00', (string) $variant->sph);
        $this->assertStringContainsString('SPH -2.00', $variant->detailLabel());
        $this->assertStringContainsString('CYL -0.50', $variant->detailLabel());

        // Detail induk menampilkan kedua varian; daftar katalog mengelompokkan.
        $this->actingAs($this->owner)->get('/produk-induk/'.$parent->id)
            ->assertOk()->assertSee('LN-P1')->assertSee('LN-P2');
        $this->actingAs($this->owner)->get('/katalog/lens')
            ->assertOk()->assertSee('Lensa Progresif Premium')->assertSee('LN-P1');

        // Ubah induk.
        $this->actingAs($this->owner)->put('/produk-induk/'.$parent->id, [
            'name' => 'Lensa Progresif Premium V2', 'brand' => 'Essilor',
        ])->assertRedirect();
        $this->assertSame('Lensa Progresif Premium V2', $parent->fresh()->name);
    }

    public function test_variant_without_parent_auto_groups_and_type_mismatch_is_rejected(): void
    {
        $this->actingAs($this->owner)->post('/katalog/frame', [
            'sku' => 'FR-AUTO', 'name' => 'Frame Otomatis', 'brand' => 'RayBan',
            'cost_price' => '100000', 'sale_price' => '200000',
        ])->assertRedirect();
        $product = Product::where('sku', 'FR-AUTO')->firstOrFail();
        $this->assertNotNull($product->parent_id);
        $this->assertSame('Frame Otomatis', $product->parent->name);

        $lensParent = ProductParent::create(['name' => 'Induk Lensa', 'type' => 'lens', 'is_active' => true]);
        $this->actingAs($this->owner)->post('/katalog/frame', [
            'parent_id' => $lensParent->id, 'sku' => 'FR-SALAH', 'name' => 'Frame Salah Induk',
            'cost_price' => '100000', 'sale_price' => '200000',
        ])->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('products', ['sku' => 'FR-SALAH']);
    }

    public function test_softlens_variant_keeps_power_color_and_expiry_tracking(): void
    {
        $this->actingAs($this->owner)->post('/katalog/softlens', [
            'sku' => 'SL-P1', 'name' => 'Softlens Bulanan Clear', 'color' => 'Clear',
            'cost_price' => '60000', 'sale_price' => '120000', 'power' => '-3.25',
            'attributes' => ['wear_period' => '1 bulan', 'expiry_tracked' => '1'],
        ])->assertRedirect();

        $product = Product::where('sku', 'SL-P1')->firstOrFail();
        $this->assertSame('-3.25', (string) $product->power);
        $this->assertSame('Clear', $product->color);
        $this->assertSame('1', $product->attributeValue('expiry_tracked'));
        $this->assertStringContainsString('Power -3.25', $product->detailLabel());
    }

    public function test_variant_image_upload_and_removal(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)->post('/katalog/frame', [
            'sku' => 'FR-IMG', 'name' => 'Frame Bergambar',
            'cost_price' => '100000', 'sale_price' => '200000',
            'image' => UploadedFile::fake()->image('frame.jpg', 400, 300),
        ])->assertRedirect();

        $product = Product::where('sku', 'FR-IMG')->firstOrFail();
        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertNotNull($product->imageUrl());

        $path = $product->image_path;
        $this->actingAs($this->owner)->put('/produk/'.$product->id, [
            'sku' => 'FR-IMG', 'name' => 'Frame Bergambar', 'type' => 'frame',
            'cost_price' => '100000', 'sale_price' => '200000', 'image_remove' => '1',
        ])->assertRedirect();
        $this->assertNull($product->fresh()->image_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_gudang_cannot_create_parent_or_variant(): void
    {
        $this->actingAs($this->gudang)->post('/katalog/frame/induk', ['name' => 'X'])->assertForbidden();
        $this->actingAs($this->gudang)->post('/katalog/frame', [
            'sku' => 'FR-GD', 'name' => 'Frame Gudang', 'cost_price' => '1', 'sale_price' => '2',
        ])->assertForbidden();
    }
}
