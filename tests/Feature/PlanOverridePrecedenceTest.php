<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit keamanan 17 Nov 2026, temuan F-1: bila sebuah fitur punya
 * lebih dari satu override yang masih berlaku, override TERBARU
 * harus menang. Riwayat override tetap append-only (jejak audit);
 * yang berubah hanya aturan presedensinya di effectiveFeatures().
 */
class PlanOverridePrecedenceTest extends TestCase
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

    private function tenant(string $tag, string $planKey = 'free'): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        $this->plans->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'email_verified_at' => now(), 'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return $tenant;
    }

    public function test_latest_boolean_override_wins_when_locking_after_opening(): void
    {
        $tenant = $this->tenant('buka-kunci');
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => true], null, 'Dibuka dulu', $this->platform);
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => false], null, 'Dikunci belakangan', $this->platform);

        $this->assertFalse($this->plans->featureEnabled($tenant, 'whatsapp'));
    }

    public function test_latest_boolean_override_wins_when_opening_after_locking(): void
    {
        $tenant = $this->tenant('kunci-buka');
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => false], null, 'Dikunci dulu', $this->platform);
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => true], null, 'Dibuka belakangan', $this->platform);

        $this->assertTrue($this->plans->featureEnabled($tenant, 'whatsapp'));
    }

    public function test_latest_limit_override_wins(): void
    {
        $tenant = $this->tenant('batas');
        $this->plans->setOverride($tenant, 'users_max', ['limit_value' => 9], null, 'Naikkan batas', $this->platform);
        $this->plans->setOverride($tenant, 'users_max', ['limit_value' => 3], null, 'Turunkan lagi', $this->platform);

        $this->assertSame(3, $this->plans->featureLimit($tenant, 'users_max'));
    }

    public function test_expired_latest_override_falls_back_to_older_valid_one(): void
    {
        $tenant = $this->tenant('kedaluwarsa');
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => true], null, 'Buka tanpa batas waktu', $this->platform);
        $this->plans->setOverride($tenant, 'whatsapp', ['enabled' => false], now()->subDay(), 'Kunci sementara yang sudah lewat', $this->platform);

        $this->assertTrue($this->plans->featureEnabled($tenant, 'whatsapp'));
    }
}
