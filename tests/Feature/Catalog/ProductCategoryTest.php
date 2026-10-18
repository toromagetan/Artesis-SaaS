<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Models\ProductParent;
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
        $this->actingAs($this->owner)->post('/katalog/lens/induk', [
            'name' => 'FSV 1.56 Bluecromic',
        ])->assertRedirect();
        $lens = ProductParent::where('name', 'FSV 1.56 Bluecromic')->firstOrFail();
        $this->assertSame('lens', $lens->type);
        $this->assertSame('Lensa', $lens->category->name);

        $this->actingAs($this->owner)->post('/katalog/softlens/induk', [
            'name' => 'X2 N8',
        ])->assertRedirect();
        $softlens = ProductParent::where('name', 'X2 N8')->firstOrFail();
        $this->assertSame('Softlens', $softlens->category->name);

        // Nama kategori tampil di detail induk.
        $this->actingAs($this->owner)->get('/produk-induk/'.$lens->id)
            ->assertOk()->assertSee('Lensa');
    }
}
