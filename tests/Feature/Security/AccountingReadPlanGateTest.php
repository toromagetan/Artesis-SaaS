<?php

namespace Tests\Feature\Security;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit keamanan 17 Nov 2026, temuan F-4: halaman baca akuntansi
 * (jurnal, buku besar, bagan akun — termasuk ekspor, cetak, unduh,
 * dan balik jurnal) sempat hanya bergerbang izin, tanpa gerbang
 * paket accounting_full, sehingga tenant tanpa fitur akuntansi bisa
 * membukanya lewat URL langsung. Kini semua bergerbang paket;
 * gembok menu (PR #65) bukan lagi satu-satunya pagar.
 */
class AccountingReadPlanGateTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User} */
    private function tenantOwner(string $tag, string $planKey): array
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

        return [$tenant, $owner];
    }

    private function journalFor(Tenant $tenant, string $number): Journal
    {
        $id = DB::table('journals')->insertGetId([
            'number' => $number, 'journal_date' => now()->toDateString(),
            'source_type' => 'manual', 'description' => 'Jurnal uji '.$number,
            'tenant_id' => $tenant->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Journal::withoutGlobalScopes()->findOrFail($id);
    }

    public function test_team_plan_is_denied_on_all_accounting_read_endpoints(): void
    {
        [$tenant, $owner] = $this->tenantOwner('gtm', 'team');
        $journal = $this->journalFor($tenant, 'JU-GTM-1');
        $account = Account::firstOrFail();

        $this->actingAs($owner)->get(route('journals.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('journals.export'))->assertForbidden();
        $this->actingAs($owner)->get(route('journals.show', $journal))->assertForbidden();
        $this->actingAs($owner)->get(route('journals.print', $journal))->assertForbidden();
        $this->actingAs($owner)->get(route('journals.download', $journal))->assertForbidden();
        $this->actingAs($owner)->post(route('journals.reverse', $journal))->assertForbidden();
        $this->actingAs($owner)->get(route('ledger.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('accounts.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('accounts.show', $account))->assertForbidden();
    }

    public function test_best_offer_and_payg_plans_keep_access(): void
    {
        [$tenantBest, $best] = $this->tenantOwner('gbt', 'best_offer');
        $journal = $this->journalFor($tenantBest, 'JU-GBT-1');
        $this->actingAs($best)->get(route('journals.index'))->assertOk();
        $this->actingAs($best)->get(route('journals.show', $journal))->assertOk();
        $this->actingAs($best)->get(route('ledger.index'))->assertOk();
        $this->actingAs($best)->get(route('accounts.index'))->assertOk();

        // PAYG membuka accounting_full (keputusan owner 16 Nov 2026).
        [, $payg] = $this->tenantOwner('gpg', 'payg');
        $this->actingAs($payg)->get(route('journals.index'))->assertOk();
        $this->actingAs($payg)->get(route('accounts.index'))->assertOk();
    }

    public function test_standalone_owner_is_unaffected(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $this->actingAs($owner)->get(route('journals.index'))->assertOk();
        $this->actingAs($owner)->get(route('ledger.index'))->assertOk();
        $this->actingAs($owner)->get(route('accounts.index'))->assertOk();
    }
}
