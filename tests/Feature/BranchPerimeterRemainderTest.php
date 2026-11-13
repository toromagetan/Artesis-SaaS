<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Services\OfflineDeviceService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modul A' — Sisa pagar cabang (audit ulang isolasi 10 Nov 2026):
 * tujuan transfer WAJIB satu perusahaan dengan aktor; admin
 * perangkat offline terpagari (daftar + pendaftaran + pencabutan);
 * daftar filter resep & log aktivitas tidak menawarkan nama milik
 * tenant lain. Transfer antar-cabang DI DALAM satu tenant tetap
 * berjalan, termasuk oleh staf yang hanya melekat di cabang asal.
 */
class BranchPerimeterRemainderTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch, Branch} */
    private function tenantWithTwoBranches(string $tag): array
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
        $one = Branch::create(['name' => 'Cabang '.$tag.' Satu', 'tenant_id' => $tenant->id]);
        $two = Branch::create(['name' => 'Cabang '.$tag.' Dua', 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'email_verified_at' => now(),            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach([$one->id, $two->id]);

        return [$tenant, $admin, $one, $two];
    }

    private function stockAt(User $actor, Tenant $tenant, Branch $branch, int $qty): Product
    {
        $product = Product::create([
            'type' => 'frame', 'name' => 'Frame '.$tenant->slug, 'brand' => 'Uji',
            'sku' => 'SKU-'.$tenant->slug, 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $tenant->id,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier '.$tenant->slug, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($actor, $supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => '10000'],
        ]);
        $orders->markSent($actor, $po);
        app(ReceiptService::class)->store($actor, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty],
        ]);

        return $product;
    }

    public function test_transfer_to_another_tenants_branch_is_rejected(): void
    {
        [$tenantA, $adminA, $aOne] = $this->tenantWithTwoBranches('a');
        [, , $bOne] = $this->tenantWithTwoBranches('b');
        $product = $this->stockAt($adminA, $tenantA, $aOne, 5);

        try {
            app(TransferService::class)->sendFromBranchStock($adminA, $aOne->id, $bOne->id, [
                ['product_id' => $product->id, 'qty' => 1],
            ]);
            $this->fail('Transfer lintas tenant seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('to_branch_id', $e->errors());
        }
        $this->assertDatabaseCount('stock_transfers', 0);
    }

    public function test_branch_staff_can_transfer_within_own_tenant(): void
    {
        [$tenantA, $adminA, $aOne, $aTwo] = $this->tenantWithTwoBranches('a');
        $product = $this->stockAt($adminA, $tenantA, $aOne, 5);

        $staff = User::create([
            'email_verified_at' => now(),            'name' => 'Staf A', 'username' => 'staf-a',
            'email' => 'staf-a@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenantA->id,
        ]);
        $staff->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $staff->branches()->attach($aOne);

        $transfer = app(TransferService::class)->sendFromBranchStock($staff, $aOne->id, $aTwo->id, [
            ['product_id' => $product->id, 'qty' => 2],
        ]);

        $this->assertSame($aTwo->id, (int) $transfer->to_branch_id);
    }

    public function test_offline_device_registration_is_company_fenced(): void
    {
        [$tenantA, $adminA, $aOne] = $this->tenantWithTwoBranches('a');
        [, $adminB, $bOne] = $this->tenantWithTwoBranches('b');
        $service = app(OfflineDeviceService::class);

        try {
            $service->register($adminA, 'Perangkat Siluman', $bOne->id, $adminA->id);
            $this->fail('Perangkat di cabang tenant lain seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('offline_devices', 0);
        }

        try {
            $service->register($adminA, 'Perangkat Siluman', $aOne->id, $adminB->id);
            $this->fail('Perangkat atas akun tenant lain seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('offline_devices', 0);
        }

        $issued = $service->register($adminA, 'Kasir A1', $aOne->id, $adminA->id);
        $this->assertNotEmpty($issued['plain_token']);
        $this->assertDatabaseCount('offline_devices', 1);
    }

    public function test_offline_device_admin_page_hides_other_tenants(): void
    {
        [, $adminA, $aOne] = $this->tenantWithTwoBranches('a');
        [, $adminB, $bOne] = $this->tenantWithTwoBranches('b');
        OfflineDevice::create([
            'name' => 'Kasir B Rahasia', 'branch_id' => $bOne->id, 'user_id' => $adminB->id,
            'token_hash' => hash('sha256', 'token-b'), 'created_by' => $adminB->id,
        ]);

        $this->actingAs($adminA)->get(route('offline-devices.index'))
            ->assertOk()
            ->assertDontSee('Kasir B Rahasia')
            ->assertDontSee('Cabang b Satu');
    }

    public function test_filter_lists_hide_other_tenant_branch_names(): void
    {
        [, $adminA] = $this->tenantWithTwoBranches('a');
        $this->tenantWithTwoBranches('b');

        $this->actingAs($adminA)->get(route('prescriptions.index'))
            ->assertOk()
            ->assertDontSee('Cabang b Satu');

        $this->actingAs($adminA)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertDontSee('Cabang b Satu')
            ->assertDontSee('admin-b');
    }

    /**
     * Putaran koreksi audit independen, temuan 3: aktor UNASSIGNED
     * (akun tanpa tenant pada instalasi BERSAMA, walau memegang
     * peran berizin) tidak boleh memperoleh daftar pengguna mana
     * pun — sebelumnya query daftar hanya menyaring mode tenant &
     * standalone, sehingga mode ini menerima seluruh pengguna.
     */
    public function test_unassigned_actor_gets_empty_user_lists(): void
    {
        $this->tenantWithTwoBranches('a');
        $this->tenantWithTwoBranches('b');

        $unassigned = User::create([
            'email_verified_at' => now(),            'name' => 'Akun Yatim', 'username' => 'yatim',
            'email' => 'yatim@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        // Halaman perangkat offline: tak ada nama staf tenant mana pun.
        $this->actingAs($unassigned)->get(route('offline-devices.index'))
            ->assertOk()
            ->assertDontSee('Admin a')
            ->assertDontSee('Admin b');

        // Halaman log aktivitas: daftar filter pengguna kosong.
        $this->actingAs($unassigned)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertDontSee('Admin a')
            ->assertDontSee('Admin b');

        // Scope pengelolaan pengguna ikut gagal-tertutup.
        $this->assertCount(0, User::visibleInTenantManagement($unassigned)->get());

        // Penjaga query bersama: mode unassigned = kosong, mode
        // platform = pengawasan penuh, mode tenant = miliknya saja.
        $this->assertCount(0, TenantContext::for($unassigned)
            ->constrainByTenant(User::query())->get());
        $this->assertGreaterThan(0, TenantContext::for($this->platform)
            ->constrainByTenant(User::query())->count());
    }

    /** Pencabutan perangkat tenant lain lewat HTTP ditolak (403). */
    public function test_revoke_other_tenants_device_is_forbidden(): void
    {
        [, $adminA] = $this->tenantWithTwoBranches('a');
        [, $adminB, $bOne] = $this->tenantWithTwoBranches('b');
        $device = OfflineDevice::create([
            'name' => 'Kasir B', 'branch_id' => $bOne->id, 'user_id' => $adminB->id,
            'token_hash' => hash('sha256', 'token-b2'), 'created_by' => $adminB->id,
        ]);

        $this->actingAs($adminA)->post(route('offline-devices.revoke', $device))
            ->assertForbidden();
        $this->assertNull($device->fresh()->revoked_at);
    }
}
