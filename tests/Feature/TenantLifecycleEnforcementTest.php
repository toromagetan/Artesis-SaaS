<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantBillingRequest;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S-01 — Penegakan lifecycle tenant: begitu platform menonaktifkan
 * tenant, penggunanya berhenti beroperasi pada request berikutnya
 * (web dialihkan ke Paket Saya; JSON/API ditolak), token API ikut
 * mati, dan hanya jalur pemulihan yang tetap terbuka: logout,
 * halaman Paket Saya, dan pengajuan tagihan. Tenant lain, aktor
 * platform, dan instalasi standalone tidak terpengaruh; aktivasi
 * kembali memulihkan akses.
 */
class TenantLifecycleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        $this->tenant = Tenant::create([
            'name' => 'Optik Siklus', 'slug' => 'optik-siklus', 'email' => 'siklus@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $this->tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang Siklus', 'tenant_id' => $this->tenant->id]);
        $this->admin = User::create([
            'email_verified_at' => now(),            'name' => 'Admin Siklus', 'username' => 'admin-siklus',
            'email' => 'admin-siklus@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $this->tenant->id,
        ]);
        $this->admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $this->admin->branches()->attach($branch);
    }

    private function suspend(): void
    {
        $this->tenant->update(['is_active' => false]);
    }

    public function test_active_tenant_user_works_normally(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $this->actingAs($this->admin)->get('/paket-saya')->assertOk();
    }

    public function test_suspended_tenant_user_is_redirected_to_my_plan(): void
    {
        $this->suspend();

        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertRedirect(route('plans.my'));
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertSessionHas('status', __('ui.plans.tenant_suspended'));
        // Jalur JSON pada aplikasi web juga tertutup (bukan redirect).
        $this->actingAs($this->admin)
            ->getJson('/dashboard/data')
            ->assertForbidden();
    }

    public function test_suspended_tenant_keeps_recovery_paths(): void
    {
        $this->suspend();

        // Halaman Paket Saya tetap terbuka...
        $this->actingAs($this->admin)->get('/paket-saya')->assertOk();

        // ...dan pengajuan tagihan (jalur pembayaran) tetap jalan.
        $team = Plan::where('key', 'team')->firstOrFail();
        $this->actingAs($this->admin)
            ->post('/paket-saya/tagihan', ['plan_id' => $team->id, 'months' => 1])
            ->assertRedirect(route('plans.my'));
        $this->assertSame(
            1,
            TenantBillingRequest::where('tenant_id', $this->tenant->id)
                ->where('status', TenantBillingRequest::STATUS_PENDING)->count(),
        );

        // ...serta logout.
        $this->actingAs($this->admin)->post('/logout')->assertRedirect();
        $this->assertGuest();
    }

    public function test_reactivation_restores_access(): void
    {
        $this->suspend();
        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('plans.my'));

        $this->tenant->update(['is_active' => true]);
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    }

    public function test_other_tenant_and_platform_unaffected_by_suspension(): void
    {
        $other = Tenant::create([
            'name' => 'Optik Lain', 'slug' => 'optik-lain', 'email' => 'lain@uji.id',
        ]);
        $otherAdmin = User::create([
            'email_verified_at' => now(),            'name' => 'Admin Lain', 'username' => 'admin-lain',
            'email' => 'admin-lain@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $other->id,
        ]);
        $otherAdmin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->suspend();

        $this->actingAs($otherAdmin)->get('/dashboard')->assertOk();
        $this->actingAs($this->platform)->get('/platform/paket')->assertOk();
    }

    public function test_api_token_stops_when_tenant_suspended_and_resumes_on_reactivation(): void
    {
        $issued = app(ApiTokenService::class)->issue($this->admin, 'Uji Siklus', ['products.read']);
        $call = fn () => $this->withHeader('Authorization', 'Bearer '.$issued['plain'])
            ->getJson('/api/v1/products');

        $call()->assertOk();

        $this->suspend();
        $call()->assertForbidden();

        $this->tenant->update(['is_active' => true]);
        $call()->assertOk();
    }

    public function test_api_token_stops_when_creator_deactivated(): void
    {
        $issued = app(ApiTokenService::class)->issue($this->admin, 'Uji Nonaktif', ['products.read']);

        $this->admin->update(['is_active' => false]);

        $this->withHeader('Authorization', 'Bearer '.$issued['plain'])
            ->getJson('/api/v1/products')
            ->assertUnauthorized();
    }
}
