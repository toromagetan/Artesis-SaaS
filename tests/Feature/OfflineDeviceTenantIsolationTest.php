<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\OfflineDeviceService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Koreksi audit PR #33 putaran 3 — P1: snapshot perangkat offline
 * membaca katalog + pelanggan lintas tenant.
 *
 * Akar: middleware perangkat mencabut penanda tamu setelah token
 * sah tetapi TIDAK memasang aktor, sehingga TenantScope tidak
 * membatasi query snapshot; pencarian duplikat client_uuid pada
 * sinkronisasi juga membaca penjualan tenant lain (id + nomor
 * bocor, dan niat sah terhalang sebagai "duplikat" palsu).
 *
 * Perbaikan: akun perangkat dipasang sebagai aktor permintaan
 * (pola AuthenticateApiToken) dan pencarian duplikat terpagar
 * ember perangkat secara eksplisit. Test ini membuktikan matriks
 * auditor dengan dua tenant SaaS nyata: snapshot A hanya berisi
 * data A, snapshot B hanya berisi data B, token tidak sah/dicabut
 * ditolak 401, dan UUID tenant lain tidak bocor maupun menghalangi.
 */
class OfflineDeviceTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        TenantContext::asSystem(fn () => Setting::set('tax.rate_default', '0'));
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'email_verified_at' => now(),            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function cashier(Tenant $tenant, Branch $branch, string $tag): User
    {
        $kasir = User::create([
            'email_verified_at' => now(),            'name' => 'Kasir '.$tag, 'username' => 'kasir-'.$tag,
            'email' => 'kasir-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $kasir->branches()->attach($branch);

        return $kasir;
    }

    /** Daftarkan perangkat untuk kasir tenant; kembalikan token mentah. */
    private function deviceToken(User $admin, Branch $branch, User $kasir, string $name): string
    {
        $issued = app(OfflineDeviceService::class)->register($admin, $name, $branch->id, $kasir->id);

        return $issued['plain_token'];
    }

    private function stockedProduct(User $admin, Tenant $tenant, Branch $branch, string $tag): Model
    {
        $product = FrameDetail::factory()->create(['product_id' => Product::factory()->forType('frame')->create(['name' => 'Frame '.$tag, 'tenant_id' => $tenant->id])->id, 'sku' => 'FR-'.$tag,
            'cost_price' => '50000', 'sale_price' => '100000',
            'tenant_id' => $tenant->id, ]);
        $supplier = Supplier::create(['name' => 'Supplier '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($admin, $supplier->id, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $product->id, 'qty' => 5, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($admin, $po);
        app(ReceiptService::class)->store($admin, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '50000'],
        ]);

        return $product;
    }

    private function customerOf(Tenant $tenant, Branch $branch, string $tag): Customer
    {
        return Customer::factory()->create([
            'name' => 'Pelanggan '.$tag, 'phone' => '0812-0000-00'.$tag,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
    }

    private function intent(string $uuid, Customer $customer, Model $product): array
    {
        return ['client_uuid' => $uuid, 'local_no' => '001', 'offline_at' => '2026-11-11 09:00:00',
            'customer_id' => $customer->id, 'tax_mode' => 'exclusive',
            'items' => [['detail_type' => 'frame', 'detail_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '100000']]];
    }

    public function test_snapshot_only_contains_own_tenant_data(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $productA = $this->stockedProduct($adminA, $tenantA, $branchA, 'A');
        $productB = $this->stockedProduct($adminB, $tenantB, $branchB, 'B');
        $customerA = $this->customerOf($tenantA, $branchA, 'A');
        $customerB = $this->customerOf($tenantB, $branchB, 'B');
        $tokenA = $this->deviceToken($adminA, $branchA, $this->cashier($tenantA, $branchA, 'a'), 'KSR-A');
        $tokenB = $this->deviceToken($adminB, $branchB, $this->cashier($tenantB, $branchB, 'b'), 'KSR-B');

        $snapA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/offline/snapshot')->assertOk();
        $this->assertContains($productA->id, collect($snapA->json('products'))->pluck('detail_id')->all());
        $this->assertNotContains($productB->id, collect($snapA->json('products'))->pluck('detail_id')->all());
        $this->assertContains($customerA->id, collect($snapA->json('customers'))->pluck('id')->all());
        $this->assertNotContains($customerB->id, collect($snapA->json('customers'))->pluck('id')->all());

        $snapB = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/v1/offline/snapshot')->assertOk();
        $this->assertContains($productB->id, collect($snapB->json('products'))->pluck('detail_id')->all());
        $this->assertNotContains($productA->id, collect($snapB->json('products'))->pluck('detail_id')->all());
        $this->assertContains($customerB->id, collect($snapB->json('customers'))->pluck('id')->all());
        $this->assertNotContains($customerA->id, collect($snapB->json('customers'))->pluck('id')->all());
    }

    public function test_invalid_revoked_and_missing_tokens_are_rejected(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        $kasirA = $this->cashier($tenantA, $branchA, 'a');
        $issued = app(OfflineDeviceService::class)->register($adminA, 'KSR-A', $branchA->id, $kasirA->id);
        $token = $issued['plain_token'];

        $this->getJson('/api/v1/offline/snapshot')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer token-ngawur')
            ->getJson('/api/v1/offline/snapshot')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/offline/snapshot')->assertOk();

        app(OfflineDeviceService::class)->revoke($adminA, $issued['device']);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/offline/snapshot')->assertUnauthorized();
    }

    public function test_cross_tenant_uuid_neither_leaks_nor_blocks(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $productA = $this->stockedProduct($adminA, $tenantA, $branchA, 'A');
        $productB = $this->stockedProduct($adminB, $tenantB, $branchB, 'B');
        $customerA = $this->customerOf($tenantA, $branchA, 'A');
        $customerB = $this->customerOf($tenantB, $branchB, 'B');
        $tokenA = $this->deviceToken($adminA, $branchA, $this->cashier($tenantA, $branchA, 'a'), 'KSR-A');
        $tokenB = $this->deviceToken($adminB, $branchB, $this->cashier($tenantB, $branchB, 'b'), 'KSR-B');

        // Tenant B lebih dulu mensinkronkan UUID "bersama".
        $resB = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-bersama', $customerB, $productB)]])
            ->assertOk();
        $resultB = $resB->json('results.0');
        $this->assertSame('synced', $resultB['status']);
        $saleB = Sale::withoutGlobalScopes()->findOrFail($resultB['sale_id']);

        // Perangkat A mengirim UUID yang sama: TIDAK boleh menerima
        // id/nomor penjualan B. Karena indeks unik global membuat
        // UUID itu mustahil dicatat dua kali, niat A menjadi
        // pengecualian konflik untuk resolusi staf — bukan duplikat
        // palsu, bukan bocoran, bukan galat 500.
        $resA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-bersama', $customerA, $productA)]])
            ->assertOk();
        $resultA = $resA->json('results.0');
        $this->assertSame('exception', $resultA['status']);
        $this->assertSame('uuid_conflict', $resultA['reason']);
        $this->assertArrayNotHasKey('sale_id', $resultA);
        $this->assertArrayNotHasKey('number', $resultA);

        // Dedupe di dalam tenant sendiri tetap bekerja: A
        // mensinkronkan UUID miliknya, lalu mengirimnya ulang =
        // duplikat penjualan A sendiri.
        $resA1 = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-a-sendiri', $customerA, $productA)]])
            ->assertOk();
        $this->assertSame('synced', $resA1->json('results.0.status'));
        $saleA = Sale::withoutGlobalScopes()->findOrFail($resA1->json('results.0.sale_id'));
        $this->assertSame($branchA->id, (int) $saleA->branch_id);

        $resA2 = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-a-sendiri', $customerA, $productA)]])
            ->assertOk();
        $this->assertSame('duplicate', $resA2->json('results.0.status'));
        $this->assertSame($saleA->id, $resA2->json('results.0.sale_id'));
    }

    public function test_uuid_conflict_exception_cannot_be_retried_but_can_be_discarded(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $productA = $this->stockedProduct($adminA, $tenantA, $branchA, 'A');
        $productB = $this->stockedProduct($adminB, $tenantB, $branchB, 'B');
        $customerA = $this->customerOf($tenantA, $branchA, 'A');
        $customerB = $this->customerOf($tenantB, $branchB, 'B');
        $tokenA = $this->deviceToken($adminA, $branchA, $this->cashier($tenantA, $branchA, 'a'), 'KSR-A');
        $tokenB = $this->deviceToken($adminB, $branchB, $this->cashier($tenantB, $branchB, 'b'), 'KSR-B');

        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-bersama', $customerB, $productB)]])
            ->assertOk();
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/offline/sync', ['intents' => [$this->intent('uuid-bersama', $customerA, $productA)]])
            ->assertOk();
        $conflict = OfflineException::where('client_uuid', 'uuid-bersama')
            ->where('reason', 'uuid_conflict')->firstOrFail();

        // Halaman antrean: TIDAK ada tombol Coba Lagi untuk konflik
        // ini; yang tampil adalah penjelasan pemeriksaan manual.
        $index = $this->actingAs($adminA)->get(route('offline-exceptions.index'))->assertOk();
        $index->assertDontSee(route('offline-exceptions.retry', $conflict));
        $index->assertSee('tidak dapat dicoba ulang');

        // POST Coba Lagi langsung pun aman: dialihkan (bukan 500),
        // pengecualian tetap pending, tidak ada penjualan baru.
        $salesBefore = Sale::withoutGlobalScopes()->count();
        $this->actingAs($adminA)->post(route('offline-exceptions.retry', $conflict))
            ->assertRedirect(route('offline-exceptions.index'));
        $this->assertSame('pending', $conflict->fresh()->status);
        $this->assertSame($salesBefore, Sale::withoutGlobalScopes()->count());

        // Buang tetap bisa oleh Owner, dengan jejak audit.
        $this->actingAs($adminA)->post(route('offline-exceptions.discard', $conflict), [
            'note' => 'Transaksi ganda dari perangkat klon — dibuang.',
        ])->assertRedirect(route('offline-exceptions.index'));
        $this->assertSame('discarded', $conflict->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'offline.exception.discard')
            ->where('subject_id', $conflict->id)->exists());
    }
}
