<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\InstallmentService;
use App\Support\XlsxWriter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar piutang pelanggan & umur piutang (F3.2, PRD 2.4).
 * Baris = penjualan aktif yang masih bersisa. Umur dihitung dari
 * tanggal acuan InstallmentService (jatuh tempo cicilan belum lunas
 * paling awal, atau tanggal penjualan bila belum berjadwal).
 * Pagar cabang datang dari BranchScope + filter `cabang`;
 * ekspor CSV wajib izin reports.export (pola M.10).
 */
class ReceivableController extends Controller
{
    public function __construct(private readonly InstallmentService $installments) {}

    public function index(Request $request): View|StreamedResponse
    {
        $branchId = $request->query('cabang');
        $bucket = (string) $request->query('umur', '');
        $today = today();

        $sales = Sale::with(['customer', 'branch', 'installments'])
            ->where('status', 'active')
            ->whereColumn('paid_amount', '<', 'total_amount')
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get();

        $rows = $sales->map(function (Sale $sale) use ($today) {
            $aging = $this->installments->agingFor($sale, $today);

            return [
                'sale' => $sale,
                'due_date' => $aging['reference_date'],
                'age_days' => $aging['age_days'],
                'bucket' => $aging['bucket'],
            ];
        })->when($bucket !== '', fn ($rows) => $rows->where('bucket', $bucket)->values());

        if ($request->boolean('ekspor') || $request->query('format') === 'xlsx') {
            Gate::authorize('reports.export');
            if ($request->query('format') === 'xlsx') {
                return $this->xlsx($rows);
            }

            return $this->csv($rows);
        }

        $context = BranchContext::current();
        $branches = $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $rows = new LengthAwarePaginator(
            $rows->forPage($page, 25)->values(),
            $rows->count(),
            25,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('sales.receivables', compact('rows', 'branches', 'branchId', 'bucket'));
    }

    /** Ekspor XLSX asli daftar piutang (baris yang sama dengan CSV). */
    private function xlsx($rows): StreamedResponse
    {
        $lines = [['Pelanggan', 'Nomor Transaksi', 'Cabang', 'Total', 'Terbayar', 'Sisa', 'Jatuh Tempo', 'Umur (hari)', 'Kategori']];
        foreach ($rows as $row) {
            $sale = $row['sale'];
            $lines[] = [
                $sale->customer?->name,
                $sale->number,
                $sale->branch?->name,
                (string) $sale->total_amount,
                (string) $sale->paid_amount,
                $sale->remaining()->toDecimalString(),
                $row['due_date']->toDateString(),
                $row['age_days'],
                __('ui.receivables.age_'.$row['bucket']),
            ];
        }
        $bytes = XlsxWriter::make([['title' => 'piutang-pelanggan', 'rows' => $lines]]);

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, 'piutang-pelanggan.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function csv($rows): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="piutang-pelanggan.csv"',
        ];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Pelanggan', 'Nomor Transaksi', 'Cabang', 'Total', 'Terbayar', 'Sisa', 'Jatuh Tempo', 'Umur (hari)', 'Kategori']);
            foreach ($rows as $row) {
                $sale = $row['sale'];
                fputcsv($out, [
                    $sale->customer?->name,
                    $sale->number,
                    $sale->branch?->name,
                    (string) $sale->total_amount,
                    (string) $sale->paid_amount,
                    $sale->remaining()->toDecimalString(),
                    $row['due_date']->toDateString(),
                    $row['age_days'],
                    __('ui.receivables.age_'.$row['bucket']),
                ]);
            }
            fclose($out);
        }, 'piutang-pelanggan.csv', $headers);
    }
}
