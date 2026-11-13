<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F-01 — Berkas per tenant (audit menyeluruh A-01 + A-02).
 *
 * Membuktikan: (1) gambar produk dan logo toko tersimpan di
 * namespace pemiliknya (tenants/{id}/… atau standalone/…), tidak
 * lagi di direktori bersama; (2) alur berkas aman terhadap
 * kegagalan — pembuatan yang ditolak layanan tidak meninggalkan
 * berkas yatim, pembaruan yang gagal tidak menghapus gambar lama
 * dan membersihkan gambar baru; (3) logo satu tenant tidak
 * menimpa atau terhapus oleh tenant lain.
 */
class TenantFileStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        Storage::fake('public');
    }

    /** @return array{0: Tenant, 1: Branch, 2: User} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create(['name' => 'Optik '.$tag, 'slug' => 'optik-'.strtolower($tag), 'email' => strtolower($tag).'@uji.id']);
        $platform = User::where('username', 'platform')->firstOrFail();
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $platform,
        );
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Pusat '.$tag, 'code' => 'PST-'.$tag]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->roles()->sync([Role::where('name', 'Owner')->firstOrFail()->id]);
        $user->branches()->sync([$branch->id]);

        return [$tenant, $branch, $user];
    }

    /** @return array<string, mixed> */
    private function productPayload(string $sku, bool $withImage = true): array
    {
        return [
            'sku' => $sku, 'name' => 'Frame '.$sku,
            'cost_price' => '100000', 'sale_price' => '200000',
        ] + ($withImage ? ['image' => UploadedFile::fake()->image(strtolower($sku).'.jpg', 400, 300)] : []);
    }

    private function createProductAs(User $actor, string $sku): Product
    {
        $this->actingAs($actor)->post('/katalog/frame', $this->productPayload($sku))->assertRedirect();

        return Product::withoutGlobalScopes()->where('sku', $sku)->firstOrFail();
    }

    public function test_product_images_are_namespaced_per_tenant(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        [$tenantB, , $adminB] = $this->tenantAdmin('B');

        $productA = $this->createProductAs($adminA, 'FR-A1');
        $productB = $this->createProductAs($adminB, 'FR-B1');

        $this->assertStringStartsWith('tenants/'.$tenantA->id.'/products/', (string) $productA->image_path);
        $this->assertStringStartsWith('tenants/'.$tenantB->id.'/products/', (string) $productB->image_path);
        Storage::disk('public')->assertExists($productA->image_path);
        Storage::disk('public')->assertExists($productB->image_path);
    }

    public function test_standalone_product_image_uses_standalone_bucket(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();

        $product = $this->createProductAs($owner, 'FR-S1');

        $this->assertStringStartsWith('standalone/products/', (string) $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_failed_create_leaves_no_orphan_file(): void
    {
        [, , $adminA] = $this->tenantAdmin('A');

        // Layanan menolak pembuatan (mis. batas paket): belum ada
        // berkas yang boleh ditulis sama sekali.
        app()->instance(ProductService::class, new class extends ProductService
        {
            public function create(User $actor, array $data, array $attributes = []): Product
            {
                throw new \RuntimeException('ditolak layanan uji');
            }
        });

        $this->actingAs($adminA)->post('/katalog/frame', $this->productPayload('FR-GAGAL'))
            ->assertServerError();

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_update_keeps_old_image_and_cleans_new_file(): void
    {
        [, , $adminA] = $this->tenantAdmin('A');
        $product = $this->createProductAs($adminA, 'FR-U1');
        $oldPath = $product->image_path;

        app()->instance(ProductService::class, new class extends ProductService
        {
            public function update(User $actor, Product $product, array $data, array $attributes = []): Product
            {
                throw new \RuntimeException('gagal uji');
            }
        });

        $this->actingAs($adminA)->put('/produk/'.$product->id, [
            'sku' => 'FR-U1', 'name' => 'Frame FR-U1', 'type' => 'frame',
            'cost_price' => '100000', 'sale_price' => '200000',
            'image' => UploadedFile::fake()->image('baru.jpg', 400, 300),
        ])->assertServerError();

        $this->assertSame($oldPath, $product->fresh()->image_path);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles());
    }

    public function test_successful_update_replaces_image_and_deletes_old(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        $product = $this->createProductAs($adminA, 'FR-U2');
        $oldPath = $product->image_path;

        $this->actingAs($adminA)->put('/produk/'.$product->id, [
            'sku' => 'FR-U2', 'name' => 'Frame FR-U2', 'type' => 'frame',
            'cost_price' => '100000', 'sale_price' => '200000',
            'image' => UploadedFile::fake()->image('pengganti.jpg', 400, 300),
        ])->assertRedirect();

        $newPath = $product->fresh()->image_path;
        $this->assertNotSame($oldPath, $newPath);
        $this->assertStringStartsWith('tenants/'.$tenantA->id.'/products/', (string) $newPath);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    /** @return array<string, mixed> */
    private function settingsPayload(array $extra = []): array
    {
        return $extra + [
            'store_name' => 'Lentera', 'tax_rate_default' => '11', 'tax_mode' => 'exclusive',
            'discount_threshold' => '20', 'technician_commission_rate' => '5',
        ];
    }

    public function test_store_logos_are_namespaced_per_tenant(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        [$tenantB, , $adminB] = $this->tenantAdmin('B');

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $logoA = 'tenants/'.$tenantA->id.'/settings/store-logo.png';
        $this->assertSame($logoA, Setting::value('store.logo_path'));
        Storage::disk('public')->assertExists($logoA);

        $this->actingAs($adminB)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.jpg', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $logoB = 'tenants/'.$tenantB->id.'/settings/store-logo.jpg';
        $this->assertSame($logoB, Setting::value('store.logo_path'));
        Storage::disk('public')->assertExists($logoB);
        // Logo A tidak tertimpa oleh unggahan B.
        Storage::disk('public')->assertExists($logoA);

        // Penghapusan oleh A tidak menyentuh berkas B.
        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'remove_logo' => '1',
        ]))->assertRedirect(route('settings.general.edit'));
        Storage::disk('public')->assertMissing($logoA);
        Storage::disk('public')->assertExists($logoB);
    }
}
