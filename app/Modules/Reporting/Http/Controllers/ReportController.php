<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Reporting\Services\AnalyticsService;
use App\Modules\Reporting\Services\ReportService;
use App\Support\Money;
use App\Support\SvgChart;
use App\Support\XlsxWriter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan inti (M.10). Filter yang sama persis dipakai layar dan
 * ekspor (satu jalur data di ReportService) — ekspor CSV dipagari
 * izin reports.export.
 */
class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function sales(Request $request): View|StreamedResponse
    {
        [$from, $to, $branchIds] = $this->scope($request);
        $rows = $this->reports->salesSummary($from, $to, $branchIds);
        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $totals = $this->sumSales($rows);

            return $this->export($request, 'laporan-penjualan.csv', [
                ['Cabang', 'Transaksi', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Retur', 'Neto', 'Terbayar', 'Piutang'],
                ...collect($rows)->map(fn ($r) => [
                    $this->branchName($r['branch_id']), $r['transactions'],
                    $r['subtotal']->toDecimalString(), $r['discount']->toDecimalString(), $r['tax']->toDecimalString(),
                    $r['total']->toDecimalString(), $r['returns']->toDecimalString(), $r['net']->toDecimalString(),
                    $r['paid']->toDecimalString(), $r['receivable']->toDecimalString(),
                ])->all(),
                ['TOTAL', $totals['transactions'], $totals['subtotal']->toDecimalString(), $totals['discount']->toDecimalString(), $totals['tax']->toDecimalString(), $totals['total']->toDecimalString(), $totals['returns']->toDecimalString(), $totals['net']->toDecimalString(), $totals['paid']->toDecimalString(), $totals['receivable']->toDecimalString()],
            ]);
        }

        return view('reporting.sales', [
            'rows' => $rows, 'totals' => $this->sumSales($rows),
            'from' => $from, 'to' => $to, 'branches' => $this->branchOptions(), 'selected' => $this->selectedBranch($request),
        ]);
    }

    public function purchases(Request $request): View|StreamedResponse
    {
        [$from, $to, $branchIds] = $this->scope($request);
        $rows = $this->reports->purchasesNet($from, $to, $branchIds);
        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $gross = Money::zero();
            $returns = Money::zero();
            $net = Money::zero();
            foreach ($rows as $r) {
                $gross = $gross->add($r['gross']);
                $returns = $returns->add($r['returns']);
                $net = $net->add($r['net']);
            }

            return $this->export($request, 'laporan-pembelian.csv', [
                ['Supplier', 'Penerimaan', 'Bruto', 'Retur', 'Neto'],
                ...collect($rows)->map(fn ($r) => [$r['supplier_name'], $r['receipts'], $r['gross']->toDecimalString(), $r['returns']->toDecimalString(), $r['net']->toDecimalString()])->all(),
                ['TOTAL', array_sum(array_column($rows, 'receipts')), $gross->toDecimalString(), $returns->toDecimalString(), $net->toDecimalString()],
            ]);
        }
        $gross = Money::zero();
        $returns = Money::zero();
        $net = Money::zero();
        foreach ($rows as $r) {
            $gross = $gross->add($r['gross']);
            $returns = $returns->add($r['returns']);
            $net = $net->add($r['net']);
        }

        return view('reporting.purchases', [
            'rows' => $rows, 'gross' => $gross, 'returns' => $returns, 'net' => $net,
            'from' => $from, 'to' => $to, 'branches' => $this->branchOptions(), 'selected' => $this->selectedBranch($request),
        ]);
    }

    public function stock(Request $request): View|StreamedResponse
    {
        [, , $branchIds] = $this->scope($request);
        $type = (string) $request->query('tipe', '');
        $rows = $this->reports->stockReport($branchIds, $type !== '' ? $type : null);
        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();

            return $this->export($request, 'laporan-stok.csv', [
                ['Cabang', 'SKU', 'Produk', 'Tipe', 'Qty', 'Dalam Perjalanan', 'Nilai'],
                ...collect($rows)->map(fn ($r) => [$this->branchName($r['branch_id']), $r['sku'], $r['product_name'], $r['type'], $r['qty'], $r['in_transit'], $r['value']->toDecimalString()])->all(),
            ]);
        }
        $totalValue = Money::zero();
        foreach ($rows as $r) {
            $totalValue = $totalValue->add($r['value']);
        }

        return view('reporting.stock', [
            'rows' => $rows, 'totalValue' => $totalValue, 'type' => $type,
            'branches' => $this->branchOptions(), 'selected' => $this->selectedBranch($request),
        ]);
    }

    public function payables(Request $request): View|StreamedResponse
    {
        [, , $branchIds] = $this->scope($request);
        $data = $this->reports->supplierPayables($branchIds);
        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $lines = [['Supplier', 'No. Penerimaan', 'PO', 'Tanggal', 'Total', 'Terbayar', 'Sisa']];
            foreach ($data['suppliers'] as $supplier) {
                foreach ($supplier['receipts'] as $receipt) {
                    $lines[] = [$supplier['supplier_name'], $receipt['number'], $receipt['po_number'], (string) $receipt['date'], $receipt['total']->toDecimalString(), $receipt['allocated']->toDecimalString(), $receipt['remaining']->toDecimalString()];
                }
            }
            $lines[] = ['TOTAL', '', '', '', '', '', $data['grand_total']->toDecimalString()];

            return $this->export($request, 'utang-supplier.csv', $lines);
        }

        return view('reporting.payables', [
            'suppliers' => $data['suppliers'], 'grandTotal' => $data['grand_total'],
            'branches' => $this->branchOptions(), 'selected' => $this->selectedBranch($request),
        ]);
    }

    // ---- pembantu ----

    /** @return array{0: string, 1: string, 2: int[]} */
    /**
     * Analitik (F4.5): halaman memerlukan reports.sales; blok
     * supplier hanya tampil & terhitung dengan reports.purchases.
     * Ekspor menghormati filter aktif seperti laporan lain.
     */
    public function analytics(Request $request, AnalyticsService $analytics): View|StreamedResponse
    {
        [$from, $to, $branchIds] = $this->scope($request);
        $sales = $analytics->salesAnalytics($branchIds, $from, $to);
        $suppliers = $request->user()->hasPermission('reports.purchases')
            ? $analytics->supplierAnalytics($branchIds, $from, $to)
            : null;

        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            $this->authorizeExport();
            $lines = [[__('ui.analytics.suppliers')], ['Supplier', 'PO', 'LeadTime', 'NilaiBeli', 'QtyTerima', 'QtyRetur', 'ReturPersen', 'PemenuhanPersen']];
            foreach ($suppliers ?? [] as $row) {
                $lines[] = [$row['supplier_name'], $row['po_count'], $row['lead_time_days'] ?? '-', $row['purchase_value']->toDecimalString(), $row['received_qty'], $row['returned_qty'], number_format($row['return_rate_bp'] / 100, 2), number_format($row['fulfillment_bp'] / 100, 2)];
            }
            $lines[] = [__('ui.analytics.top_products')];
            $lines[] = ['Produk', 'SKU', 'Qty', 'Pendapatan'];
            foreach ($sales['top_products'] as $row) {
                $lines[] = [$row['name'], $row['sku'], $row['qty'], $row['revenue']->toDecimalString()];
            }
            $lines[] = [__('ui.analytics.category_margins')];
            $lines[] = ['Kategori', 'Pendapatan', 'HPP', 'Margin'];
            foreach ($sales['category_margins'] as $row) {
                $lines[] = [$row['type'], $row['revenue']->toDecimalString(), $row['cost']->toDecimalString(), $row['margin']->toDecimalString()];
            }

            return $this->export($request, 'analitik.csv', $lines);
        }

        // Pengayaan visual (keputusan owner 2026-10-19): grafik SVG
        // server-side dari data analitik yang sama — tren cabang
        // multi-seri per bulan berkesinambungan + doughnut
        // komposisi pendapatan per kategori. Tabel tetap di bawahnya.
        $months = [];
        $cursor = Carbon::parse($from)->startOfMonth();
        $endMonth = Carbon::parse($to)->startOfMonth();
        while ($cursor->lte($endMonth)) {
            $months[] = $cursor->format('Y-m');
            $cursor->addMonth();
        }
        $branchNames = Branch::pluck('name', 'id');
        $series = [];
        foreach (collect($sales['branch_trend'])->groupBy('branch_id') as $branchId => $rows) {
            $byMonth = $rows->keyBy('month');
            $series[] = [
                'label' => $branchNames[$branchId] ?? '#'.$branchId,
                'color' => SvgChart::PALETTE[count($series) % count(SvgChart::PALETTE)],
                'points' => array_map(fn ($m) => isset($byMonth[$m]) ? $byMonth[$m]['revenue']->cents() / 100 : 0, $months),
            ];
        }
        $trendChart = $series === [] ? null : SvgChart::line(
            array_map(fn ($m) => Carbon::parse($m.'-01')->translatedFormat('M y'), $months),
            $series,
        );

        $slices = [];
        $categoryLegend = [];
        $categoryTotal = Money::zero();
        foreach ($sales['category_margins'] as $i => $row) {
            if ($row['revenue']->isZero()) {
                continue;
            }
            $color = SvgChart::PALETTE[$i % count(SvgChart::PALETTE)];
            $label = __('ui.products.types.'.$row['type']);
            $slices[] = ['label' => $label, 'value' => $row['revenue']->cents() / 100, 'color' => $color];
            $categoryLegend[] = ['label' => $label, 'color' => $color, 'value' => $row['revenue'], 'pct' => 0.0];
            $categoryTotal = $categoryTotal->add($row['revenue']);
        }
        foreach (SvgChart::shares($slices) as $idx => $pct) {
            $categoryLegend[$idx]['pct'] = $pct;
        }
        $categoryChart = SvgChart::doughnut($slices, SvgChart::shortMoney($categoryTotal->cents() / 100), __('ui.dashboard.total'));

        return view('reporting.analytics', [
            'sales' => $sales, 'suppliers' => $suppliers,
            'trendChart' => $trendChart, 'categoryChart' => $categoryChart, 'categoryLegend' => $categoryLegend,
            'from' => $from, 'to' => $to, 'branches' => $this->branchOptions(), 'selected' => $this->selectedBranch($request),
        ]);
    }

    private function scope(Request $request): array
    {
        $from = (string) $request->query('dari', now()->startOfMonth()->format('Y-m-d'));
        $to = (string) $request->query('sampai', now()->format('Y-m-d'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) !== 1) {
            $from = now()->startOfMonth()->format('Y-m-d');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) !== 1) {
            $to = now()->format('Y-m-d');
        }
        $context = BranchContext::current();
        $branchIds = $context->hasGlobalAccess()
            ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
        $selected = $this->selectedBranch($request);
        if ($selected !== null && in_array($selected, $branchIds, true)) {
            $branchIds = [$selected];
        } elseif ($selected !== null) {
            $branchIds = []; // cabang di luar kewenangan → hasil kosong, bukan bocor
        }

        return [$from, $to, $branchIds];
    }

    private function selectedBranch(Request $request): ?int
    {
        $value = $request->query('cabang');

        return $value !== null && $value !== '' ? (int) $value : null;
    }

    private function branchOptions()
    {
        $context = BranchContext::current();
        $query = Branch::orderBy('name');
        if (! $context->hasGlobalAccess()) {
            $query->whereIn('id', $context->managedBranchIds());
        }

        return $query->get();
    }

    private function branchName(int $id): string
    {
        return Branch::find($id)?->name ?? '#'.$id;
    }

    private function sumSales(array $rows): array
    {
        $totals = ['transactions' => 0];
        foreach (['subtotal', 'discount', 'tax', 'total', 'returns', 'net', 'paid', 'receivable'] as $key) {
            $totals[$key] = Money::zero();
        }
        foreach ($rows as $row) {
            $totals['transactions'] += $row['transactions'];
            foreach (['subtotal', 'discount', 'tax', 'total', 'returns', 'net', 'paid', 'receivable'] as $key) {
                $totals[$key] = $totals[$key]->add($row[$key]);
            }
        }

        return $totals;
    }

    private function authorizeExport(): void
    {
        Gate::authorize('reports.export'); // kemampuan dari registry (Gate::before)
    }

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
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            foreach ($lines as $line) {
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
