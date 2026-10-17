<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.1 Catalog (Blueprint B.2 + D.3): lima tipe produk, atribut per tipe,
 * SKU unik, pagar izin per operasi, audit, pencarian global.
 */
class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();

        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', 'admin')->firstOrFail()->id]);

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
    }

    public function test_admin_creates_frame_product_with_attributes(): void
    {
        $this->actingAs($this->admin)->post('/katalog/frame', [
            'sku' => 'FRM-001',
            'name' => 'Frame Titanium A',
            'brand' => 'Acme',
            'color' => 'Hitam',
            'cost_price' => '250000',
            'sale_price' => '450000.5',
            'attributes' => ['eye_size' => '52', 'bridge_size' => '18', 'temple_size' => '140'],
        ])->assertRedirect();

        $product = Product::where('sku', 'FRM-001')->firstOrFail();
        $this->assertSame('frame', $product->type);
        $this->assertSame('450000.50', (string) $product->sale_price);
        $this->assertSame('52', $product->attributeValue('eye_size'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.create', 'subject_type' => 'product', 'subject_id' => $product->id]);
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        Product::factory()->create(['sku' => 'DUP-001']);

        $this->actingAs($this->admin)->post('/katalog/lens', [
            'sku' => 'DUP-001',
            'name' => 'Lensa X',
            'cost_price' => '1000',
            'sale_price' => '2000',
        ])->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('sku', 'DUP-001')->count());
    }

    public function test_unknown_attribute_key_is_rejected_by_service(): void
    {
        $this->actingAs($this->admin)->post('/katalog/accessory', [
            'sku' => 'ACC-001',
            'name' => 'Kain Microfiber',
            'cost_price' => '5000',
            'sale_price' => '15000',
            'attributes' => ['eye_size' => '52'], // atribut frame di aksesori
        ])->assertSessionHasErrors('attributes');

        $this->assertDatabaseMissing('products', ['sku' => 'ACC-001']);
    }

    public function test_softlens_expiry_tracked_normalizes_to_flag(): void
    {
        $this->actingAs($this->admin)->post('/katalog/softlens', [
            'sku' => 'SFL-001',
            'name' => 'Softlens Bulanan',
            'cost_price' => '40000',
            'sale_price' => '85000',
            'attributes' => ['power' => '-1.50', 'wear_period' => '1 bulan', 'expiry_tracked' => '1'],
        ])->assertRedirect();

        $product = Product::where('sku', 'SFL-001')->firstOrFail();
        $this->assertSame('1', $product->attributeValue('expiry_tracked'));
        $this->assertSame('-1.50', $product->attributeValue('power'));
    }

    public function test_type_lists_are_separated_and_searchable(): void
    {
        Product::factory()->ofType('frame')->create(['name' => 'Frame Unik Zebra']);
        Product::factory()->ofType('lens')->create(['name' => 'Lensa Unik Zebra']);

        $this->actingAs($this->kasir) // kasir memegang products.view (matriks C.2)
            ->get('/katalog/frame')
            ->assertOk()
            ->assertSee('Frame Unik Zebra')
            ->assertDontSee('Lensa Unik Zebra');

        $this->actingAs($this->kasir)
            ->get('/katalog/lens?q=Zebra')
            ->assertOk()
            ->assertSee('Lensa Unik Zebra');
    }

    public function test_view_only_role_cannot_create_or_edit(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->kasir)->get('/katalog/frame/buat')->assertForbidden();
        $this->actingAs($this->kasir)->post('/katalog/frame', [
            'sku' => 'X-1', 'name' => 'X', 'cost_price' => '1', 'sale_price' => '2',
        ])->assertForbidden();
        $this->actingAs($this->kasir)->get('/produk/'.$product->id.'/ubah')->assertForbidden();
        $this->actingAs($this->kasir)->post('/produk/'.$product->id.'/alih-aktif')->assertForbidden();
    }

    public function test_platform_actor_is_denied(): void
    {
        $platform = User::where('username', 'platform')->firstOrFail();

        $this->actingAs($platform)->get('/katalog/frame')->assertForbidden();
    }

    public function test_update_replaces_attributes_and_keeps_sku_unique(): void
    {
        $product = Product::factory()->ofType('lens')->create(['sku' => 'LNS-001']);
        $product->productAttributes()->create(['key' => 'index', 'value' => '1.56']);

        $this->actingAs($this->admin)->put('/produk/'.$product->id, [
            'type' => 'lens',
            'sku' => 'LNS-001',
            'name' => 'Lensa Diperbarui',
            'cost_price' => '120000',
            'sale_price' => '210000',
            'attributes' => ['index' => '1.67', 'coating' => 'Bluecut'],
        ])->assertRedirect('/produk/'.$product->id);

        $product->refresh()->load('productAttributes');
        $this->assertSame('1.67', $product->attributeValue('index'));
        $this->assertSame('Bluecut', $product->attributeValue('coating'));
        $this->assertSame('210000.00', (string) $product->sale_price);
    }

    public function test_deactivate_and_reactivate(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->admin)->post('/produk/'.$product->id.'/alih-aktif')->assertRedirect();
        $this->assertFalse($product->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'products.deactivate', 'subject_id' => $product->id]);

        $this->actingAs($this->admin)->post('/produk/'.$product->id.'/alih-aktif')->assertRedirect();
        $this->assertTrue($product->refresh()->is_active);
    }

    public function test_detail_page_shows_attributes_and_money_format(): void
    {
        $product = Product::factory()->ofType('rx')->create(['sale_price' => '1500000']);
        $product->productAttributes()->create(['key' => 'sph_min', 'value' => '-10.00']);
        $product->productAttributes()->create(['key' => 'sph_max', 'value' => '+6.00']);

        $this->actingAs($this->kasir)
            ->get('/produk/'.$product->id)
            ->assertOk()
            ->assertSee('Rp1.500.000,00')
            ->assertSee('SPH Minimum')
            ->assertSee('-10.00');
    }

    public function test_product_appears_in_global_search_for_viewer(): void
    {
        Product::factory()->create(['name' => 'Frame Langka Melati']);

        $this->actingAs($this->kasir)
            ->get('/cari?q=Melati')
            ->assertOk()
            ->assertSee('Frame Langka Melati')
            ->assertSee('Produk');
    }

    public function test_invalid_type_route_is_404(): void
    {
        $this->actingAs($this->owner)->get('/katalog/gadget')->assertNotFound();
    }
}
