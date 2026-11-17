<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantSubscription;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit keamanan 17 Nov 2026, temuan F-5: assignPlan() kini mengunci
 * baris tenant di dalam transaksinya (pola BillingService::confirm)
 * agar penetapan bersamaan tidak menyisakan dua langganan ACTIVE,
 * dan currentSubscription() membaca langganan aktif TERBARU secara
 * deterministik bila data warisan telanjur ganda.
 */
class PlanAssignmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private PlanService $plans;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->plans = app(PlanService::class);
    }

    private function tenant(string $tag): Tenant
    {
        return Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
    }

    private function activeCount(Tenant $tenant): int
    {
        return TenantSubscription::where('tenant_id', $tenant->id)
            ->where('status', TenantSubscription::STATUS_ACTIVE)
            ->count();
    }

    public function test_repeated_assignment_keeps_exactly_one_active_subscription(): void
    {
        $tenant = $this->tenant('beruntun');
        foreach (['free', 'team', 'best_offer'] as $key) {
            $this->plans->assignPlan($tenant, Plan::where('key', $key)->firstOrFail(), null, $this->platform);
            $this->assertSame(1, $this->activeCount($tenant), "Sesudah assign {$key} harus tepat satu langganan aktif.");
        }
        $this->assertSame('best_offer', $tenant->fresh()->currentPlan()?->key);
    }

    public function test_current_subscription_prefers_the_newest_active_row(): void
    {
        $tenant = $this->tenant('warisan');
        $older = $this->plans->assignPlan($tenant, Plan::where('key', 'free')->firstOrFail(), null, $this->platform);
        // Simulasi data warisan: langganan kedua ikut berstatus aktif.
        $newer = TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => Plan::where('key', 'team')->firstOrFail()->id,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'starts_at' => today(),
            'created_by' => $this->platform->id,
        ]);
        $this->assertGreaterThan($older->id, $newer->id);

        $current = $tenant->fresh()->currentSubscription();
        $this->assertSame($newer->id, $current?->id);
        $this->assertSame('team', $current?->plan?->key);
    }
}
