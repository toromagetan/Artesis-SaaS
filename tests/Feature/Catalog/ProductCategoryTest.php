<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori produk sebagai data tetap (paritas Artesis, lanjutan
 * ADR-015): lima kategori bawaan dari migrasi, tanpa UI kelola
 * (keputusan owner 2026-10-19 — kategori tidak ditambah/dikurangi
 * dari menu). Induk otomatis memegang kategori bawaan tipenya.
 */
class ProductCategoryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_five_default_categories_exist(): void
    {
        $this->assertSame(5, ProductCategory::count());
        $names = ProductCategory::orderBy('sort_order')->pluck('name')->all();
        $this->assertSame(['Frame', 'Lensa', 'Lensa RX', 'Softlens', 'Aksesoris'], $names);
    }

    public function test_categories_have_no_management_ui(): void
    {
        $this->actingAs($this->owner)->get('/katalog/kategori')->assertNotFound();
        $this->actingAs($this->owner)->post('/katalog/kategori', [
            'name' => 'Kategori Baru', 'type' => 'lens',
        ])->assertNotFound();
    }

    public function test_parent_automatically_holds_the_category_of_its_type(): void
    {
        $this->actingAs($this->owner)->post('/katalog/lens', [
            'sku' => 'LNS-FSV156', 'name' => 'FSV 1.56 Bluecromic', 'cost_price' => 10, 'sale_price' => 20,
        ])->assertRedirect();
        $lens = Product::where('name', 'FSV 1.56 Bluecromic')->firstOrFail();
        $this->assertSame('lens', $lens->typeKey());
        $this->assertSame('Lensa', $lens->category->name);

        // A2: master lahir dari formulir detail; kategorinya
        // mengikuti tipe halaman tempat ia dibuat.
        $this->actingAs($this->owner)->post('/katalog/softlens', [
            'sku' => 'SFT-X2N8', 'name' => 'X2 N8', 'cost_price' => 10, 'sale_price' => 20,
        ])->assertRedirect();
        $softlens = Product::where('name', 'X2 N8')->firstOrFail();
        $this->assertSame('Softlens', $softlens->category->name);
        $this->assertSame('Lensa', $lens->category->name);
    }
}
