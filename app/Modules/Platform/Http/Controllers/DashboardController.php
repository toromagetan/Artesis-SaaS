<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Platform\Services\BackupService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Support\Money;
use App\Support\SvgChart;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard operasional DINAMIS (keputusan owner 2026-10-19):
 * seluruh kartu + grafik mengikuti PERIODE terpilih (hari ini /
 * 7 hari / 30 hari / bulan ini) dan diperbarui tanpa muat ulang
 * lewat endpoint JSON /dashboard/data (pemuatan awal dirender
 * server; Alpine menukar fragmen HTML sesudahnya + auto-refresh
 * 60 detik). Setiap angka terpagar izin & cabang pemanggil —
 * kartu yang izinnya tidak dipegang tidak pernah dirender.
 * Grafik adalah SVG buatan server (App\Support\SvgChart).
 */
class DashboardController extends Controller
{
    public const PERIODS = ['today', '7d', '30d', 'month'];

    public function index(Request $request): View
    {
        $payload = $this->payload($request->user(), '30d');

        return view('dashboard', $payload);
    }

    public function data(Request $request): JsonResponse
    {
        $period = (string) $request->query('periode', '30d');
        if (! in_array($period, self::PERIODS, true)) {
            $period = '30d';
        }
        $payload = $this->payload($request->user(), $period);

        return response()->json([
            'period' => $period,
            'updated_at' => now()->format('H:i:s'),
            'cards_html' => view('dashboard.partials.cards', $payload)->render(),
            'charts_html' => view('dashboard.partials.charts', $payload)->render(),
        ]);
    }

