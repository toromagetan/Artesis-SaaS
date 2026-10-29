<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantFeatureOverride;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * M3 — Fondasi SaaS: paket bertingkat (harga ketetapan CEO 2026-10-29),
 * panel platform (gerbang ganda is_platform + izin), penetapan paket
 * ke tenant, fitur efektif (paket + override berbatas waktu), audit.
 */
class SaasPlatformTest extends TestCase
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

    public function test_default_plans_match_ceo_pricing_and_limits(): void
    {
        $this->assertSame(4, Plan::count());

        $free = Plan::where('key', 'free')->firstOrFail();
        $solo = Plan::where('key', 'solo')->firstOrFail();
        $team = Plan::where('key', 'team')->firstOrFail();
        $best = Plan::where('key', 'best_offer')->firstOrFail();

        $this->assertSame(0, $free->price_monthly);
        $this->assertSame(49000, $solo->price_monthly);
        $this->assertSame(129000, $team->price_monthly);
        $this->assertSame(199000, $best->price_monthly);
        $this->assertSame(99000, $best->extra_branch_price);

        $tenant = Tenant::create(['name' => 'Uji', 'slug' => 'uji']);
        // Tanpa langganan: bawaan Free — produk maks 500, WA mati.
        $this->assertSame(500, $this->plans->featureLimit($tenant, 'products_max'));
        $this->assertFalse($this->plans->featureEnabled($tenant, 'whatsapp'));

        $this->plans->assignPlan($tenant, $solo, null, $this->platform);
        $this->assertSame(2, $this->plans->featureLimit($tenant, 'users_max'));
        $this->assertNull($this->plans->featureLimit($tenant, 'products_max'));
        $this->assertTrue($this->plans->featureEnabled($tenant, 'reports_advanced'));
        $this->assertFalse($this->plans->featureEnabled($tenant, 'crm_members'));

        $this->plans->assignPlan($tenant, $best, null, $this->platform);
        $this->assertTrue($this->plans->featureEnabled($tenant, 'whatsapp'));
        $this->assertTrue($this->plans->featureEnabled($tenant, 'accounting_full'));
        $this->assertNull($this->plans->featureLimit($tenant, 'branches_max'));
    }

    public function test_platform_user_can_open_and_update_plans_panel(): void
    {
        $solo = Plan::where('key', 'solo')->firstOrFail();

        $this->actingAs($this->platform)
            ->get('/platform/paket')
            ->assertOk()
            ->assertSee('Paket Langganan')
            ->assertSee('Best Offer');

        $payload = ['plans' => []];
        foreach (Plan::with('features')->get() as $plan) {
            $features = [];
            foreach ($plan->features as $f) {
                $features[$f->feature_key] = $f->enabled !== null ? ($f->enabled ? '1' : '0') : ($f->limit_value ?? '');
            }
            $payload['plans'][$plan->id] = [
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'extra_branch_price' => $plan->extra_branch_price,
                'is_active' => $plan->is_active ? '1' : '0',
                'features' => $features,
            ];
        }
        $payload['plans'][$solo->id]['price_monthly'] = 59000;
        $payload['plans'][$solo->id]['features']['whatsapp'] = '1';

        $this->actingAs($this->platform)
            ->put('/platform/paket', $payload)
            ->assertRedirect();

        $solo->refresh();
        $this->assertSame(59000, $solo->price_monthly);
        $this->assertTrue($solo->featuresMap()['whatsapp']['enabled']);
    }

    public function test_tenant_owner_is_denied_platform_panel_despite_wildcard_role(): void
    {
        // Peran Owner = '*' (semua izin registry) — gerbang is_platform
        // adalah batas kerasnya.
        $this->actingAs($this->owner)->get('/platform/paket')->assertForbidden();
        $this->actingAs($this->owner)->get('/platform/tenant')->assertForbidden();
    }

    public function test_platform_user_without_plan_permission_is_denied(): void
    {
        $this->platform->roles()->detach();

        $this->actingAs($this->platform)->get('/platform/paket')->assertForbidden();
        $this->actingAs($this->platform)->get('/platform/tenant')->assertForbidden();
    }

    public function test_platform_user_is_still_denied_business_pages(): void
    {
        $this->actingAs($this->platform)->get('/penjualan')->assertForbidden();
        $this->actingAs($this->platform)->get('/pengguna')->assertForbidden();
    }

    public function test_tenant_flow_create_assign_override_and_audit(): void
    {
        $this->actingAs($this->platform)
            ->post('/platform/tenant', ['name' => 'Optik Cerah', 'email' => 'halo@cerah.id'])
            ->assertRedirect();

        $tenant = Tenant::where('slug', 'optik-cerah')->firstOrFail();

        $this->actingAs($this->platform)
            ->get('/platform/tenant')
            ->assertOk()
            ->assertSee('Optik Cerah');
        $this->actingAs($this->platform)
            ->get('/platform/tenant/buat')
            ->assertOk();
        $this->actingAs($this->platform)
            ->get("/platform/tenant/{$tenant->id}")
            ->assertOk()
            ->assertSee('Fitur Efektif')
            ->assertSee('Tetapkan Paket');

        $best = Plan::where('key', 'best_offer')->firstOrFail();
        $this->actingAs($this->platform)
            ->post("/platform/tenant/{$tenant->id}/paket", ['plan_id' => $best->id])
            ->assertRedirect();

        $this->assertTrue($this->plans->featureEnabled($tenant, 'whatsapp'));
        $this->assertTrue(AuditLog::where('action', 'tenant.plan_assigned')
            ->where('subject_id', $tenant->id)->exists());

        // Override mematikan WA selama masa promo — alasan wajib.
        $this->actingAs($this->platform)
            ->post("/platform/tenant/{$tenant->id}/pengecualian", [
                'feature_key' => 'whatsapp', 'enabled' => '0',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->platform)
            ->post("/platform/tenant/{$tenant->id}/pengecualian", [
                'feature_key' => 'whatsapp', 'enabled' => '0',
                'expires_at' => now()->addDays(7)->toDateString(),
                'reason' => 'Uji coba tanpa WA',
            ])
            ->assertRedirect();

        $this->assertFalse($this->plans->featureEnabled($tenant, 'whatsapp'));
        $this->assertTrue(AuditLog::where('action', 'tenant.feature_override')
            ->where('subject_id', $tenant->id)->exists());

        // Override kedaluwarsa tidak berlaku.
        TenantFeatureOverride::create([
            'tenant_id' => $tenant->id, 'feature_key' => 'accounting_full',
            'enabled' => false, 'expires_at' => Carbon::yesterday(),
            'reason' => 'Sudah lewat', 'created_by' => $this->platform->id,
        ]);
        $this->assertTrue($this->plans->featureEnabled($tenant, 'accounting_full'));
    }

    public function test_tenant_toggle_active_and_expired_subscription_falls_back_to_free(): void
    {
        $tenant = Tenant::create(['name' => 'Optik Uji', 'slug' => 'optik-uji']);
        $solo = Plan::where('key', 'solo')->firstOrFail();
        $this->plans->assignPlan($tenant, $solo, Carbon::yesterday(), $this->platform);

        // Langganan sudah kedaluwarsa kemarin → fitur efektif = Free.
        $this->assertSame(500, $this->plans->featureLimit($tenant, 'products_max'));

        $this->actingAs($this->platform)
            ->post("/platform/tenant/{$tenant->id}/alih-aktif")
            ->assertRedirect();
        $this->assertFalse($tenant->refresh()->is_active);
    }
}
