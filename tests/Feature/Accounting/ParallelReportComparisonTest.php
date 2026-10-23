<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang roadmap Fase 2 (butir 124): uji PARALEL — laporan sistem
 * dibandingkan dengan "laporan manual" yang dihitung ulang dari
 * nol di dalam test ini, meniru cara akuntan memakai spreadsheet:
 * seluruh baris jurnal dibaca mentah, dijumlahkan baris demi baris
 * di PHP per kelompok akun dengan kaidah saldo normal, TANPA
 * memakai FinancialReportService sama sekali. Keduanya wajib sama
 * persis untuk laporan laba rugi periode contoh dan neraca per
 * tanggal contoh.
 */
class ParallelReportComparisonTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $posting = app(PostingService::class);
        $b = $this->branch->id;
        // Modal awal (2 Okt).
        $posting->post('manual', null, '2026-10-02', [
            ['account' => '1-1100', 'branch_id' => $b, 'debit' => '2000000', 'credit' => '0'],
            ['account' => '3-1000', 'branch_id' => $b, 'debit' => '0', 'credit' => '2000000'],
        ], 'Modal', $this->owner);
        // Penjualan tunai (10 Okt).
        $posting->post('manual', null, '2026-10-10', [
            ['account' => '1-1100', 'branch_id' => $b, 'debit' => '750000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $b, 'debit' => '0', 'credit' => '750000'],
        ], 'Jual', $this->owner);
        // Beban operasional (12 Okt) — akun beban pertama di bagan.
        $expense = DB::table('accounts')->where('group', 'expense')->orderBy('code')->first();
        $posting->post('manual', null, '2026-10-12', [
            ['account' => $expense->code, 'branch_id' => $b, 'debit' => '200000', 'credit' => '0'],
            ['account' => '1-1100', 'branch_id' => $b, 'debit' => '0', 'credit' => '200000'],
        ], 'Beban', $this->owner);
        // Utang usaha (15 Okt): persediaan datang, belum dibayar.
        $posting->post('manual', null, '2026-10-15', [
            ['account' => '1-1400', 'branch_id' => $b, 'debit' => '300000', 'credit' => '0'],
            ['account' => '2-1100', 'branch_id' => $b, 'debit' => '0', 'credit' => '300000'],
        ], 'Utang', $this->owner);
        // Transaksi NOVEMBER — tidak boleh bocor ke laporan Oktober.
        $posting->post('manual', null, '2026-11-03', [
            ['account' => '1-1100', 'branch_id' => $b, 'debit' => '99000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $b, 'debit' => '0', 'credit' => '99000'],
        ], 'Jual Nov', $this->owner);
    }

    /**
     * "Spreadsheet manual": jumlahkan baris mentah per kelompok.
     * Mengembalikan total per kelompok dalam sen (integer).
     *
     * @return array<string, int>
     */
    private function spreadsheetTotals(string $from, string $to): array
    {
        $rows = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereBetween('journals.journal_date', [$from, $to])
            ->select('accounts.group as grp', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $totals = ['asset' => 0, 'liability' => 0, 'equity' => 0, 'revenue' => 0, 'expense' => 0];
        foreach ($rows as $row) {
            $debit = (int) round(((float) $row->debit) * 100);
            $credit = (int) round(((float) $row->credit) * 100);
            // Saldo normal: aset & beban bertambah di debit;
            // liabilitas, ekuitas, pendapatan bertambah di kredit.
            $delta = in_array($row->grp, ['asset', 'expense'], true) ? $debit - $credit : $credit - $debit;
            $totals[$row->grp] += $delta;
        }

        return $totals;
    }

    private function cents(string $decimal): int
    {
        return (int) round(((float) $decimal) * 100);
    }

    public function test_manual_spreadsheet_matches_system_reports(): void
    {
        $service = app(FinancialReportService::class);

        // --- Laba rugi Oktober ---
        $manual = $this->spreadsheetTotals('2026-10-01', '2026-10-31');
        $statement = $service->incomeStatement('2026-10-01', '2026-10-31');
        $this->assertSame($manual['revenue'], $this->cents($statement['total_revenue']->toDecimalString()), 'Pendapatan manual ≠ sistem');
        $this->assertSame($manual['expense'], $this->cents($statement['total_expense']->toDecimalString()), 'Beban manual ≠ sistem');
        $this->assertSame($manual['revenue'] - $manual['expense'], $this->cents($statement['net']->toDecimalString()), 'Laba bersih manual ≠ sistem');

        // --- Neraca per 31 Oktober (kumulatif sejak awal) ---
        $manualAll = $this->spreadsheetTotals('2000-01-01', '2026-10-31');
        $sheet = $service->balanceSheet('2026-10-31');
        $this->assertSame($manualAll['asset'], $this->cents($sheet['total_assets']->toDecimalString()), 'Aset manual ≠ sistem');
        $this->assertSame($manualAll['liability'], $this->cents($sheet['total_liabilities']->toDecimalString()), 'Liabilitas manual ≠ sistem');
        $this->assertSame($manualAll['equity'], $this->cents($sheet['total_equity']->toDecimalString()), 'Ekuitas manual ≠ sistem');
        $this->assertSame($manualAll['revenue'] - $manualAll['expense'], $this->cents($sheet['earnings']->toDecimalString()), 'Laba berjalan manual ≠ sistem');
        $this->assertTrue($sheet['balanced']);
        $this->assertSame(
            $manualAll['asset'],
            $manualAll['liability'] + $manualAll['equity'] + ($manualAll['revenue'] - $manualAll['expense']),
            'Persamaan akuntansi manual tidak seimbang'
        );
    }
}
