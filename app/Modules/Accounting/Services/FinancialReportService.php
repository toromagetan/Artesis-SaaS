<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Support\Chart;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Laporan keuangan (F1.3, izin reports.financial — Blueprint D.9):
 * seluruhnya dihitung dari baris jurnal, tidak ada sumber kedua.
 *
 * - Neraca: saldo kumulatif per tanggal; laba berjalan (pendapatan
 *   − beban kumulatif) tampil sebagai baris ekuitas agar neraca
 *   seimbang sebelum tutup buku.
 * - Laba Rugi: mutasi periode per akun pendapatan & beban, total
 *   maupun per cabang (dimensi branch_id baris jurnal).
 * - Arus Kas sederhana: perubahan akun Kas+Bank dikelompokkan
 *   menurut jenis sumber jurnal; saldo awal + mutasi = saldo akhir.
 */
class FinancialReportService
{
    /**
     * @return array{
     *     assets: array<int, array{account: Account, balance: Money}>,
     *     liabilities: array<int, array{account: Account, balance: Money}>,
     *     equity: array<int, array{account: Account, balance: Money}>,
     *     total_assets: Money, total_liabilities: Money, total_equity: Money,
     *     earnings: Money, balanced: bool
     * }
     */
    public function balanceSheet(string $asOf): array
    {
        $assets = $this->balances('asset', null, $asOf);
        $liabilities = $this->balances('liability', null, $asOf);
        $equity = $this->balances('equity', null, $asOf);

        $totalAssets = $this->sum($assets);
        $totalLiabilities = $this->sum($liabilities);
        $totalEquity = $this->sum($equity);

        $revenue = $this->sum($this->balances('revenue', null, $asOf));
        $expense = $this->sum($this->balances('expense', null, $asOf));
        $earnings = $revenue->sub($expense);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'earnings' => $earnings,
            'balanced' => $totalAssets->equals($totalLiabilities->add($totalEquity)->add($earnings)),
        ];
    }

    /**
     * @return array{
     *     revenues: array<int, array{account: Account, balance: Money}>,
     *     expenses: array<int, array{account: Account, balance: Money}>,
     *     total_revenue: Money, total_expense: Money, net: Money
     * }
     */
    public function incomeStatement(string $from, string $to, ?int $branchId = null): array
    {
        $revenues = $this->balances('revenue', $from, $to, $branchId);
        $expenses = $this->balances('expense', $from, $to, $branchId);
        $totalRevenue = $this->sum($revenues);
        $totalExpense = $this->sum($expenses);

        return [
            'revenues' => $revenues,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net' => $totalRevenue->sub($totalExpense),
        ];
    }

    /**
     * Laba rugi per cabang: baris tanpa dimensi cabang masuk ember
     * terpisah (branch_id null) agar total per cabang + ember selalu
     * sama dengan laba rugi keseluruhan.
     *
     * @return array<int, array{branch_id: ?int, revenue: Money, expense: Money, net: Money}>
     */
    public function incomeByBranch(string $from, string $to): array
    {
        $rows = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.group', ['revenue', 'expense'])
            ->whereBetween('journals.journal_date', [$from, $to])
            ->groupBy('journal_lines.branch_id', 'accounts.group')
            ->selectRaw('journal_lines.branch_id as bid, accounts.group as grp, COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->get();

        $byBranch = [];
        foreach ($rows as $row) {
            $key = $row->bid === null ? 'none' : (string) $row->bid;
            $byBranch[$key] ??= ['branch_id' => $row->bid !== null ? (int) $row->bid : null, 'revenue' => Money::zero(), 'expense' => Money::zero(), 'net' => Money::zero()];
            $d = Money::fromDecimal((string) $row->d);
            $c = Money::fromDecimal((string) $row->c);
            if ($row->grp === 'revenue') {
                $byBranch[$key]['revenue'] = $byBranch[$key]['revenue']->add($c->sub($d));
            } else {
                $byBranch[$key]['expense'] = $byBranch[$key]['expense']->add($d->sub($c));
            }
        }
        foreach ($byBranch as &$entry) {
            $entry['net'] = $entry['revenue']->sub($entry['expense']);
        }

        return array_values($byBranch);
    }

    /**
     * @return array{
     *     opening: Money, closing: Money,
     *     groups: array<int, array{source_type: string, amount: Money}>
     * }
     */
    public function cashFlow(string $from, string $to): array
    {
        $cashAccountIds = Account::whereIn('code', [Chart::KAS, Chart::BANK])->pluck('id');

        $signed = fn ($query) => $query
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();

        $base = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->whereIn('journal_lines.account_id', $cashAccountIds);

        $openingRow = $signed((clone $base)->where('journals.journal_date', '<', $from));
        $opening = Money::fromDecimal((string) $openingRow->d)->sub(Money::fromDecimal((string) $openingRow->c));

        $groupRows = (clone $base)
            ->whereBetween('journals.journal_date', [$from, $to])
            ->groupBy('journals.source_type')
            ->selectRaw('journals.source_type as st, COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->orderBy('journals.source_type')
            ->get();

        $groups = [];
        $closing = $opening;
        foreach ($groupRows as $row) {
            $amount = Money::fromDecimal((string) $row->d)->sub(Money::fromDecimal((string) $row->c));
            $groups[] = ['source_type' => (string) $row->st, 'amount' => $amount];
            $closing = $closing->add($amount);
        }

        return ['opening' => $opening, 'closing' => $closing, 'groups' => $groups];
    }

    /** Saldo Kas + Bank saat ini (semua mutasi s.d. hari ini). */
    public function cashPosition(): Money
    {
        $cashAccountIds = Account::whereIn('code', [Chart::KAS, Chart::BANK])->pluck('id');
        $row = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->whereIn('journal_lines.account_id', $cashAccountIds)
            ->where('journals.journal_date', '<=', now()->toDateString())
            ->selectRaw('COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->first();

        return Money::fromDecimal((string) $row->d)->sub(Money::fromDecimal((string) $row->c));
    }

    /**
     * Saldo bertanda sisi normal per akun untuk satu kelompok.
     * $from null = sejak awal; akun bersaldo nol disembunyikan.
     *
     * @return array<int, array{account: Account, balance: Money}>
     */
    private function balances(string $group, ?string $from, string $to, ?int $branchId = null): array
    {
        $debitNormal = in_array($group, ['asset', 'expense'], true);

        $rows = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.group', $group)
            ->where('journals.journal_date', '<=', $to)
            ->when($from !== null, fn ($q) => $q->where('journals.journal_date', '>=', $from))
            ->when($branchId !== null, fn ($q) => $q->where('journal_lines.branch_id', $branchId))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id as aid, COALESCE(SUM(journal_lines.debit),0) as d, COALESCE(SUM(journal_lines.credit),0) as c')
            ->get()
            ->keyBy('aid');

        $result = [];
        $accounts = Account::where('group', $group)->orderBy('code')->get();
        foreach ($accounts as $account) {
            $row = $rows->get($account->id);
            if ($row === null) {
                continue;
            }
            $d = Money::fromDecimal((string) $row->d);
            $c = Money::fromDecimal((string) $row->c);
            $balance = $debitNormal ? $d->sub($c) : $c->sub($d);
            if ($balance->isZero()) {
                continue;
            }
            $result[] = ['account' => $account, 'balance' => $balance];
        }

        return $result;
    }

    /** @param array<int, array{balance: Money}> $rows */
    private function sum(array $rows): Money
    {
        $total = Money::zero();
        foreach ($rows as $row) {
            $total = $total->add($row['balance']);
        }

        return $total;
    }
}
