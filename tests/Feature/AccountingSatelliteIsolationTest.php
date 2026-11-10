<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Services\CommissionService;
use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\PayrollService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
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

    /**
     * Temuan 4 (koreksi audit independen): posting payroll tenant A
     * (3 karyawan) tidak memproses payroll tenant B (2 karyawan);
     * relasi cabang lintas tenant pada karyawan A tidak menarik
     * jurnal ke cabang B; posting ulang tidak menggandakan jurnal;
     * aktor platform ditolak.
     */
    public function test_payroll_posting_processes_only_own_tenant_and_branches(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $period = now()->format('Y-m');
        $service = app(PayrollService::class);

        $staffA1 = $this->staffOf($tenantA, $branchA, 'staf-a1');
        $staffA2 = $this->staffOf($tenantA, $branchA, 'staf-a2');
        // Lampiran cabang lintas tenant yang tercemar: cabang B
        // melekat LEBIH DULU pada karyawan A — dimensi jurnal
        // tetap wajib cabang A.
        $staffA2->branches()->detach($branchA->id);
        $staffA2->branches()->attach($branchB->id);
        $staffA2->branches()->attach($branchA->id);

        $staffB1 = $this->staffOf($tenantB, $branchB, 'staf-b1');
        $staffB2 = $this->staffOf($tenantB, $branchB, 'staf-b2');

        foreach ([[$adminA, $adminA, '4000000'], [$adminA, $staffA1, '3000000'], [$adminA, $staffA2, '2000000']] as [$actor, $employee, $base]) {
            $service->upsertEntry($actor, $employee->id, $period, $base, '0', '0');
        }
        foreach ([[$adminB, $staffB1, '7000000'], [$adminB, $staffB2, '8000000']] as [$actor, $employee, $base]) {
            $service->upsertEntry($actor, $employee->id, $period, $base, '0', '0');
        }
        $this->assertSame(5, Payroll::withoutGlobalScopes()->where('period', $period)->count());

        $journal = $service->postPeriod($adminA, $period, 'cash');
        $this->assertNotNull($journal);
        $this->assertSame($tenantA->id, (int) $journal->tenant_id);

        // Hanya 3 baris A yang terjurnal; 2 baris B tetap menunggu.
        $this->assertSame(3, Payroll::withoutGlobalScopes()->where('period', $period)->whereNotNull('journal_id')->count());
        $this->assertSame(2, Payroll::withoutGlobalScopes()->where('period', $period)->whereNull('journal_id')->count());

        // Dimensi cabang jurnal: hanya cabang A, total persis gaji A.
        $branchIds = $journal->lines()->whereNotNull('branch_id')->pluck('branch_id')->unique()->all();
        $this->assertSame([$branchA->id], array_map('intval', $branchIds));
        $debit = $journal->lines()->sum('debit');
        $this->assertSame('9000000.00', number_format((float) $debit, 2, '.', ''));

        // Posting ulang: tidak ada jurnal kedua.
        $this->assertNull($service->postPeriod($adminA, $period, 'cash'));
        $this->assertSame(1, Journal::withoutGlobalScopes()->where('source_type', 'payroll')->count());

        // Aktor platform ditolak.
        try {
            $service->postPeriod($this->platform, $period, 'cash');
            $this->fail('Posting payroll oleh aktor platform seharusnya ditolak.');
        } catch (AuthorizationException) {
        }
    }

    /**
     * Temuan 4: baris payroll tercampur (cap tenant A, karyawan
     * tenant B) membatalkan posting — bukan diproses diam-diam.
     */
    public function test_payroll_posting_rejects_mixed_tenant_row(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [, $adminB] = $this->tenantAdmin('b');
        $period = now()->format('Y-m');

        Payroll::withoutGlobalScopes()->create([
            'employee_user_id' => $adminB->id, 'period' => $period,
            'base_amount' => '1000000', 'allowance_amount' => '0',
            'deduction_amount' => '0', 'net_amount' => '1000000',
            'created_by' => $adminA->id, 'tenant_id' => $tenantA->id,
        ]);

        try {
            app(PayrollService::class)->postPeriod($adminA, $period, 'cash');
            $this->fail('Baris payroll tercampur tenant seharusnya membatalkan posting.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('employee', $e->errors());
        }
        $this->assertSame(0, Journal::withoutGlobalScopes()->where('source_type', 'payroll')->count());
    }

    /**
     * Temuan 5: komisi teknisi & dokter tercap tenant PENJUALAN
     * sumber walau diproses tanpa sesi auth; teknisi tenant lain
     * tidak menghasilkan komisi dari penjualan tenant ini.
     */
    public function test_commissions_follow_source_sale_tenant_without_auth(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate'], ['value' => '10']);

        $technicianA = $this->staffOf($tenantA, $branchA, 'teknisi-a');
        $technicianB = $this->staffOf($tenantB, $branchB, 'teknisi-b');
        $customer = Customer::create([
            'name' => 'Pelanggan F5', 'phone' => '0833111',
            'branch_id' => $branchA->id, 'tenant_id' => $tenantA->id,
        ]);
        $sale = Sale::create([
            'number' => 'SL-UJI-F5-A', 'customer_id' => $customer->id,
            'branch_id' => $branchA->id, 'cashier_id' => $adminA->id,
            'sale_date' => now()->toDateString(),
            'subtotal_amount' => 2000000, 'total_amount' => 2000000,
            'tax_amount' => 0, 'paid_amount' => 2000000, 'status' => 'active',
        ]);
        $service = app(CommissionService::class);

        // Tanpa auth sama sekali (jalur antrean/konsol): cap tenant
        // tetap tenant penjualan, bukan NULL dan bukan tenant sesi.
        auth()->logout();
        $processA = LensProcess::create([
            'sale_id' => $sale->id, 'status' => 'done',
            'technician_id' => $technicianA->id, 'started_at' => now(), 'completed_at' => now(),
        ]);
        $row = $service->accrueForTechnician($processA);
        $this->assertNotNull($row);
        $this->assertSame($tenantA->id, (int) $row->tenant_id);
        $this->assertSame('200000.00', $row->amount);

        // Teknisi tenant B pada penjualan tenant A: tidak terakru
        // (penjualan kedua dipakai agar pemeriksaan duplikasi pada
        // penjualan pertama tidak menutupi jalur penolakan ini).
        $sale2 = Sale::create([
            'number' => 'SL-UJI-F5-A2', 'customer_id' => $customer->id,
            'branch_id' => $branchA->id, 'cashier_id' => $adminA->id,
            'sale_date' => now()->toDateString(),
            'subtotal_amount' => 1000000, 'total_amount' => 1000000,
            'tax_amount' => 0, 'paid_amount' => 1000000, 'status' => 'active',
        ]);
        $processB2 = LensProcess::create([
            'sale_id' => $sale2->id, 'status' => 'done',
            'technician_id' => $technicianB->id, 'started_at' => now(), 'completed_at' => now(),
        ]);
        $this->assertNull($service->accrueForTechnician($processB2));
        $this->assertSame(1, Commission::withoutGlobalScopes()->where('type', 'technician')->count());

        // Komisi dokter: tercap tenant penjualan juga.
        $doctorA = Doctor::create([
            'name' => 'dr. F5 A', 'commission_rate' => 5, 'is_active' => true,
        ]);
        $sale3 = Sale::create([
            'number' => 'SL-UJI-F5-A3', 'customer_id' => $customer->id,
            'branch_id' => $branchA->id, 'cashier_id' => $adminA->id,
            'doctor_id' => $doctorA->id, 'sale_date' => now()->toDateString(),
            'subtotal_amount' => 1000000, 'total_amount' => 1000000,
            'tax_amount' => 0, 'paid_amount' => 1000000, 'status' => 'active',
        ]);
        $doctorRow = $service->accrueForDoctor($sale3);
        $this->assertNotNull($doctorRow);
        $this->assertSame($tenantA->id, (int) $doctorRow->tenant_id);
    }

    /**
     * Temuan 3 pada B2: halaman payroll oleh aktor unassigned
     * menampilkan daftar karyawan KOSONG.
     */
    public function test_payroll_page_for_unassigned_actor_lists_no_employees(): void
    {
        $this->tenantAdmin('a');
        $unassigned = User::create([
            'name' => 'Yatim Payroll', 'username' => 'yatim-payroll',
            'email' => 'yatim-payroll@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        $unassigned->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->actingAs($unassigned)->get(route('payroll.index'))
            ->assertOk()
            ->assertDontSee('Admin a')
            ->assertDontSee('Staf');
    }
}
