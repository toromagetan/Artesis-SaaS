<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantBillingRequest;
use App\Modules\Platform\Services\BillingService;
use App\Modules\Platform\Services\PlanGate;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M5 — Pendaftaran mandiri tenant (bisnis baru + pemilik + cabang
 * pertama + paket Free, langsung masuk) dan penagihan sederhana
 * (transfer manual: admin tenant mengajukan, platform mengonfirmasi
 * → paket ditetapkan; tolak → paket tidak berubah).
 */
class SaasSignupBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $owner;

    private PlanService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->plans = app(PlanService::class);
    }

    private function signupPayload(array $override = []): array
    {
        return array_merge([
            'business_name' => 'Optik Sejahtera',
            'owner_name' => 'Budi Sejahtera',
            'email' => 'budi@sejahtera.id',
            'phone' => '081234567890',
            'branch_name' => 'Sejahtera Pusat',
            'password' => 'rahasia-uji-123',
            'password_confirmation' => 'rahasia-uji-123',
        ], $override);
    }

    /** Tenant + admin Owner dengan N cabang, paket Free. */
    private function tenantAdmin(int $branches = 1, string $slug = 'optik-uji'): array
    {
        $tenant = Tenant::create(['name' => 'Optik Uji', 'slug' => $slug, 'email' => $slug.'@uji.id']);
        $this->plans->assignPlan($tenant, Plan::where('key', 'free')->firstOrFail(), null, $this->platform);
        $admin = User::create([
            'name' => 'Admin Uji', 'username' => 'admin-'.$slug, 'email' => 'admin@'.$slug.'.id',
            'password' => bcrypt('rahasia-uji'), 'is_active' => true, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        for ($i = 1; $i <= $branches; $i++) {
            $branch = Branch::create(['name' => 'Cabang '.$i.' '.$slug, 'tenant_id' => $tenant->id]);
            $admin->branches()->attach($branch);
        }

        return [$tenant, $admin];
    }

    public function test_signup_page_is_public_and_landing_links_to_it(): void
    {
        $this->get('/')->assertOk()->assertSee('/daftar');
        $this->get('/daftar')->assertOk()->assertSee('Daftar Artesis Gratis');
        $this->actingAs($this->owner)->get('/daftar')->assertRedirect('/dashboard');
    }

    public function test_signup_creates_tenant_branch_owner_and_free_plan(): void
    {
        $response = $this->post('/daftar', $this->signupPayload());
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $tenant = Tenant::where('slug', 'optik-sejahtera')->firstOrFail();
        $user = User::where('email', 'budi@sejahtera.id')->firstOrFail();
        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasRole('Owner'));
        $this->assertSame($user->id, auth()->id());

        $branch = Branch::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame('Sejahtera Pusat', $branch->name);
        $this->assertTrue($user->branches->contains($branch));

        $this->assertSame('free', $tenant->currentPlan()?->key);
        // Batas Free langsung berlaku untuk pemilik baru.
        $gate = app(PlanGate::class);
        $this->assertSame(500, $gate->limit($user, 'products_max'));
        $this->assertFalse($gate->enabled($user, 'whatsapp'));
    }

    public function test_signup_slugs_and_usernames_stay_unique(): void
    {
        $this->post('/daftar', $this->signupPayload());
        auth()->logout();

        $this->post('/daftar', $this->signupPayload([
            'email' => 'lain@sejahtera.id',
        ]));

        $this->assertSame(2, Tenant::where('name', 'Optik Sejahtera')->count());
        $slugs = Tenant::where('name', 'Optik Sejahtera')->pluck('slug')->all();
        $this->assertSame(['optik-sejahtera', 'optik-sejahtera-2'], $slugs);
        $this->assertSame(2, User::whereIn('email', ['budi@sejahtera.id', 'lain@sejahtera.id'])->count());
    }

    public function test_signup_rejects_duplicate_email_and_weak_password(): void
    {
        $this->post('/daftar', $this->signupPayload());
        auth()->logout();

        $this->post('/daftar', $this->signupPayload(['business_name' => 'Optik Lain']))
            ->assertSessionHasErrors('email');
        $this->assertSame(1, Tenant::count());

        $this->post('/daftar', $this->signupPayload([
            'email' => 'baru@lain.id', 'password' => 'abc', 'password_confirmation' => 'abc',
        ]))->assertSessionHasErrors('password');
        $this->assertSame(1, Tenant::count());
    }

    public function test_billing_math_includes_extra_branches_and_months(): void
    {
        [$tenant, $admin] = $this->tenantAdmin(branches: 3);
        $billing = app(BillingService::class);
        $best = Plan::where('key', 'best_offer')->firstOrFail();

        // 199.000 + 2 cabang tambahan × 99.000 = 397.000 per bulan.
        $this->assertSame(397000, $billing->monthlyTotal($best, 3));
        $this->assertSame(199000, $billing->monthlyTotal($best, 1));

        $request = $billing->createRequest($tenant, $best, 3, 'Transfer BCA 123', $admin);
        $this->assertSame(2, $request->extra_branches);
        $this->assertSame(1191000, $request->amount);
        $this->assertTrue($request->isPending());
    }

    public function test_billing_rejects_free_plan_and_duplicate_pending(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        $billing = app(BillingService::class);
        $free = Plan::where('key', 'free')->firstOrFail();
        $solo = Plan::where('key', 'solo')->firstOrFail();

        try {
            $billing->createRequest($tenant, $free, 1, null, $admin);
            $this->fail('Paket gratis seharusnya tidak bisa ditagih.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $billing->createRequest($tenant, $solo, 1, null, $admin);
        $this->expectException(ValidationException::class);
        $billing->createRequest($tenant, $solo, 1, null, $admin);
    }

    public function test_tenant_admin_submits_upgrade_and_sees_bank_instructions(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        Setting::set('billing_bank_name', 'BCA');
        Setting::set('billing_bank_account', '1234567890');
        Setting::set('billing_account_name', 'Artesis Platform');
        $solo = Plan::where('key', 'solo')->firstOrFail();

        $this->actingAs($admin)->get('/paket-saya')
            ->assertOk()->assertSee('BCA')->assertSee('1234567890');

        $this->actingAs($admin)->post('/paket-saya/tagihan', [
            'plan_id' => $solo->id, 'months' => 1, 'note' => 'Ref 001',
        ])->assertRedirect(route('plans.my'));

        $request = TenantBillingRequest::where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame(49000, $request->amount);

        $this->actingAs($admin)->get('/paket-saya')
            ->assertOk()->assertSee('Permintaan Menunggu Konfirmasi');
    }

    public function test_platform_confirms_and_plan_is_upgraded_with_expiry(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        $best = Plan::where('key', 'best_offer')->firstOrFail();
        $request = app(BillingService::class)->createRequest($tenant, $best, 3, null, $admin);

        $this->actingAs($this->platform)
            ->post(route('platform.billing.confirm', $request))
            ->assertRedirect(route('platform.billing.index'));

        $request->refresh();
        $this->assertSame(TenantBillingRequest::STATUS_CONFIRMED, $request->status);
        $this->assertNotNull($request->subscription_id);
        $this->assertSame('best_offer', $tenant->currentPlan()?->key);
        $this->assertSame(
            now()->addMonths(3)->format('Y-m-d'),
            $tenant->currentSubscription()?->expires_at?->format('Y-m-d'),
        );
        // Fitur Best Offer langsung terbuka untuk admin tenant.
        $this->assertTrue(app(PlanGate::class)->enabled($admin, 'whatsapp'));
    }

    public function test_platform_rejects_and_plan_stays_free(): void
    {
        [$tenant, $admin] = $this->tenantAdmin();
        $solo = Plan::where('key', 'solo')->firstOrFail();
        $request = app(BillingService::class)->createRequest($tenant, $solo, 1, null, $admin);

        $this->actingAs($this->platform)
            ->post(route('platform.billing.reject', $request))
            ->assertRedirect(route('platform.billing.index'));

        $this->assertSame(TenantBillingRequest::STATUS_REJECTED, $request->refresh()->status);
        $this->assertSame('free', $tenant->currentPlan()?->key);
    }

    public function test_billing_panel_is_platform_only_and_settings_round_trip(): void
    {
        [, $admin] = $this->tenantAdmin();

        $this->actingAs($admin)->get('/platform/penagihan')->assertForbidden();
        $this->actingAs($this->owner)->get('/platform/penagihan')->assertForbidden();

        $this->actingAs($this->platform)->get('/platform/penagihan')->assertOk();
        $this->actingAs($this->platform)->put(route('platform.billing.settings'), [
            'billing_bank_name' => 'Mandiri',
            'billing_bank_account' => '987654321',
            'billing_account_name' => 'Artesis',
        ])->assertRedirect(route('platform.billing.index'));
        $this->assertSame('Mandiri', Setting::value('billing_bank_name'));

        // Pemilik instalasi mandiri tidak melihat bagian penagihan.
        $this->actingAs($this->owner)->get('/paket-saya')
            ->assertOk()->assertDontSee('Naik Paket');
    }
}
