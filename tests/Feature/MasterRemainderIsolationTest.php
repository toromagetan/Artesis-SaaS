<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul C — Master lanjutan per tenant (audit ulang isolasi
 * 10 Nov 2026): dokter dan level membership menyusul pola S-02.
 * Dokter tenant lain tidak terlihat, tidak dapat diubah, dan
 * tidak dapat dirujuk penjualan; level membership satu tenant
 * tidak mengubah program tenant lain; di dalam tenant, evaluasi
 * poin memakai level tenant itu sendiri.
 */
class MasterRemainderIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
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
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    public function test_doctors_do_not_cross_tenants(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB, $adminB] = $this->tenantAdmin('b');

        $this->actingAs($adminA)->post(route('doctors.store'), [
            'name' => 'dr. Rahasia A', 'clinic' => 'Klinik A', 'commission_rate' => 7.5,
        ])->assertRedirect();
        $doctorA = Doctor::withoutGlobalScopes()->where('name', 'dr. Rahasia A')->firstOrFail();
        $this->assertSame($tenantA->id, (int) $doctorA->tenant_id);

        $this->actingAs($adminB)->get(route('doctors.index'))
            ->assertOk()->assertDontSee('dr. Rahasia A');

        $this->actingAs($adminB)->get(route('doctors.edit', $doctorA))
            ->assertNotFound();

        $this->actingAs($adminB)->put(route('doctors.update', $doctorA), [
            'name' => 'dr. Dibajak', 'commission_rate' => 99,
        ])->assertNotFound();
        $this->assertSame('dr. Rahasia A', $doctorA->refresh()->name);
        $this->assertSame($tenantB->id, (int) $adminB->tenant_id);
    }

    public function test_sale_cannot_reference_another_tenants_doctor(): void
    {
        [, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        $foreignDoctor = Doctor::create([
            'name' => 'dr. Milik B', 'commission_rate' => 10, 'is_active' => true,
            'tenant_id' => $tenantB->id,
        ]);
        $customer = Customer::create([
            'name' => 'Pelanggan A', 'phone' => '0811000', 'branch_id' => $branchA->id,
            'tenant_id' => $adminA->tenant_id,
        ]);
        $product = Product::create([
            'type' => 'frame', 'name' => 'Frame A', 'brand' => 'Uji',
            'sku' => 'SKU-A-DOC', 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $adminA->tenant_id,
        ]);

        $this->actingAs($adminA);
        try {
            app(SalesService::class)->createSale($adminA, [
                'customer_id' => $customer->id,
                'branch_id' => $branchA->id,
                'sale_date' => now()->format('Y-m-d'),
                'doctor_id' => $foreignDoctor->id,
                'tax_mode' => 'inclusive',
            ], [['product_id' => $product->id, 'qty' => 1, 'unit_price' => '20000']]);
            $this->fail('Penjualan seharusnya menolak dokter tenant lain.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('sales', 0);
        }
    }

    public function test_membership_levels_do_not_cross_tenants(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');

        $this->actingAs($adminA)->post(route('members.levels.store'), [
            'name' => 'Platinum A', 'min_accumulation' => 1000000,
            'point_multiplier' => 3, 'benefits' => 'Khusus A',
        ])->assertRedirect();
        $levelA = MembershipLevel::withoutGlobalScopes()->where('name', 'Platinum A')->firstOrFail();
        $this->assertSame($tenantA->id, (int) $levelA->tenant_id);

        $this->actingAs($adminB)->get(route('members.levels'))
            ->assertOk()->assertDontSee('Platinum A');
        $this->assertNull(MembershipLevel::find($levelA->id));

        // Level pelanggan A terselesaikan untuk tenantnya sendiri.
        $customerA = Customer::create([
            'name' => 'Member A', 'phone' => '0822000', 'branch_id' => $branchA->id,
            'tenant_id' => $tenantA->id, 'membership_level_id' => $levelA->id,
        ]);
        $this->actingAs($adminA);
        $this->assertSame('Platinum A', app(PointsService::class)->currentLevel($customerA)?->name);
    }

    /**
     * Data warisan bertenant NULL (dibuat sebelum Modul C) hanya
     * milik instalasi mandiri/platform — tenant mana pun tidak
     * boleh melihatnya (pola S-02 yang sama).
     */
    public function test_legacy_null_master_rows_are_invisible_to_tenants(): void
    {
        [, $adminA] = $this->tenantAdmin('a');
        $this->tenantAdmin('b');

        $legacyDoctor = Doctor::withoutGlobalScopes()->create([
            'name' => 'dr. Warisan Lama', 'commission_rate' => 5,
            'is_active' => true, 'tenant_id' => null,
        ]);
        $legacyLevel = MembershipLevel::withoutGlobalScopes()->create([
            'name' => 'Level Warisan', 'min_accumulation' => 1,
            'point_multiplier' => 1, 'tenant_id' => null,
        ]);

        $this->actingAs($adminA)->get(route('doctors.index'))
            ->assertOk()->assertDontSee('dr. Warisan Lama');
        $this->assertNull(Doctor::find($legacyDoctor->id));
        $this->assertNull(MembershipLevel::find($legacyLevel->id));

        // Platform tetap dapat melihatnya untuk pengawasan.
        $this->actingAs($this->platform);
        $this->assertNotNull(Doctor::find($legacyDoctor->id));
    }

    /**
     * Temuan 6 (koreksi audit independen): tenant yang masih
     * memiliki dokter/level membership TIDAK dapat dihapus —
     * kepemilikan master tidak boleh berubah menjadi NULL.
     */
    public function test_tenant_with_master_data_cannot_be_deleted(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');

        $this->actingAs($adminA)->post(route('doctors.store'), [
            'name' => 'dr. Penjaga A', 'commission_rate' => 5,
        ])->assertRedirect();
        $doctor = Doctor::withoutGlobalScopes()->where('name', 'dr. Penjaga A')->firstOrFail();

        try {
            $tenantA->delete();
            $this->fail('Penghapusan tenant bermaster seharusnya ditolak basis data.');
        } catch (QueryException) {
        }
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);
        $this->assertSame($tenantA->id, (int) $doctor->fresh()->tenant_id);
    }
}
