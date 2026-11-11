<?php

namespace Tests\Feature;

use App\Modules\Accounting\Services\CommissionService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Audit lanjutan temuan 8 & 9.
 *
 * Temuan 8: TenantScope (dan pasangannya BranchScope) tidak lagi
 * gagal-buka untuk request HTTP tamu: middleware MarkGuestRequest
 * menandai setiap request web/api anonim sebagai konteks tamu dan
 * scope gagal-tertutup selama penanda itu aktif. Proses sistem
 * (seeder, perintah konsol terjadwal, job antrean) bekerja lewat
 * konteks sistem EKPLISIT TenantContext::asSystem(); pemanggilan
 * in-process terpercaya (layanan berparameter aktor) tidak berubah.
 *
 * Temuan 9: tarif komisi teknisi mengikuti tenant penjualan sumber
 * (bukan sesi aktor / fallback global buta), dan tulisan pengaturan
 * global hanya sah untuk platform, standalone, atau konteks sistem
 * eksplisit — unassigned ditolak.
 */
class GuestScopeAndSettingsIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
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

    private function tenantData(string $tag, Tenant $tenant, Branch $branch): void
    {
        Customer::create([
            'name' => 'Pelanggan '.$tag, 'phone' => '0855'.$tag,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
        Product::create([
            'type' => 'frame', 'name' => 'Frame '.$tag, 'brand' => 'Uji',
            'sku' => 'SKU-GS-'.$tag, 'cost_price' => 10000, 'sale_price' => 20000,
            'is_active' => true, 'tenant_id' => $tenant->id,
        ]);
    }

    public function test_guest_http_cannot_read_tenant_data(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        $this->tenantData('A', $tenantA, $branchA);

        // Rute uji yang membaca model TenantScoped tanpa autentikasi,
        // mewakili endpoint publik mana pun: tamu harus melihat
        // himpunan KOSONG, bukan seluruh data tenant.
        Route::middleware('web')->get('/_uji-tamu', fn () => response()->json([
            'customers' => Customer::count(),
            'products' => Product::count(),
        ]));

        $this->get('/_uji-tamu')
            ->assertOk()
            ->assertExactJson(['customers' => 0, 'products' => 0]);

        // Bukti data memang ada (dibaca tanpa pagar).
        $this->assertSame(1, Customer::withoutGlobalScopes()->count());
        $this->assertSame(1, Product::withoutGlobalScopes()->count());
    }

    public function test_guest_context_fails_closed_while_trusted_calls_still_work(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        $this->tenantData('A', $tenantA, $branchA);
        auth()->logout();

        // Pemanggilan in-process terpercaya (layanan menerima aktor
        // eksplisit) tetap dapat bekerja tanpa sesi.
        $this->assertSame(1, Customer::count());

        // Begitu konteks dinyatakan TAMU (yang dilakukan middleware
        // untuk setiap request HTTP anonim), query yang sama
        // gagal-tertutup — dan penanda tidak bocor sesudah dicabut.
        TenantContext::markGuestHttp();
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Product::count());
        TenantContext::forgetGuestHttp();
        $this->assertSame(1, Customer::count());

        // Konteks sistem eksplisit tetap tersedia untuk proses
        // terpercaya dan dipulihkan sesudah callback selesai.
        $inside = TenantContext::asSystem(fn () => Customer::count());
        $this->assertSame(1, $inside);
        $this->assertFalse(TenantContext::isSystemContext());
    }

    public function test_seeder_runs_as_explicit_system_context(): void
    {
        // DatabaseSeeder dibungkus konteks sistem: menjalankan ulang
        // seeder fondasi tidak menggandakan level membership (model
        // TenantScoped) walau tanpa sesi login sama sekali.
        auth()->logout();
        $this->seed(DatabaseSeeder::class);
        $before = MembershipLevel::withoutGlobalScopes()->count();
        $this->assertGreaterThan(0, $before);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($before, MembershipLevel::withoutGlobalScopes()->count());
    }

    private function saleWithTechnician(Tenant $tenant, User $admin, Branch $branch, User $technician, string $tag): LensProcess
    {
        $customer = Customer::create([
            'name' => 'Pelanggan '.$tag, 'phone' => '0866'.$tag,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
        $sale = Sale::create([
            'number' => 'SL-GS-'.$tag, 'customer_id' => $customer->id,
            'branch_id' => $branch->id, 'cashier_id' => $admin->id,
            'sale_date' => now()->toDateString(),
            'subtotal_amount' => 2000000, 'total_amount' => 2000000,
            'tax_amount' => 0, 'paid_amount' => 2000000, 'status' => 'active',
        ]);

        return LensProcess::create([
            'sale_id' => $sale->id, 'status' => 'done',
            'technician_id' => $technician->id,
            'started_at' => now(), 'completed_at' => now(),
        ]);
    }

    private function staffOf(Tenant $tenant, Branch $branch, string $username): User
    {
        $user = User::create([
            'name' => 'Staf '.$username, 'username' => $username,
            'email' => $username.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $user->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $user->branches()->attach($branch);

        return $user;
    }

    public function test_commission_rate_follows_sale_tenant_without_auth(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate', 'tenant_id' => null], ['value' => '5']);
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate', 'tenant_id' => $tenantA->id], ['value' => '10']);
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate', 'tenant_id' => $tenantB->id], ['value' => '20']);

        $processA = $this->saleWithTechnician($tenantA, $adminA, $branchA, $this->staffOf($tenantA, $branchA, 'tek-gs-a'), 'GA');
        $processB = $this->saleWithTechnician($tenantB, $adminB, $branchB, $this->staffOf($tenantB, $branchB, 'tek-gs-b'), 'GB');

        // Jalur antrean/konsol: tanpa sesi sama sekali, tarif yang
        // dipakai tetap tarif tenant pemilik penjualan (bukan 5%
        // global untuk semua).
        auth()->logout();
        $service = app(CommissionService::class);
        $rowA = $service->accrueForTechnician($processA);
        $rowB = $service->accrueForTechnician($processB);

        $this->assertNotNull($rowA);
        $this->assertSame('10', $rowA->rate);
        $this->assertSame('200000.00', $rowA->amount);
        $this->assertNotNull($rowB);
        $this->assertSame('20', $rowB->rate);
        $this->assertSame('400000.00', $rowB->amount);
    }

    public function test_setting_writes_respect_bucket_ownership(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate', 'tenant_id' => null], ['value' => '5']);

        // Aktor tenant menulis baris MILIKNYA — global & tenant lain
        // tidak berubah.
        $this->actingAs($adminA);
        Setting::set('lab.technician_commission_rate', '15');
        $this->assertSame('15', Setting::valueForTenant('lab.technician_commission_rate', $tenantA->id));
        $this->assertSame('5', Setting::valueForTenant('lab.technician_commission_rate', null));
        $this->assertSame('5', Setting::valueForTenant('lab.technician_commission_rate', $tenantB->id));
        $this->assertSame(0, Setting::query()
            ->where('key', 'lab.technician_commission_rate')
            ->where('tenant_id', $tenantB->id)->count());

        // Platform menulis baris global.
        $this->actingAs($this->platform);
        Setting::set('lab.technician_commission_rate', '7');
        $this->assertSame('7', Setting::valueForTenant('lab.technician_commission_rate', null));
        $this->assertSame('15', Setting::valueForTenant('lab.technician_commission_rate', $tenantA->id));
    }

    public function test_unassigned_and_anonymous_cannot_write_global_setting(): void
    {
        $this->tenantAdmin('a');
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate', 'tenant_id' => null], ['value' => '5']);

        $unassigned = User::create([
            'name' => 'Yatim Setting', 'username' => 'yatim-setting',
            'email' => 'yatim-setting@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->actingAs($unassigned);
        try {
            Setting::set('lab.technician_commission_rate', '99');
            $this->fail('Unassigned seharusnya ditolak menulis pengaturan.');
        } catch (AuthorizationException) {
            $this->assertSame('5', Setting::valueForTenant('lab.technician_commission_rate', null));
        }

        // Request HTTP tamu (penanda middleware): ditolak juga.
        auth()->logout();
        TenantContext::markGuestHttp();
        try {
            Setting::set('lab.technician_commission_rate', '99');
            $this->fail('Tamu HTTP seharusnya ditolak menulis pengaturan.');
        } catch (AuthorizationException) {
            $this->assertSame('5', Setting::valueForTenant('lab.technician_commission_rate', null));
        } finally {
            TenantContext::forgetGuestHttp();
        }

        // Pemanggilan in-process terpercaya (konsol/antrean/seeder)
        // menulis baris global — seluruh jalur tulis produksi berada
        // di balik autentikasi atau proses sistem.
        TenantContext::asSystem(fn () => Setting::set('lab.technician_commission_rate', '6'));
        $this->assertSame('6', Setting::valueForTenant('lab.technician_commission_rate', null));
    }
}
