<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori produk sebagai entitas terpisah (paritas Artesis):
 * lima kategori bawaan dari migrasi, CRUD terkelola, dan induk
 * terikat kategori setipe.
 */
class ProductCategoryTest extends TestCase
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

    public function test_five_default_categories_exist_and_are_listed(): void
    {
        $this->assertSame(5, ProductCategory::count());
        $names = ProductCategory::orderBy('sort_order')->pluck('name')->all();
        $this->assertSame(['Frame', 'Lensa', 'Lensa RX', 'Softlens', 'Aksesoris'], $names);

        $this->actingAs($this->owner)->get('/katalog/kategori')
            ->assertOk()->assertSee('Lensa RX')->assertSee('Aksesoris');
    }

    public function test_category_crud_and_parent_linkage(): void
    {
        // Kategori tambahan setipe lensa.
        $this->actingAs($this->owner)->post('/katalog/kategori', [
            'name' => 'Lensa Premium', 'description' => 'Lini premium', 'type' => 'lens', 'sort_order' => 9,
        ])->assertRedirect();
        $premium = ProductCategory::where('name', 'Lensa Premium')->firstOrFail();

        // Induk memakai kategori itu; tipe induk mengikuti kategori.
        $this->actingAs($this->owner)->post('/katalog/lens/induk', [
            'name' => 'FSV 1.56 Bluecromic', 'category_id' => $premium->id,
        ])->assertRedirect();
        $parent = ProductParent::where('name', 'FSV 1.56 Bluecromic')->firstOrFail();
        $this->assertSame($premium->id, $parent->category_id);
        $this->assertSame('lens', $parent->type);

        // Kategori beda tipe ditolak untuk induk lensa.
        $frameCategory = ProductCategory::where('type', 'frame')->firstOrFail();
        $this->actingAs($this->owner)->post('/katalog/lens/induk', [
            'name' => 'Lensa Salah Kategori', 'category_id' => $frameCategory->id,
        ])->assertSessionHasErrors('category_id');
        $this->assertDatabaseMissing('product_parents', ['name' => 'Lensa Salah Kategori']);

        // Tipe kategori yang sudah dipakai induk terkunci.
        $this->actingAs($this->owner)->put('/katalog/kategori/'.$premium->id, [
            'name' => 'Lensa Premium', 'type' => 'frame',
        ])->assertSessionHasErrors('type');

        // Nonaktifkan kategori.
        $this->actingAs($this->owner)->post('/katalog/kategori/'.$premium->id.'/alih-aktif')->assertRedirect();
        $this->assertFalse($premium->fresh()->is_active);
    }

    public function test_parent_without_category_gets_the_default_of_its_type(): void
    {
        $this->actingAs($this->owner)->post('/katalog/softlens/induk', [
            'name' => 'X2 N8',
        ])->assertRedirect();
        $parent = ProductParent::where('name', 'X2 N8')->firstOrFail();
        $this->assertNotNull($parent->category_id);
        $this->assertSame('Softlens', $parent->category->name);
    }

    public function test_gudang_can_view_but_cannot_manage_categories(): void
    {
        $this->actingAs($this->gudang)->get('/katalog/kategori')->assertOk();
        $this->actingAs($this->gudang)->post('/katalog/kategori', [
            'name' => 'Kategori Gudang', 'type' => 'frame',
        ])->assertForbidden();
    }
}
