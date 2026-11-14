<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\AccessoriesDetail;
use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SoftlensDetail;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A1 — katalog master + detail (persis Artesis): lima tipe detail,
 * atribut bebas per tipe, kolom berkunci (index/jenis lensa,
 * expiry_tracked), SKU unik per tenant per tipe, pagar izin,
 * kunci tipe saat detail sudah bertransaksi.
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
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
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
            'cost_price' => 100000,
            'sale_price' => 250000,
            'attributes' => ['eye_size' => '52'],
        ])->assertRedirect();

        $detail = FrameDetail::where('sku', 'FRM-001')->firstOrFail();
        $this->assertSame('Frame Titanium A', $detail->product->name);
        $this->assertSame('Acme', $detail->product->brand);
        $this->assertSame('52', $detail->attributeValue('eye_size'));
    }

    public function test_duplicate_sku_is_rejected(): void
    {
        FrameDetail::factory()->create(['sku' => 'FRM-DUP']);

        $this->actingAs($this->admin)->post('/katalog/frame', [
            'sku' => 'FRM-DUP', 'name' => 'Lain', 'cost_price' => 1, 'sale_price' => 2,
        ])->assertSessionHasErrors('sku');

        $this->assertSame(1, FrameDetail::where('sku', 'FRM-DUP')->count());
    }

    public function test_unknown_attribute_key_is_rejected_by_service(): void
    {
        $this->expectException(ValidationException::class);

        app(ProductService::class)->createDetail($this->admin, 'frame', [
            'sku' => 'FRM-X', 'name' => 'Uji', 'cost_price' => 1, 'sale_price' => 2,
        ], ['kunci_aneh' => 'nilai']);
    }

    public function test_softlens_expiry_tracked_becomes_a_column(): void
    {
        $this->actingAs($this->admin)->post('/katalog/softlens', [
            'sku' => 'SFT-001', 'name' => 'Softlens Bulanan', 'power' => -2.5,
            'cost_price' => 50000, 'sale_price' => 90000,
            'attributes' => ['wear_period' => 'monthly', 'expiry_tracked' => '1'],
        ])->assertRedirect();

        $detail = SoftlensDetail::where('sku', 'SFT-001')->firstOrFail();
        $this->assertTrue($detail->expiry_tracked);
        $this->assertSame('monthly', $detail->attributeValue('wear_period'));
        $this->assertNull($detail->attributeValue('expiry_tracked'));
    }

    public function test_lens_index_and_kind_become_columns(): void
    {
        $this->actingAs($this->admin)->post('/katalog/lens', [
            'sku' => 'LNS-001', 'name' => 'Lensa Progresif X', 'sph' => -1.5, 'add' => 2.0,
            'cost_price' => 200000, 'sale_price' => 450000,
            'attributes' => ['index' => '1.67', 'lens_kind' => 'progressive', 'coating' => 'bluecut'],
        ])->assertRedirect();

        $detail = LensDetail::where('sku', 'LNS-001')->firstOrFail();
        $this->assertSame('Progresif', $detail->type);
        $this->assertSame('1.67', $detail->index);
        $this->assertSame('bluecut', $detail->attributeValue('coating'));
        $this->assertNull($detail->attributeValue('index'));
    }

    public function test_type_lists_are_separated_and_searchable(): void
    {
        $this->actingAs($this->admin)->post('/katalog/frame', [
            'sku' => 'FRM-UNIK', 'name' => 'Frame Unik', 'cost_price' => 1, 'sale_price' => 2,
        ]);
        $this->actingAs($this->admin)->post('/katalog/lens', [
            'sku' => 'LNS-UNIK', 'name' => 'Lensa Unik', 'cost_price' => 1, 'sale_price' => 2,
        ]);

        $this->actingAs($this->admin)->get('/katalog/frame')
            ->assertOk()->assertSee('Frame Unik')->assertDontSee('Lensa Unik');
        $this->actingAs($this->admin)->get('/katalog/frame?q=FRM-UNIK')
            ->assertOk()->assertSee('Frame Unik');
    }

    public function test_view_only_role_cannot_create_or_edit(): void
    {
        $detail = FrameDetail::factory()->create();

        $this->actingAs($this->kasir)->post('/katalog/frame', [
            'sku' => 'FRM-K', 'name' => 'Kasir', 'cost_price' => 1, 'sale_price' => 2,
        ])->assertForbidden();
        $this->actingAs($this->kasir)->get("/produk/frame/{$detail->id}/ubah")->assertForbidden();
    }

    public function test_platform_actor_is_denied(): void
    {
        $platform = User::where('username', 'platform')->firstOrFail();

        $this->actingAs($platform)->get('/katalog/frame')->assertForbidden();
    }

    public function test_update_replaces_attributes_and_keeps_sku_unique(): void
    {
        $detail = FrameDetail::factory()->create(['sku' => 'FRM-U1']);
        $detail->detailAttributes()->create(['key' => 'eye_size', 'value' => '50']);
        FrameDetail::factory()->create(['sku' => 'FRM-U2']);

        $this->actingAs($this->admin)->put("/produk/frame/{$detail->id}", [
            'type' => 'frame', 'sku' => 'FRM-U2', 'name' => 'X', 'cost_price' => 1, 'sale_price' => 2,
        ])->assertSessionHasErrors('sku');

        $this->actingAs($this->admin)->put("/produk/frame/{$detail->id}", [
            'type' => 'frame', 'sku' => 'FRM-U1', 'name' => 'Nama Baru', 'cost_price' => 3, 'sale_price' => 4,
            'attributes' => ['bridge_size' => '18'],
        ])->assertRedirect(route('products.show', ['frame', $detail->id]));

        $detail->refresh();
        $this->assertSame('Nama Baru', $detail->product->name);
        $this->assertSame('18', $detail->attributeValue('bridge_size'));
        $this->assertNull($detail->attributeValue('eye_size'));
    }

    public function test_deactivate_and_reactivate(): void
    {
        $detail = FrameDetail::factory()->create();

        $this->actingAs($this->admin)->post("/produk/frame/{$detail->id}/alih-aktif")->assertRedirect();
        $this->assertFalse($detail->refresh()->is_active);

        $this->actingAs($this->admin)->post("/produk/frame/{$detail->id}/alih-aktif")->assertRedirect();
        $this->assertTrue($detail->refresh()->is_active);
    }

    public function test_detail_page_shows_attributes_and_money_format(): void
    {
        $detail = FrameDetail::factory()->create(['sale_price' => 250000]);
        $detail->detailAttributes()->create(['key' => 'eye_size', 'value' => '54']);

        $this->actingAs($this->admin)->get("/produk/frame/{$detail->id}")
            ->assertOk()->assertSee('54');
    }

    public function test_invalid_type_route_is_404(): void
    {
        $this->actingAs($this->admin)->get('/katalog/bukan-tipe')->assertNotFound();
        $this->actingAs($this->admin)->get('/produk/bukan-tipe/1')->assertNotFound();
    }

    public function test_create_page_renders_for_every_product_type(): void
    {
        // A2 (paritas Artesis): GET buat mengarah ke satu halaman
        // kelola; dialog formulirnya wajib ter-render untuk kelima
        // tipe (regresi PR #50: form dulu 500 "Undefined variable
        // $product").
        foreach (Product::TYPES as $type) {
            $this->actingAs($this->admin)->get("/katalog/{$type}/buat")
                ->assertRedirect(route('products.index', $type));
            $this->actingAs($this->admin)->get("/katalog/{$type}")
                ->assertOk()->assertSee('name="sku"', false);
        }
    }

    public function test_create_dialog_lists_existing_masters(): void
    {
        $master = Product::factory()->forType('frame')->create();

        // Pemilih induk pada dialog buat memuat master yang ada.
        $this->actingAs($this->admin)->get('/katalog/frame')
            ->assertOk()->assertSee($master->name);
    }

    public function test_edit_endpoint_redirects_to_the_management_page(): void
    {
        // A2: GET ubah mengarah ke daftar; dialog ubah per baris
        // ter-render di sana (regresi PR #50: halaman ubah dulu 500).
        foreach (Product::DETAIL_MODELS as $type => $model) {
            $detail = $model::factory()->create();
            $this->actingAs($this->admin)->get("/produk/{$type}/{$detail->id}/ubah")
                ->assertRedirect(route('products.index', $type));
        }
    }

    public function test_type_change_moves_unlocked_detail(): void
    {
        $detail = FrameDetail::factory()->create(['sku' => 'MOV-1']);

        $moved = app(ProductService::class)->updateDetail($this->admin, $detail, [
            'type' => 'accessory', 'name' => 'Jadi Aksesoris', 'cost_price' => 5, 'sale_price' => 9,
        ]);

        $this->assertInstanceOf(AccessoriesDetail::class, $moved);
        $this->assertSame($detail->id, $moved->id);
        $this->assertSame(0, FrameDetail::where('sku', 'MOV-1')->count());
    }

    public function test_type_change_is_locked_once_detail_is_sold(): void
    {
        $detail = FrameDetail::factory()->create(['sku' => 'LOCK-1']);
        $sale = Sale::factory()->create();
        DB::table('sale_items')->insert([
            'sale_id' => $sale->id, 'detail_id' => $detail->id, 'detail_type' => 'frame',
            'product_name_snapshot' => 'Terkunci', 'type' => 'frame', 'qty' => 1,
            'unit_price' => 1000, 'line_total' => 1000,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(ProductService::class)->updateDetail($this->admin, $detail, ['type' => 'lens']);
    }
}
