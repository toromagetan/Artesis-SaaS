<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tanda gembok menu terkunci paket (keputusan owner 2026-11-15
 * §215): item sidebar yang fitur paketnya mati untuk tenant tampil
 * ber-gembok & TIDAK bisa diklik (span tanpa href), bukan
 * disembunyikan. Lapisan UX — gerbang server plan.feature tetap
 * penegak aslinya. Aktor standalone/platform tidak pernah terkunci.
 */
class SidebarPlanLockTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    /** @return array{Tenant, User} */
    private function tenantOwner(string $tag, string $planKey): array
    {
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

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
            'email_verified_at' => now(),
            'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner];
    }

    private function dashboardHtml(User $user): string
    {
        return $this->actingAs($user)->get(route('dashboard'))->assertOk()->getContent();
    }

    public function test_payg_tenant_only_accounting_items_are_locked(): void
    {
        [, $owner] = $this->tenantOwner('payg', 'payg');
        $html = $this->dashboardHtml($owner);

        // Accounting terkunci: span ber-gembok, tanpa href ke halamannya.
        $this->assertStringContainsString('data-plan-locked="accounting_full"', $html);
        $this->assertStringNotContainsString('href="'.route('accounts.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('financial.balance-sheet').'"', $html);
        $this->assertStringNotContainsString('href="'.route('journals.index').'"', $html);

        // Fitur lain PAYG terbuka semua: tidak ada gembok untuk mereka,
        // dan itemnya tetap tautan normal.
        foreach (['ai_assistant', 'crm_members', 'purchasing_full', 'api_access', 'roles_permissions'] as $feature) {
            $this->assertStringNotContainsString('data-plan-locked="'.$feature.'"', $html, "PAYG tidak boleh mengunci {$feature}.");
        }
        $this->assertStringContainsString('href="'.route('assistant.index').'"', $html);
        $this->assertStringContainsString('href="'.route('members.index').'"', $html);
        $this->assertStringContainsString('href="'.route('pr.index').'"', $html);
        $this->assertStringContainsString('href="'.route('po.index').'"', $html);
        $this->assertStringContainsString('href="'.route('api-tokens.index').'"', $html);
        $this->assertStringContainsString('href="'.route('roles.index').'"', $html);
    }

    public function test_free_tenant_all_mapped_features_are_locked(): void
    {
        [, $owner] = $this->tenantOwner('free', 'free');
        $html = $this->dashboardHtml($owner);

        foreach (['accounting_full', 'ai_assistant', 'crm_members', 'purchasing_full', 'api_access', 'roles_permissions'] as $feature) {
            $this->assertStringContainsString('data-plan-locked="'.$feature.'"', $html, "Free harus mengunci {$feature}.");
        }
        $this->assertStringNotContainsString('href="'.route('accounts.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('assistant.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('members.index').'"', $html);
    }

    public function test_best_offer_tenant_has_no_locked_items(): void
    {
        [, $owner] = $this->tenantOwner('best', 'best_offer');
        $html = $this->dashboardHtml($owner);

        $this->assertStringNotContainsString('data-plan-locked', $html);
        $this->assertStringContainsString('href="'.route('accounts.index').'"', $html);
        $this->assertStringContainsString('href="'.route('financial.balance-sheet').'"', $html);
    }

    public function test_standalone_owner_has_no_locked_items(): void
    {
        // Tanpa satu pun baris tenant, pemilik instalasi = standalone:
        // tidak pernah terkunci (semua fitur menyala seperti perilaku lama).
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();
        $html = $this->dashboardHtml($owner);

        $this->assertStringNotContainsString('data-plan-locked', $html);
        $this->assertStringContainsString('href="'.route('accounts.index').'"', $html);
    }
}
