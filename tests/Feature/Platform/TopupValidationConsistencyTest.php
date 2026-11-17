<?php

namespace Tests\Feature\Platform;

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
 * Audit keamanan 17 Nov 2026, temuan F-6: validasi isi ulang di
 * controller (1–100000) sempat berbeda dari service (10–10000),
 * sehingga pengajuan 1–9 dan 10001–100000 lolos formulir lalu
 * ditolak service. Kini controller membaca konstanta service —
 * satu sumber aturan untuk controller, service, dan formulir.
 */
class TopupValidationConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    private function tenantOwner(string $tag, string $planKey): User
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'email_verified_at' => now(), 'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return $owner;
    }

    public function test_payg_topup_range_is_enforced_at_the_form(): void
    {
        $owner = $this->tenantOwner('vpg', 'payg');

        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 5])
            ->assertSessionHasErrors('credits');
        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 10001])
            ->assertSessionHasErrors('credits');
        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 100])
            ->assertSessionHasNoErrors();
    }

    public function test_ai_topup_range_is_enforced_at_the_form(): void
    {
        // Best Offer membawa fitur ai_assistant (gerbang paket controller).
        $owner = $this->tenantOwner('vai', 'best_offer');

        $this->actingAs($owner)->post(route('ai-credits.store'), ['questions' => 9])
            ->assertSessionHasErrors('questions');
        $this->actingAs($owner)->post(route('ai-credits.store'), ['questions' => 10001])
            ->assertSessionHasErrors('questions');
        $this->actingAs($owner)->post(route('ai-credits.store'), ['questions' => 10])
            ->assertSessionHasNoErrors();
    }
}
