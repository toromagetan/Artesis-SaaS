<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\AccessoriesDetail;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul katalog (owner 15 Nov 2026): tab kategori di atas pencarian
 * pada daftar produk (pola Artesis lama/pos-optik-istori) dan pilihan
 * jenis aksesoris yang eksplisit — Stok vs Proses — di formulir,
 * dengan badge untuk kedua jenis di daftar.
 */
class CatalogTabsKindTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_catalog_index_shows_category_tabs_above_search(): void
    {
        $response = $this->actingAs($this->owner)->get('/katalog/frame')->assertOk();

        $html = $response->getContent();
        foreach (['frame', 'lens', 'rx', 'softlens', 'accessory'] as $type) {
            $this->assertStringContainsString(route('products.index', $type), $html);
        }
        $this->assertStringContainsString('aria-label="Kategori produk"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);

        // Tab berada di atas formulir pencarian katalog (jangkar =
        // placeholder khas pencarian katalog, bukan pencarian global).
        $tabPos = strpos($html, 'aria-label="Kategori produk"');
        $searchPos = strpos($html, 'Cari nama, SKU, merek');
        $this->assertTrue($tabPos !== false && $searchPos !== false && $tabPos < $searchPos);
    }

    public function test_accessory_form_offers_explicit_stock_or_process_choice(): void
    {
        $html = $this->actingAs($this->owner)->get('/katalog/accessory')->assertOk()->getContent();

        $this->assertStringContainsString('name="is_process_item" value="0"', $html);
        $this->assertStringContainsString('name="is_process_item" value="1"', $html);
        $this->assertStringContainsString('Jenis Item', $html);
    }

    public function test_accessory_kind_persists_and_badges_both_kinds(): void
    {
        $this->actingAs($this->owner)->post('/katalog/accessory', [
            'sku' => 'ACC-STOK-1', 'name' => 'Kain Microfiber', 'variant' => 'Biru',
            'cost_price' => 5000, 'sale_price' => 15000, 'is_process_item' => '0',
        ])->assertRedirect();
        $this->actingAs($this->owner)->post('/katalog/accessory', [
            'sku' => 'ACC-PROS-1', 'name' => 'Pewarnaan Lensa', 'variant' => 'Gradasi',
            'cost_price' => 50000, 'sale_price' => 150000, 'is_process_item' => '1',
        ])->assertRedirect();

        $this->assertFalse(AccessoriesDetail::where('sku', 'ACC-STOK-1')->firstOrFail()->is_process_item);
        $this->assertTrue(AccessoriesDetail::where('sku', 'ACC-PROS-1')->firstOrFail()->is_process_item);

        $html = $this->actingAs($this->owner)->get('/katalog/accessory')->assertOk()->getContent();
        $this->assertStringContainsString('Stok', $html);
        $this->assertStringContainsString('Proses', $html);
    }
}
