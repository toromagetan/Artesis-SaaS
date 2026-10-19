<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Master\Models\Branch;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan keuangan (F1.3, izin reports.financial): Neraca,
 * Laba Rugi (total & per cabang), Arus Kas sederhana. Ekspor CSV
 * memakai jalur data yang sama dengan layar dan dipagari izin
 * reports.export — pola yang sama dengan laporan inti M.10.
 */
class FinancialReportController extends Controller
{
    public function __construct(private readonly FinancialReportService $financial) {}

    public function balanceSheet(Request $request): View|StreamedResponse
    {
        $asOf = $this->dateParam($request, 'per', now()->toDateString());
        $sheet = $this->financial->balanceSheet($asOf);

        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $lines = [[__('ui.financial.balance_sheet'), $asOf], ['Kode', 'Akun', 'Saldo']];
            foreach (['assets', 'liabilities', 'equity'] as $section) {
                foreach ($sheet[$section] as $row) {
                    $lines[] = [$row['account']->code, $row['account']->name, $row['balance']->toDecimalString()];
                }
            }
            $lines[] = ['', __('ui.financial.current_earnings'), $sheet['earnings']->toDecimalString()];
            $lines[] = ['', __('ui.financial.total_assets'), $sheet['total_assets']->toDecimalString()];
            $lines[] = ['', __('ui.financial.total_liabilities_equity'), $sheet['total_liabilities']->add($sheet['total_equity'])->add($sheet['earnings'])->toDecimalString()];

            return $this->export($request, 'neraca.csv', $lines);
        }

        return view('accounting.financial.balance-sheet', compact('sheet', 'asOf'));
    }

    public function incomeStatement(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $branchParam = $request->query('cabang');
        $selected = is_numeric($branchParam) ? (int) $branchParam : null;

        $statement = $this->financial->incomeStatement($from, $to, $selected);
        $byBranch = $this->financial->incomeByBranch($from, $to);
        $branches = Branch::orderBy('name')->get();

        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $lines = [[__('ui.financial.income_statement'), $from, $to], ['Kode', 'Akun', 'Jumlah']];
            foreach ($statement['revenues'] as $row) {
                $lines[] = [$row['account']->code, $row['account']->name, $row['balance']->toDecimalString()];
            }
            foreach ($statement['expenses'] as $row) {
                $lines[] = [$row['account']->code, $row['account']->name, '-'.$row['balance']->toDecimalString()];
            }
            $lines[] = ['', __('ui.financial.net_income'), $statement['net']->toDecimalString()];
            $lines[] = [];
            $lines[] = [__('ui.financial.per_branch'), __('ui.financial.revenue'), __('ui.financial.expenses'), __('ui.financial.net_income')];
            foreach ($byBranch as $row) {
                $lines[] = [$this->branchName($branches, $row['branch_id']), $row['revenue']->toDecimalString(), $row['expense']->toDecimalString(), $row['net']->toDecimalString()];
            }

            return $this->export($request, 'laba-rugi.csv', $lines);
        }

        return view('accounting.financial.income', compact('statement', 'byBranch', 'branches', 'from', 'to', 'selected'));
    }

    public function cashFlow(Request $request): View|StreamedResponse
    {
        [$from, $to] = $this->range($request);
        $flow = $this->financial->cashFlow($from, $to);

        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $lines = [[__('ui.financial.cash_flow'), $from, $to], ['', __('ui.financial.opening'), $flow['opening']->toDecimalString()]];
            foreach ($flow['groups'] as $group) {
                $lines[] = [$group['source_type'], '', $group['amount']->toDecimalString()];
            }
            $lines[] = ['', __('ui.financial.closing'), $flow['closing']->toDecimalString()];

            return $this->export($request, 'arus-kas.csv', $lines);
        }

        return view('accounting.financial.cash-flow', compact('flow', 'from', 'to'));
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $from = $this->dateParam($request, 'dari', now()->startOfMonth()->toDateString());
        $to = $this->dateParam($request, 'sampai', now()->toDateString());
        if ($to < $from) {
            $to = $from;
        }

        return [$from, $to];
    }

    private function dateParam(Request $request, string $key, string $default): string
    {
        $value = (string) $request->query($key, $default);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : $default;
    }

    /** @param Collection<int, Branch> $branches */
    private function branchName($branches, ?int $branchId): string
    {
        if ($branchId === null) {
            return __('ui.financial.no_branch');
        }

        return $branches->firstWhere('id', $branchId)?->name ?? ('#'.$branchId);
    }

    private function authorizeExport(): void
    {
        Gate::authorize('reports.export');
    }

    /** @param array<int, array<int, string>> $lines */
    /** Ekspor CSV (bawaan) atau XLSX asli bila ?format=xlsx. */
    private function export(Request $request, string $filename, array $lines): StreamedResponse
    {
        if ($request->query('format') === 'xlsx') {
            $title = pathinfo($filename, PATHINFO_FILENAME);
            $bytes = XlsxWriter::make([['title' => $title, 'rows' => $lines]]);

            return response()->streamDownload(function () use ($bytes) {
                echo $bytes;
            }, preg_replace('/\.csv$/', '.xlsx', $filename), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
        }

        return $this->csv($filename, $lines);
    }

    private function csv(string $filename, array $lines): StreamedResponse
    {
        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($lines as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
