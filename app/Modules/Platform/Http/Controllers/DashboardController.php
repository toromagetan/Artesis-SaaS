<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard operasional (M.11): kartu per izin — setiap angka
 * terpagar cabang pemanggil (BranchScoped untuk penjualan/lab/PR/
 * gerakan; transfer & utang lewat cakupan eksplisit). Pengguna
 * hanya melihat kartu yang izinnya ia pegang.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports, FinancialReportService $financial): View
    {
        $user = $request->user();
        $context = BranchContext::for($user);
        $branchIds = $context->hasGlobalAccess()
            ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
        $today = now()->format('Y-m-d');

        $data = ['cards' => []];

        if ($user->hasPermission('sales.view')) {
            $todaySales = Sale::where('status', 'active')->whereDate('sale_date', $today);
            $receivable = Money::zero();
            foreach (Sale::where('status', 'active')->get(['total_amount', 'paid_amount']) as $sale) {
                $receivable = $receivable->add(
                    Money::fromDecimal((string) $sale->total_amount)->sub(Money::fromDecimal((string) $sale->paid_amount)),
                );
            }
            $data['cards']['sales'] = [
                'today_count' => (clone $todaySales)->count(),
                'today_total' => Money::fromDecimal((string) ((clone $todaySales)->sum('total_amount') ?? '0')),
                'receivable' => $receivable,
            ];
        }

        if ($user->hasPermission('lab.view')) {
            $counts = LensProcess::query()
                ->join('sales', 'sales.id', '=', 'lens_processes.sale_id')
                ->whereIn('sales.branch_id', $branchIds)
                ->selectRaw('lens_processes.status, COUNT(*) as total')
                ->groupBy('lens_processes.status')
                ->pluck('total', 'status');
            $data['cards']['lab'] = [
                'waiting' => (int) ($counts['waiting'] ?? 0),
                'edging' => (int) ($counts['edging'] ?? 0),
                'qc' => (int) ($counts['qc'] ?? 0),
                'ready' => (int) ($counts['ready'] ?? 0),
            ];
        }

        if ($user->hasPermission('stock.view')) {
            $latest = StockMovement::withoutGlobalScopes()
                ->selectRaw('MAX(id) as id')
                ->whereIn('branch_id', $branchIds)
                ->groupBy('product_id', 'branch_id');
            $balances = StockMovement::withoutGlobalScopes()
                ->joinSub($latest, 'latest', 'latest.id', '=', 'stock_movements.id')
                ->pluck('stock_movements.balance_after');
            $data['cards']['stock'] = [
                'low' => $balances->filter(fn ($q) => $q >= 1 && $q <= 5)->count(),
                'out' => $balances->filter(fn ($q) => $q === 0)->count(),
                'backorder' => $balances->filter(fn ($q) => $q < 0)->count(),
                // Peringatan kedaluwarsa softlens (F4.3, PRD 4.4):
                // lapisan kedaluwarsa / < 90 hari di cabang terlihat.
                'expiring' => app(FifoService::class)->expiringLayers($branchIds, 90),
            ];
            $data['cards']['transfers_in'] = StockTransfer::where('status', 'sent')
                ->whereIn('to_branch_id', $branchIds)
                ->count();
        }

        if ($user->hasPermission('pr.view')) {
            $data['cards']['pr_pending'] = PurchaseRequest::where('status', 'submitted')->count();
        }

        if ($user->hasPermission('reports.purchases')) {
            $data['cards']['payables'] = $reports->supplierPayables($branchIds)['grand_total'];
        }

        // Kartu keuangan (F1.6): laba bulan berjalan per cabang
        // terpagar cabang pemanggil; posisi kas hanya untuk aktor
        // berakses global (angka tingkat tenant).
        if ($user->hasPermission('reports.financial')) {
            $byBranch = collect($financial->incomeByBranch(now()->startOfMonth()->toDateString(), $today))
                ->filter(fn ($row) => $row['branch_id'] === null
                    ? $context->hasGlobalAccess()
                    : in_array($row['branch_id'], $branchIds, true))
                ->values()
                ->all();
            $monthNet = Money::zero();
            foreach ($byBranch as $row) {
                $monthNet = $monthNet->add($row['net']);
            }
            $data['cards']['finance'] = [
                'month_net' => $monthNet,
                'by_branch' => $byBranch,
                'branch_names' => Branch::pluck('name', 'id')->all(),
                'cash' => $context->hasGlobalAccess() ? $financial->cashPosition() : null,
            ];
        }

        return view('dashboard', $data);
    }
}
