<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InitialStockImportService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Support\TenantCache;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * C-01 (audit menyeluruh A-03; Blueprint J.2 butir 5): cache data
 * bisnis memakai awalan ember pemilik `t{tenant_id}:` dari
 * TenantCache. Satu-satunya cache data bisnis aplikasi adalah
 * pratinjau impor stok awal — token pratinjau milik tenant A tidak
 * boleh terbaca atau dikomit oleh tenant B.
 */
class TenantCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
    }

    /** @return array{0: Tenant, 1: Branch, 2: User} */
    private function tenantOwner(string $tag): array
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

    public function test_key_buckets_follow_the_validated_actor_context(): void
    {
        [$tenantA, , $ownerA] = $this->tenantOwner('A');
        $this->tenantOwner('B');

        // Tenant valid → t{id}:
        $this->assertSame('t'.$tenantA->id.':kunci', TenantCache::key('kunci', $ownerA));

        // Platform → ember platform.
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertSame('platform:kunci', TenantCache::key('kunci', $platform));

        // Tanpa aktor (proses in-process, batas kepercayaan
        // ADR-022) → ember system.
        $this->assertSame('system:kunci', TenantCache::key('kunci'));

        // Unassigned (tenant sudah ada, aktor tanpa tenant) →
        // gagal-tertutup.
        $unassigned = User::where('username', 'owner')->firstOrFail();
        try {
            TenantCache::key('kunci', $unassigned);
            $this->fail('Aktor unassigned harus ditolak dari cache bisnis');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        // Tenant menggantung (baris tenant tidak ada) → ditolak.
        // FK users.tenant_id mewajibkan matikan pemeriksaan FK
        // sesaat untuk membuat baris yatim ini.
        $dangling = Schema::withoutForeignKeyConstraints(
            fn () => User::factory()->create(['tenant_id' => 999]),
        );
        try {
            TenantCache::key('kunci', $dangling);
            $this->fail('Aktor tenant menggantung harus ditolak dari cache bisnis');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_standalone_bucket_when_no_tenant_exists(): void
    {
        $this->assertSame(0, Tenant::count());
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->assertSame('standalone:kunci', TenantCache::key('kunci', $owner));
    }

    public function test_import_preview_is_bound_to_the_owners_tenant_bucket(): void
    {
        [$tenantA, $branchA, $ownerA] = $this->tenantOwner('A');
        [, $branchB, $ownerB] = $this->tenantOwner('B');
        $service = app(InitialStockImportService::class);

        $this->actingAs($ownerA);
        $productA = Product::factory()->ofType('frame')->create([
            'tenant_id' => $tenantA->id, 'sku' => 'FR-C01-A', 'cost_price' => '100000',
        ]);
        $preview = $service->preview("sku,cabang,qty\nFR-C01-A,{$branchA->id},7\n");
        $this->assertSame([], $preview['errors']);
        $token = $preview['token'];

        // Entri hidup HANYA di ember tenant A: bukan kunci global
        // lama, bukan ember tenant B.
        $this->assertNotNull(Cache::get('t'.$tenantA->id.':initial-stock-preview:'.$token));
        $this->assertNull(Cache::get('initial-stock-preview:'.$token));

        // Tenant B memegang token A: pratinjau tidak terbaca…
        $this->actingAs($ownerB);
        $this->assertNull($service->getPreview($token));

        // …dan komitmen lintas tenant gagal-tertutup sebagai token
        // kedaluwarsa, tanpa satu pun gerakan stok tercipta.
        try {
            $service->commit($ownerB, $token);
            $this->fail('Komitmen lintas tenant harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, StockMovement::count());
        }

        // Pemilik sah tetap dapat membaca & mengomit pratinjaunya.
        $this->actingAs($ownerA);
        $this->assertNotNull($service->getPreview($token));
        $service->commit($ownerA, $token);
        $this->assertSame(1, StockMovement::where('branch_id', $branchA->id)->count());
        $this->assertSame(0, StockMovement::where('branch_id', $branchB->id)->count());
        $this->assertSame($productA->id, StockMovement::firstOrFail()->product_id);
    }

    public function test_commit_with_explicit_actor_mismatching_ambient_session_is_rejected(): void
    {
        // Koreksi audit C-01-R1: sesi ambient tenant B, aktor
        // eksplisit tenant A — komitmen atas pratinjau B oleh A
        // ditolak gagal-tertutup SEBELUM cache dibaca dari ember
        // yang salah; tidak ada gerakan stok maupun audit impor.
        [$tenantA, $branchA, $ownerA] = $this->tenantOwner('A');
        [$tenantB, $branchB, $ownerB] = $this->tenantOwner('B');
        $service = app(InitialStockImportService::class);

        $this->actingAs($ownerB);
        Product::factory()->ofType('frame')->create([
            'tenant_id' => $tenantB->id, 'sku' => 'FR-C01-B', 'cost_price' => '100000',
        ]);
        $previewB = $service->preview("sku,cabang,qty\nFR-C01-B,{$branchB->id},5\n", $ownerB);
        $this->assertSame([], $previewB['errors']);

        try {
            $service->commit($ownerA, $previewB['token']);
            $this->fail('Komitmen aktor A atas sesi B harus ditolak');
        } catch (AuthorizationException) {
            $this->assertSame(0, StockMovement::count());
            $this->assertSame(0, AuditLog::where('action', 'stock.import_initial')->count());
        }

        // Pratinjau B tetap utuh & hanya dapat dikomit pemiliknya.
        $service->commit($ownerB, $previewB['token']);
        $this->assertSame(1, StockMovement::where('branch_id', $branchB->id)->count());
        $this->assertSame(0, StockMovement::where('branch_id', $branchA->id)->count());
    }

    public function test_preview_with_explicit_actor_reads_actor_tenant_without_ambient_session(): void
    {
        // Koreksi audit C-01-R2: tanpa sesi ambient sama sekali,
        // pembacaan produk & cabang preview mengikuti aktor
        // eksplisit — bukan konteks tamu.
        [$tenantA, $branchA, $ownerA] = $this->tenantOwner('A');
        [$tenantB, $branchB, $ownerB] = $this->tenantOwner('B');
        $service = app(InitialStockImportService::class);

        $this->actingAs($ownerA);
        $productA = Product::factory()->ofType('frame')->create([
            'tenant_id' => $tenantA->id, 'sku' => 'FR-R2-A', 'cost_price' => '100000',
        ]);
        $this->actingAs($ownerB);
        Product::factory()->ofType('frame')->create([
            'tenant_id' => $tenantB->id, 'sku' => 'FR-R2-B', 'cost_price' => '100000',
        ]);
        auth()->logout();

        // Produk & cabang A terbaca penuh atas nama aktor A.
        $preview = $service->preview("sku,cabang,qty\nFR-R2-A,{$branchA->id},9\n", $ownerA);
        $this->assertSame([], $preview['errors']);
        $this->assertSame($productA->id, $preview['valid'][0]['product_id']);
        $this->assertSame($branchA->id, $preview['valid'][0]['branch_id']);

        // SKU & cabang B TIDAK termuat dalam pembacaan aktor A:
        // keduanya menjadi baris kesalahan, nol baris valid.
        $foreign = $service->preview("sku,cabang,qty\nFR-R2-B,{$branchA->id},1\nFR-R2-A,{$branchB->id},1\n", $ownerA);
        $this->assertSame([], $foreign['valid']);
        $this->assertSame(2, count($foreign['errors']));
    }

    public function test_preview_with_mismatched_ambient_is_rejected_before_reading(): void
    {
        [$tenantA, $branchA, $ownerA] = $this->tenantOwner('A');
        [, , $ownerB] = $this->tenantOwner('B');
        $service = app(InitialStockImportService::class);

        $this->actingAs($ownerB);
        try {
            $service->preview("sku,cabang,qty\nFR-R2-A,{$branchA->id},1\n", $ownerA);
            $this->fail('Pratinjau aktor A atas sesi B harus ditolak');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_system_bucket_preview_cannot_be_committed_by_a_tenant_actor(): void
    {
        // Pratinjau yang dibuat proses tanpa aktor hidup di ember
        // system: — aktor tenant tidak dapat mengomitnya sebagai
        // operasi bisnis (koreksi audit C-01-R1 butir C.3).
        [$tenantA, $branchA, $ownerA] = $this->tenantOwner('A');
        $service = app(InitialStockImportService::class);

        $this->actingAs($ownerA);
        Product::factory()->ofType('frame')->create([
            'tenant_id' => $tenantA->id, 'sku' => 'FR-C01-S', 'cost_price' => '100000',
        ]);
        auth()->logout();
        // Tanpa aktor & tanpa sesi, resolusi baris berjalan dalam
        // konteks tamu (gagal-tertutup) — yang dibuktikan di sini
        // adalah penempatan embernya, bukan isi validasinya.
        $preview = $service->preview("sku,cabang,qty\nFR-C01-S,{$branchA->id},3\n");
        $this->assertNotNull(Cache::get('system:initial-stock-preview:'.$preview['token']));
        $this->assertNull(Cache::get('t'.$tenantA->id.':initial-stock-preview:'.$preview['token']));

        try {
            $service->commit($ownerA, $preview['token']);
            $this->fail('Pratinjau ember system tidak boleh dikomit aktor tenant');
        } catch (ValidationException) {
            $this->assertSame(0, StockMovement::count());
        }
    }
}
