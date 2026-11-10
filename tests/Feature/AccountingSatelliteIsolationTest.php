<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Commission;
use App\Modules\CRM\Models\Customer;
use App\Modules\Sales\Models\Sale;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\PayrollService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modul B2 — Satelit akuntansi per tenant (audit ulang isolasi
 * 10 Nov 2026): aset tetap, payroll, komisi, dan aturan insentif
 * milik tenant. Payroll B tidak memuat nama/gaji staf A; jurnal
 * depresiasi dari jalur konsol (tanpa aktor) tetap tercap
 * tenant pemilik aset; entri gaji untuk staf tenant lain
 * ditolak di service.
 */
class AccountingSatelliteIsolationTest extends TestCase
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

    public function test_fixed_assets_do_not_cross_tenants(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');

        $this->actingAs($adminA)->post(route('assets.store'), [
            'name' => 'Mesin Faset Rahasia A', 'acquired_at' => '2026-09-01',
            'acquisition_cost' => 12000000, 'useful_life_months' => 24,
        ])->assertRedirect();
        $asset = FixedAsset::withoutGlobalScopes()->where('name', 'Mesin Faset Rahasia A')->firstOrFail();
        $this->assertSame($tenantA->id, (int) $asset->tenant_id);

        $this->actingAs($adminB)->get(route('assets.index'))
            ->assertOk()->assertDontSee('Mesin Faset Rahasia A');
        $this->actingAs($adminB)->get(route('assets.show', $asset))->assertNotFound();
    }

    public function test_console_depreciation_journal_carries_asset_tenant(): void
    {
        [$tenantA] = $this->tenantAdmin('a');
        FixedAsset::create([
            'name' => 'Aset Konsol A', 'acquired_at' => now()->subMonths(2)->toDateString(),
            'acquisition_cost' => 1200000, 'useful_life_months' => 12,
            'method' => 'straight_line', 'accumulated_amount' => 0, 'status' => 'active',
            'tenant_id' => $tenantA->id,
        ]);

        $result = app(FixedAssetService::class)->depreciatePeriod(null, now()->format('Y-m'));

        $this->assertSame(1, $result['posted']);
        $journal = Journal::withoutGlobalScopes()->where('source_type', 'depreciation')->firstOrFail();
        $this->assertSame($tenantA->id, (int) $journal->tenant_id);
    }

    public function test_payroll_entries_are_company_fenced(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');
        $period = now()->format('Y-m');
        $service = app(PayrollService::class);

        try {
            $service->upsertEntry($adminA, $adminB->id, $period, '5000000', '0', '0');
            $this->fail('Entri gaji untuk staf tenant lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('employee', $e->errors());
        }

        // Konteks auth aktif seperti pada permintaan web nyata —
        // cap tenant pada pembuatan baris berasal dari aktor ini.
        $this->actingAs($adminA);
        $entry = $service->upsertEntry($adminA, $adminA->id, $period, '5000000', '250000', '0');
        $this->assertSame($tenantA->id, (int) $entry->tenant_id);

        $this->actingAs($adminB)->get(route('payroll.index', ['periode' => $period]))
            ->assertOk()->assertDontSee('Admin a');
    }

    public function test_commissions_and_incentive_rules_are_tenant_owned(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');
        $doctorA = Doctor::create([
            'name' => 'dr. Komisi Rahasia A', 'commission_rate' => 5,
            'is_active' => true, 'tenant_id' => $tenantA->id,
        ]);
        $customer = Customer::create([
            'name' => 'Pelanggan Komisi A', 'phone' => '0833000',
            'branch_id' => $branchA->id, 'tenant_id' => $tenantA->id,
        ]);
        $sale = Sale::create([
            'number' => 'SL-UJI-KOMISI-A', 'customer_id' => $customer->id,
            'branch_id' => $branchA->id, 'cashier_id' => $adminA->id,
            'doctor_id' => $doctorA->id, 'sale_date' => now()->toDateString(),
            'subtotal_amount' => 1000000, 'total_amount' => 1000000,
            'paid_amount' => 1000000, 'status' => 'active',
        ]);
        Commission::create([
            'type' => 'doctor', 'beneficiary_doctor_id' => $doctorA->id,
            'source_sale_id' => $sale->id,
            'base_amount' => 1000000, 'rate' => 5, 'amount' => 50000,
            'period' => now()->format('Y-m'), 'status' => 'accrued',
            'tenant_id' => $tenantA->id,
        ]);

        $this->actingAs($adminB)->get(route('commissions.index'))
            ->assertOk()->assertDontSee('dr. Komisi Rahasia A');

        $this->actingAs($adminA)->post(route('incentives.rules.store'), [
            'name' => 'Aturan Rahasia A', 'recipient_role' => 'Kasir',
            'calculation_type' => 'percent', 'value' => 2,
            'effective_from' => now()->toDateString(),
        ])->assertRedirect();
        $rule = IncentiveRule::withoutGlobalScopes()->where('name', 'Aturan Rahasia A')->firstOrFail();
        $this->assertSame($tenantA->id, (int) $rule->tenant_id);

        $this->actingAs($adminB);
        $this->assertNull(IncentiveRule::find($rule->id));
    }
}
