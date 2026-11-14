<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Support\TenantStorage;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

    private function createProductAs(User $actor, string $sku): FrameDetail
    {
        $this->actingAs($actor)->post('/katalog/frame', $this->productPayload($sku))->assertRedirect();

        return FrameDetail::withoutGlobalScopes()->where('sku', $sku)->firstOrFail();
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
            public function createDetail(User $actor, string $type, array $data, array $attributes = []): Model
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
            public function updateDetail(User $actor, Model $detail, array $data, array $attributes = []): Model
            {
                throw new \RuntimeException('gagal uji');
            }
        });

        $this->actingAs($adminA)->put('/produk/frame/'.$product->id, [
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

        $this->actingAs($adminA)->put('/produk/frame/'.$product->id, [
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
        $logoA = (string) Setting::value('store.logo_path');
        $this->assertMatchesRegularExpression('#^tenants/'.$tenantA->id.'/settings/store-logo-.+\.png$#', $logoA);
        Storage::disk('public')->assertExists($logoA);

        $this->actingAs($adminB)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.jpg', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $logoB = (string) Setting::value('store.logo_path');
        $this->assertMatchesRegularExpression('#^tenants/'.$tenantB->id.'/settings/store-logo-.+\.jpg$#', $logoB);
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

    public function test_logo_replacement_with_same_extension_uses_a_new_unique_file(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $first = (string) Setting::value('store.logo_path');

        // Ekstensi SAMA (kasus F-01-R1): berkas lama tidak boleh
        // tertimpa di path-nya sendiri; unggahan kedua memakai
        // path baru dan berkas pertama terhapus sesudahnya.
        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo-baru.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $second = (string) Setting::value('store.logo_path');

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('#^tenants/'.$tenantA->id.'/settings/store-logo-.+\.png$#', $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_failed_logo_setting_save_keeps_old_logo_and_cleans_new_file(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $first = (string) Setting::value('store.logo_path');

        // Simulasikan kegagalan database KHUSUS pada penyimpanan
        // nilai logo: penulisan berkas baru sudah terjadi saat itu,
        // jadi berkas baru wajib dibersihkan dan nilai + berkas
        // lama tidak berubah.
        Setting::saving(function (Setting $model): void {
            if ($model->key === 'store.logo_path') {
                throw new \RuntimeException('kegagalan simpan uji');
            }
        });

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo-baru.png', 120, 40),
        ]))->assertServerError();

        $this->assertSame($first, Setting::value('store.logo_path'));
        Storage::disk('public')->assertExists($first);
        $this->assertSame([$first], Storage::disk('public')->files('tenants/'.$tenantA->id.'/settings'));
    }

    public function test_product_create_survives_disk_failure_with_a_warning(): void
    {
        [, , $adminA] = $this->tenantAdmin('A');

        // Disk publik diarahkan ke lokasi yang tidak dapat ditulis.
        config(['filesystems.disks.public.root' => '/proc/artesis-tidak-tertulis']);
        Storage::forgetDisk('public');

        $response = $this->actingAs($adminA)->post('/katalog/frame', $this->productPayload('FR-DISK'))
            ->assertRedirect()
            ->assertSessionHas('warning');

        // Produk TETAP dibuat (tanpa gambar) dan pengguna tidak
        // menemui 500 pasca-commit (F-01-R2).
        $product = FrameDetail::withoutGlobalScopes()->where('sku', 'FR-DISK')->firstOrFail();
        $this->assertNull($product->image_path);
        $response->assertRedirect(route('products.show', ['frame', $product->id]));
    }

    public function test_product_update_aborts_cleanly_when_disk_fails(): void
    {
        [, , $adminA] = $this->tenantAdmin('A');
        $product = $this->createProductAs($adminA, 'FR-DISK2');
        $oldPath = $product->image_path;

        config(['filesystems.disks.public.root' => '/proc/artesis-tidak-tertulis']);
        Storage::forgetDisk('public');

        $this->actingAs($adminA)->put('/produk/frame/'.$product->id, [
            'sku' => 'FR-DISK2', 'name' => 'Nama Berubah', 'type' => 'frame',
            'cost_price' => '100000', 'sale_price' => '200000',
            'image' => UploadedFile::fake()->image('baru.jpg', 400, 300),
        ])->assertRedirect()->assertSessionHasErrors('image');

        // Tidak ada perubahan yang diterapkan sama sekali.
        $fresh = $product->fresh();
        $this->assertSame($oldPath, $fresh->image_path);
        $this->assertSame('Frame FR-DISK2', $fresh->product->name);
    }

    /** Logo global (baris tenant_id NULL) + berkasnya di disk. */
    private function seedGlobalLogo(string $path = 'branding/store-logo-global.png'): string
    {
        Storage::disk('public')->put($path, 'logo-global');
        TenantContext::asSystem(
            fn () => Setting::set('store.logo_path', $path),
        );

        return $path;
    }

    public function test_tenant_uploading_first_logo_keeps_the_inherited_global_logo(): void
    {
        $global = $this->seedGlobalLogo();
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        [, , $adminB] = $this->tenantAdmin('B');

        // Prasyarat skenario R4: A belum punya logo sendiri dan
        // mewarisi logo global sebagai nilai efektif.
        $this->actingAs($adminA);
        $this->assertSame($global, Setting::value('store.logo_path'));
        $this->assertNull(Setting::ownValue('store.logo_path'));

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));

        // Berkas global TIDAK terhapus oleh unggahan A, dan B
        // tetap mewarisi logo global yang sama.
        Storage::disk('public')->assertExists($global);
        $ownA = (string) Setting::ownValue('store.logo_path');
        $this->assertMatchesRegularExpression('#^tenants/'.$tenantA->id.'/settings/store-logo-.+\.png$#', $ownA);
        Storage::disk('public')->assertExists($ownA);
        $this->actingAs($adminB);
        $this->assertSame($global, Setting::value('store.logo_path'));
    }

    public function test_tenant_removing_inherited_logo_keeps_the_global_file(): void
    {
        $global = $this->seedGlobalLogo();
        [, , $adminA] = $this->tenantAdmin('A');
        [, , $adminB] = $this->tenantAdmin('B');

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'remove_logo' => '1',
        ]))->assertRedirect(route('settings.general.edit'));

        // Penghapusan oleh tenant yang mewarisi TIDAK menyentuh
        // berkas global; B tetap mewarisinya.
        Storage::disk('public')->assertExists($global);
        $this->actingAs($adminB);
        $this->assertSame($global, Setting::value('store.logo_path'));
    }

    public function test_tenant_removing_own_logo_keeps_the_global_file(): void
    {
        $global = $this->seedGlobalLogo();
        [, , $adminA] = $this->tenantAdmin('A');
        [, , $adminB] = $this->tenantAdmin('B');

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'store_logo' => UploadedFile::fake()->image('logo.png', 120, 40),
        ]))->assertRedirect(route('settings.general.edit'));
        $ownA = (string) Setting::ownValue('store.logo_path');
        Storage::disk('public')->assertExists($ownA);

        $this->actingAs($adminA)->put(route('settings.general.update'), $this->settingsPayload([
            'remove_logo' => '1',
        ]))->assertRedirect(route('settings.general.edit'));

        // Berkas milik A sendiri terhapus; berkas global utuh dan
        // tetap menjadi warisan efektif bagi B.
        Storage::disk('public')->assertMissing($ownA);
        Storage::disk('public')->assertExists($global);
        $this->actingAs($adminB);
        $this->assertSame($global, Setting::value('store.logo_path'));
    }

    public function test_tenant_storage_buckets_follow_the_validated_context(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        $platform = User::where('username', 'platform')->firstOrFail();

        $this->assertSame('tenants/'.$tenantA->id.'/products', TenantStorage::directory($adminA, 'products'));
        $this->assertSame('platform/products', TenantStorage::directory($platform, 'products'));
    }

    public function test_tenant_storage_standalone_bucket_when_no_tenant_exists(): void
    {
        // Tanpa satu pun tenant, pengguna bawaan adalah instalasi
        // mandiri — ember standalone. (Begitu ada tenant, pengguna
        // tanpa tenant menjadi unassigned dan ditolak; lihat test
        // penolakan unassigned.)
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->assertSame('standalone/products', TenantStorage::directory($owner, 'products'));
    }

    public function test_tenant_storage_rejects_a_dangling_tenant(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $dangling = User::factory()->create(['tenant_id' => 999]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->expectException(AuthorizationException::class);
        TenantStorage::directory($dangling, 'products');
    }

    public function test_tenant_storage_rejects_an_unassigned_user(): void
    {
        $this->tenantAdmin('A'); // memastikan tabel tenants tidak kosong
        $unassigned = User::factory()->create(['tenant_id' => null]);

        $this->expectException(AuthorizationException::class);
        TenantStorage::directory($unassigned, 'products');
    }
}
