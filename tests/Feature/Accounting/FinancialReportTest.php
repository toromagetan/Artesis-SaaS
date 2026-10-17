<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F1.3: Neraca seimbang, Laba Rugi total & per cabang, Arus Kas
 * sederhana — semuanya dari baris jurnal, diverifikasi juga dengan
 * agregasi SQL independen di dalam test.
 */
class FinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::firstOrFail();
        $posting = app(PostingService::class);
        $b = $this->branch->id;

        // Setoran modal 1.000.000 (tanpa cabang)
        $posting->post('modal', null, '2026-10-01', [
            ['account' => '1-1100', 'debit' => '1000000'],
            ['account' => '3-1000', 'credit' => '1000000'],
        ], 'modal', $this->owner);
        // Penjualan tunai 500.000 di cabang
        $posting->post('sale', null, '2026-10-05', [
            ['account' => '1-1100', 'debit' => '500000', 'branch_id' => $b],
            ['account' => '4-1100', 'credit' => '500000', 'branch_id' => $b],
        ], 'penjualan', $this->owner);
        // Beban gaji 100.000 di cabang
        $posting->post('salary', null, '2026-10-07', [
            ['account' => '5-1500', 'debit' => '100000', 'branch_id' => $b],
            ['account' => '1-1100', 'credit' => '100000', 'branch_id' => $b],
        ], 'gaji', $this->owner);
        // Beli persediaan kredit 300.000 (tanpa cabang)
        $posting->post('receipt', null, '2026-10-09', [
            ['account' => '1-1400', 'debit' => '300000'],
            ['account' => '2-1100', 'credit' => '300000'],
        ], 'penerimaan', $this->owner);
    }

    public function test_balance_sheet_balances_with_current_earnings(): void
    {
        $sheet = app(FinancialReportService::class)->balanceSheet('2026-10-31');

        $this->assertTrue($sheet['balanced']);
        $this->assertSame('1700000.00', $sheet['total_assets']->toDecimalString()); // kas 1,4jt + persediaan 300rb
        $this->assertSame('300000.00', $sheet['total_liabilities']->toDecimalString());
        $this->assertSame('1000000.00', $sheet['total_equity']->toDecimalString());
        $this->assertSame('400000.00', $sheet['earnings']->toDecimalString()); // 500rb − 100rb

        // Verifikasi independen: total debit = total kredit seluruh jurnal.
        $totals = DB::table('journal_lines')->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
        $this->assertSame((string) $totals->d, (string) $totals->c);
    }

    public function test_income_statement_total_and_per_branch(): void
    {
        $service = app(FinancialReportService::class);
        $statement = $service->incomeStatement('2026-10-01', '2026-10-31');
        $this->assertSame('500000.00', $statement['total_revenue']->toDecimalString());
        $this->assertSame('100000.00', $statement['total_expense']->toDecimalString());
        $this->assertSame('400000.00', $statement['net']->toDecimalString());

        // Filter cabang: penjualan & gaji sama-sama di cabang ini.
        $perBranch = $service->incomeStatement('2026-10-01', '2026-10-31', $this->branch->id);
        $this->assertSame('400000.00', $perBranch['net']->toDecimalString());

        $byBranch = $service->incomeByBranch('2026-10-01', '2026-10-31');
        $branchRow = collect($byBranch)->firstWhere('branch_id', $this->branch->id);
        $this->assertNotNull($branchRow);
        $this->assertSame('500000.00', $branchRow['revenue']->toDecimalString());
        $this->assertSame('100000.00', $branchRow['expense']->toDecimalString());

        // Jumlah per cabang + ember tanpa cabang = total keseluruhan.
        $sumNet = Money::zero();
        foreach ($byBranch as $row) {
            $sumNet = $sumNet->add($row['net']);
        }
        $this->assertSame('400000.00', $sumNet->toDecimalString());
    }

    public function test_cash_flow_opening_groups_and_closing_reconcile(): void
    {
        $flow = app(FinancialReportService::class)->cashFlow('2026-10-01', '2026-10-31');
        $this->assertSame('0.00', $flow['opening']->toDecimalString());
        $this->assertSame('1400000.00', $flow['closing']->toDecimalString());

        $bySource = collect($flow['groups'])->keyBy('source_type');
        $this->assertSame('1000000.00', $bySource['modal']['amount']->toDecimalString());
        $this->assertSame('500000.00', $bySource['sale']['amount']->toDecimalString());
        $this->assertSame('-100000.00', $bySource['salary']['amount']->toDecimalString());

        // Saldo akhir arus kas = saldo buku besar Kas+Bank.
        $kasBank = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.code', ['1-1100', '1-1200'])
            ->selectRaw('SUM(debit) - SUM(credit) as saldo')
            ->value('saldo');
        $this->assertSame('1400000.00', Money::fromDecimal((string) $kasBank)->toDecimalString());
    }

    public function test_financial_pages_and_export_permission_fences(): void
    {
        $admin = $this->makeUserWithRole('Admin');
        $kasir = $this->makeUserWithRole('Kasir');
        $platform = User::where('username', 'platform')->firstOrFail();

        foreach (['financial.balance-sheet', 'financial.income', 'financial.cash-flow'] as $route) {
            $this->actingAs($this->owner)->get(route($route))->assertOk();
            $this->actingAs($admin)->get(route($route))->assertOk();
            $this->actingAs($kasir)->get(route($route))->assertForbidden();
            $this->actingAs($platform)->get(route($route))->assertForbidden();
        }

        // Ekspor: Owner boleh (punya reports.export), Admin tidak.
        $csv = $this->actingAs($this->owner)->get(route('financial.balance-sheet', ['ekspor' => 1]));
        $csv->assertOk();
        $this->assertStringContainsString('1700000', $csv->streamedContent());
        $this->actingAs($admin)->get(route('financial.balance-sheet', ['ekspor' => 1]))->assertForbidden();

        // Navigasi Laporan kini terlihat oleh Admin (reports.financial).
        $this->actingAs($admin)->get(route('financial.income'))->assertSee(route('financial.balance-sheet'), false);
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::create([
            'name' => $roleName, 'username' => strtolower($roleName).'-f13',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        $user->branches()->attach($this->branch);

        return $user;
    }
}
