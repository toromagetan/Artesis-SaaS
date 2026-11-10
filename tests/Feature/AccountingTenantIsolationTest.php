<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\PostingService;
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
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modul B1 — Akuntansi per tenant (audit ulang isolasi
 * 10 Nov 2026): jurnal dicap tenant pemiliknya dan hanya
 * terlihat tenant itu; kunci periode berlaku per tenant —
 * satu tenant menutup bulan tidak mengunci tenant lain;
 * laporan keuangan (laba rugi) hanya mengagregat jurnal
 * tenant aktor.
 */
class AccountingTenantIsolationTest extends TestCase
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

    /** Jurnal pendapatan sederhana: Dr Kas / Cr Pendapatan Penjualan. */
    private function revenueJournal(User $actor, Branch $branch, string $amount): Journal
    {
        return app(PostingService::class)->post('manual', null, now()->toDateString(), [
            ['account' => '1-1100', 'branch_id' => $branch->id, 'debit' => $amount],
            ['account' => '4-1100', 'branch_id' => $branch->id, 'credit' => $amount],
        ], 'Jurnal uji '.$actor->username, $actor);
    }

    public function test_journals_are_stamped_and_hidden_across_tenants(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB, $branchB] = $this->tenantAdmin('b');

        $journalA = $this->revenueJournal($adminA, $branchA, '500000');
        $this->revenueJournal($adminB, $branchB, '900000');

        $this->assertSame($tenantA->id, (int) $journalA->tenant_id);

        $this->actingAs($adminB)->get(route('journals.index'))
            ->assertOk()
            ->assertDontSee($journalA->number);

        $this->actingAs($adminB)->get(route('journals.show', $journalA))
            ->assertNotFound();

        $this->actingAs($adminA)->get(route('journals.show', $journalA))
            ->assertOk();
    }

    public function test_period_lock_applies_per_tenant(): void
    {
        [, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB, $branchB] = $this->tenantAdmin('b');
        $period = now()->format('Y-m');
        $posting = app(PostingService::class);

        $posting->lockPeriod($adminA, $period);

        $this->assertTrue($posting->isPeriodLocked($period, $adminA->tenant_id));
        $this->assertFalse($posting->isPeriodLocked($period, $adminB->tenant_id));

        try {
            $this->revenueJournal($adminA, $branchA, '1000');
            $this->fail('Posting tenant A seharusnya ditolak pada periode terkunci.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        $journalB = $this->revenueJournal($adminB, $branchB, '2000');
        $this->assertSame($adminB->tenant_id, (int) $journalB->tenant_id);

        $posting->unlockPeriod($adminA, $period, 'koreksi uji');
        $this->assertFalse($posting->isPeriodLocked($period, $adminA->tenant_id));
    }

    public function test_financial_reports_only_aggregate_own_tenant(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $this->revenueJournal($adminA, $branchA, '500000');
        $this->revenueJournal($adminB, $branchB, '900000');

        $reports = app(FinancialReportService::class);
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $statementB = $reports->incomeStatement($from, $to, null, $tenantB->id);
        $this->assertSame('900000.00', $statementB['total_revenue']->toDecimalString());

        $statementA = $reports->incomeStatement($from, $to, null, $tenantA->id);
        $this->assertSame('500000.00', $statementA['total_revenue']->toDecimalString());

        $sheetB = $reports->balanceSheet($to, $tenantB->id);
        $this->assertSame('900000.00', $sheetB['earnings']->toDecimalString());

        // Ekspor laba-rugi tenant B tidak memuat angka tenant A.
        $csv = $this->actingAs($adminB)->get(route('financial.income', ['ekspor' => 1]));
        $csv->assertOk();
        $this->assertStringNotContainsString('500000', $csv->streamedContent());
        $this->assertStringContainsString('900000', $csv->streamedContent());
    }
}