    /**
     * Susun seluruh data dashboard untuk satu periode.
     *
     * @return array{period: string, cards: array<string, mixed>, charts: ?array<string, mixed>}
     */
    private function payload(User $user, string $period): array
    {
        $reports = app(ReportService::class);
        $financial = app(FinancialReportService::class);
        $context = BranchContext::for($user);
        $branchIds = $context->hasGlobalAccess()
            ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
        $today = now();
        [$from, $to] = match ($period) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay()],
            '7d' => [$today->copy()->subDays(6)->startOfDay(), $today->copy()->endOfDay()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfDay()],
            default => [$today->copy()->subDays(29)->startOfDay(), $today->copy()->endOfDay()],
        };
        $fromDate = $from->format('Y-m-d');
        $toDate = $to->format('Y-m-d');

        $cards = [];
        $charts = null;

        if ($user->hasPermission('sales.view')) {
            $periodSales = Sale::where('status', 'active')->whereBetween('sale_date', [$fromDate, $toDate]);
            $receivable = Money::zero();
            foreach (Sale::where('status', 'active')->get(['total_amount', 'paid_amount']) as $sale) {
                $receivable = $receivable->add(
                    Money::fromDecimal((string) $sale->total_amount)->sub(Money::fromDecimal((string) $sale->paid_amount)),
                );
            }
            $cards['sales'] = [
                'count' => (clone $periodSales)->count(),
                'total' => Money::fromDecimal((string) ((clone $periodSales)->sum('total_amount') ?? '0')),
                'receivable' => $receivable,
            ];
            $charts = $this->charts($from, $to);
        }

        if ($user->hasPermission('lab.view')) {
            $counts = LensProcess::query()
                ->join('sales', 'sales.id', '=', 'lens_processes.sale_id')
                ->whereIn('sales.branch_id', $branchIds)
                ->selectRaw('lens_processes.status, COUNT(*) as total')
                ->groupBy('lens_processes.status')
                ->pluck('total', 'status');
            $cards['lab'] = [
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
            $cards['stock'] = [
                'low' => $balances->filter(fn ($q) => $q >= 1 && $q <= 5)->count(),
                'out' => $balances->filter(fn ($q) => $q === 0)->count(),
                'backorder' => $balances->filter(fn ($q) => $q < 0)->count(),
                'expiring' => app(FifoService::class)->expiringLayers($branchIds, 90),
            ];
            $cards['transfers_in'] = StockTransfer::where('status', 'sent')
                ->whereIn('to_branch_id', $branchIds)
                ->count();
        }

        if ($user->hasPermission('pr.view')) {
            $cards['pr_pending'] = PurchaseRequest::where('status', 'submitted')->count();
        }

        if ($user->hasPermission('reports.purchases')) {
            $cards['payables'] = $reports->supplierPayables($branchIds)['grand_total'];
        }

        if ($user->hasPermission('reports.financial')) {
            $byBranch = collect($financial->incomeByBranch($fromDate, $toDate))
                ->filter(fn ($row) => $row['branch_id'] === null
                    ? $context->hasGlobalAccess()
                    : in_array($row['branch_id'], $branchIds, true))
                ->values()
                ->all();
            $periodNet = Money::zero();
            foreach ($byBranch as $row) {
                $periodNet = $periodNet->add($row['net']);
            }
            $cards['finance'] = [
                'net' => $periodNet,
                'by_branch' => $byBranch,
                'branch_names' => Branch::pluck('name', 'id')->all(),
                'cash' => $context->hasGlobalAccess() ? $financial->cashPosition() : null,
            ];
        }

        // Peringatan operasional (PRD-Detail 12.6) — hanya untuk
        // pemegang izin pengaturan: backup hilang/basi/rusak dan
        // job antrean gagal adalah hal yang wajib terlihat dini.
        $opsWarnings = [];
        if ($user->hasPermission('settings.manage')) {
            $health = app(BackupService::class)->health();
            if ($health['latest_at'] === null) {
                $opsWarnings[] = ['key' => 'backup_none', 'params' => []];
            } else {
                $ageHours = (int) floor((now()->timestamp - $health['latest_at']) / 3600);
                if ($ageHours > 48) {
                    $opsWarnings[] = ['key' => 'backup_stale', 'params' => ['hours' => $ageHours]];
                }
                if (! $health['latest_verified']) {
                    $opsWarnings[] = ['key' => 'backup_unverified', 'params' => []];
                }
            }
            if ($health['failed_jobs'] > 0) {
                $opsWarnings[] = ['key' => 'queue_failed', 'params' => ['count' => $health['failed_jobs']]];
            }
        }

        return ['period' => $period, 'cards' => $cards, 'charts' => $charts, 'ops_warnings' => $opsWarnings];
    }

    /**
     * Grafik dashboard untuk rentang periode: line tren penjualan
     * harian + doughnut komposisi per tipe produk.
     *
     * @return array{trend_svg: string, composition_svg: string, legend: list<array{label: string, color: string, value: Money, pct: float}>, composition_total: Money}
     */
    private function charts(Carbon $from, Carbon $to): array
    {
        $daily = Sale::where('status', 'active')
            ->whereBetween('sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->selectRaw('sale_date, SUM(total_amount) as total')
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $labels = [];
        $points = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $points[] = ($daily[$key] ?? 0) + 0;
        }
        $trendSvg = SvgChart::line($labels, [
            ['label' => __('ui.dashboard.sales_period'), 'color' => SvgChart::PALETTE[0], 'points' => $points],
        ]);

        $saleIds = Sale::where('status', 'active')
            ->whereBetween('sale_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->pluck('id');
        $byType = SaleItem::whereIn('sale_id', $saleIds)
            ->selectRaw('type, SUM(line_total) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $slices = [];
        $legend = [];
        $total = Money::zero();
        $i = 0;
        foreach (['frame', 'lens', 'rx', 'softlens', 'accessory'] as $type) {
            $value = Money::fromDecimal((string) ($byType[$type] ?? '0'));
            if ($value->isZero()) {
                continue;
            }
            $color = SvgChart::PALETTE[$i % count(SvgChart::PALETTE)];
            $label = __('ui.products.types.'.$type);
            $slices[] = ['label' => $label, 'value' => $value->cents() / 100, 'color' => $color];
            $legend[] = ['label' => $label, 'color' => $color, 'value' => $value, 'pct' => 0.0];
            $total = $total->add($value);
            $i++;
        }
        foreach (SvgChart::shares($slices) as $idx => $pct) {
            $legend[$idx]['pct'] = $pct;
        }
        $compositionSvg = SvgChart::doughnut($slices, SvgChart::shortMoney($total->cents() / 100), __('ui.dashboard.total'));

        return [
            'trend_svg' => $trendSvg,
            'composition_svg' => $compositionSvg,
            'legend' => $legend,
            'composition_total' => $total,
        ];
    }
}
