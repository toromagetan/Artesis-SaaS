<?php

namespace App\Modules\Reporting\Services;

use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Analitik (F4.5, PRD-Detail 10.4 & 10.5): angka analitik SELALU
 * rekonsiliasi dengan laporan sumber pada periode & pagar cabang
 * yang sama — nilai pembelian dari receipt_items aktual, margin
 * dari biaya FIFO gerakan 'sale' (total_cost), pendapatan dari
 * penjualan aktif. Rasio dihitung integer (basis poin / perseribu
 * hari) agar tak ada float pada jalur uang & statistik.
 */
class AnalyticsService
{
    /**
     * Per supplier: jumlah PO terkirim, lead time rata-rata
     * (order_date → terima pertama), nilai pembelian aktual, qty terima,
     * qty retur + tingkat retur, tingkat pemenuhan qty.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function supplierAnalytics(array $branchIds, string $from, string $to): array
    {
        if ($branchIds === []) {
            return [];
        }

        // Basis lead time: order_date PO yang sudah terkirim
        // (skema PO tak memiliki sent_at; status draft/void dikecualikan).
        $pos = DB::table('purchase_orders as po')
            ->join('suppliers as s', 's.id', '=', 'po.supplier_id')
            ->whereIn('po.branch_id', $branchIds)
            ->whereIn('po.status', ['sent', 'partial', 'complete'])
            ->whereBetween('po.order_date', [$from, $to])
            ->get(['po.id', 'po.supplier_id', 's.name as supplier_name', 'po.order_date']);

        $stats = [];
        foreach ($pos as $po) {
            $key = (int) $po->supplier_id;
            $stats[$key] ??= [
                'supplier_id' => $key,
                'supplier_name' => $po->supplier_name,
                'po_count' => 0,
                'lead_days_sum' => 0,
                'lead_count' => 0,
                'ordered_qty' => 0,
                'purchase_value' => Money::zero(),
                'received_qty' => 0,
                'returned_qty' => 0,
            ];
            $stats[$key]['po_count']++;
            $stats[$key]['ordered_qty'] += (int) DB::table('purchase_order_items')
                ->where('purchase_order_id', $po->id)->sum('qty_ordered');
            $firstReceipt = DB::table('receipts')
                ->where('purchase_order_id', $po->id)
                ->min('received_date');
            if ($firstReceipt !== null) {
                $days = (int) ((strtotime((string) $firstReceipt) - strtotime((string) $po->order_date)) / 86400);
                $stats[$key]['lead_days_sum'] += max(0, $days);
                $stats[$key]['lead_count']++;
            }
        }

        // Nilai & qty penerimaan pada periode (tanggal terima).
        $receiptRows = DB::table('receipt_items as ri')
            ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->whereIn('po.branch_id', $branchIds)
            ->whereBetween('r.received_date', [$from, $to])
            ->get(['po.supplier_id', 'ri.qty', 'ri.unit_cost_actual']);
        foreach ($receiptRows as $row) {
            $key = (int) $row->supplier_id;
            $stats[$key] ??= [
                'supplier_id' => $key,
                'supplier_name' => (string) DB::table('suppliers')->where('id', $key)->value('name'),
                'po_count' => 0, 'lead_days_sum' => 0, 'lead_count' => 0, 'ordered_qty' => 0,
                'purchase_value' => Money::zero(), 'received_qty' => 0, 'returned_qty' => 0,
            ];
            $stats[$key]['purchase_value'] = $stats[$key]['purchase_value']
                ->add(Money::fromDecimal((string) $row->unit_cost_actual)->mul((int) $row->qty));
            $stats[$key]['received_qty'] += (int) $row->qty;
        }

        // Retur pembelian pada periode (tanggal retur).
        $returnRows = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->join('receipt_items as ri', 'ri.id', '=', 'pri.receipt_item_id')
            ->join('receipts as r', 'r.id', '=', 'ri.receipt_id')
            ->join('purchase_orders as po', 'po.id', '=', 'r.purchase_order_id')
            ->whereIn('po.branch_id', $branchIds)
            ->whereBetween(DB::raw('DATE(pr.created_at)'), [$from, $to])
            ->get(['po.supplier_id', 'pri.qty']);
        foreach ($returnRows as $row) {
            $key = (int) $row->supplier_id;
            if (isset($stats[$key])) {
                $stats[$key]['returned_qty'] += (int) $row->qty;
            }
        }

        $out = [];
        foreach ($stats as $row) {
            $row['lead_time_days'] = $row['lead_count'] > 0
                ? self::ratioString($row['lead_days_sum'] * 100, $row['lead_count'], 2)
                : null;
            $row['return_rate_bp'] = $row['received_qty'] > 0
                ? intdiv($row['returned_qty'] * 10000, $row['received_qty'])
                : 0;
            $row['fulfillment_bp'] = $row['ordered_qty'] > 0
                ? intdiv(min($row['received_qty'], $row['ordered_qty']) * 10000, $row['ordered_qty'])
                : 0;
            unset($row['lead_days_sum'], $row['lead_count']);
            $out[] = $row;
        }
        usort($out, fn ($a, $b) => $b['purchase_value']->cmp($a['purchase_value']));

        return $out;
    }

    /**
     * Analitik penjualan: produk terlaris, margin per kategori
     * (biaya FIFO), tren per cabang per bulan, pelanggan teratas.
     *
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function salesAnalytics(array $branchIds, string $from, string $to): array
    {
        if ($branchIds === []) {
            return ['top_products' => [], 'category_margins' => [], 'branch_trend' => [], 'top_customers' => []];
        }

        $topProducts = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('s.status', 'active')
            ->whereIn('s.branch_id', $branchIds)
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('si.product_id', 'p.name', 'p.sku')
            ->orderByDesc(DB::raw('SUM(si.line_total)'))
            ->limit(10)
            ->get(['si.product_id', 'p.name', 'p.sku', DB::raw('SUM(si.qty) as qty'), DB::raw('SUM(si.line_total) as revenue')])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'name' => $row->name,
                'sku' => $row->sku,
                'qty' => (int) $row->qty,
                'revenue' => Money::fromDecimal((string) $row->revenue),
            ])->all();

        $revenueByType = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->where('s.status', 'active')
            ->whereIn('s.branch_id', $branchIds)
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('si.type')
            ->get(['si.type', DB::raw('SUM(si.line_total) as revenue')])
            ->mapWithKeys(fn ($row) => [$row->type => Money::fromDecimal((string) $row->revenue)]);

        $costByType = DB::table('stock_movements as m')
            ->join('products as p', 'p.id', '=', 'm.product_id')
            ->join('sales as s', 's.id', '=', 'm.reference_id')
            ->where('m.reason', 'sale')
            ->where('m.reference_type', 'sale')
            ->where('s.status', 'active')
            ->whereIn('m.branch_id', $branchIds)
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('p.type')
            ->get(['p.type', DB::raw('SUM(m.total_cost) as cost')])
            ->mapWithKeys(fn ($row) => [$row->type => Money::fromDecimal((string) ($row->cost ?? '0'))]);

        $categoryMargins = [];
        foreach ($revenueByType as $type => $revenue) {
            $cost = $costByType->get($type) ?? Money::zero();
            $categoryMargins[] = [
                'type' => $type,
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $revenue->sub($cost),
            ];
        }
        usort($categoryMargins, fn ($a, $b) => $b['margin']->cmp($a['margin']));

        $branchTrend = DB::table('sales as s')
            ->where('s.status', 'active')
            ->whereIn('s.branch_id', $branchIds)
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('s.branch_id', DB::raw("DATE_FORMAT(s.sale_date, '%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(s.sale_date, '%Y-%m')"))
            ->get(['s.branch_id', DB::raw("DATE_FORMAT(s.sale_date, '%Y-%m') as month"), DB::raw('COUNT(*) as sales_count'), DB::raw('SUM(s.total_amount) as revenue')])
            ->map(fn ($row) => [
                'branch_id' => (int) $row->branch_id,
                'month' => (string) $row->month,
                'sales_count' => (int) $row->sales_count,
                'revenue' => Money::fromDecimal((string) $row->revenue),
            ])->all();

        $topCustomers = DB::table('sales as s')
            ->join('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.status', 'active')
            ->whereIn('s.branch_id', $branchIds)
            ->whereBetween('s.sale_date', [$from, $to])
            ->groupBy('s.customer_id', 'c.name')
            ->orderByDesc(DB::raw('SUM(s.total_amount)'))
            ->limit(10)
            ->get(['s.customer_id', 'c.name', DB::raw('COUNT(*) as sales_count'), DB::raw('SUM(s.total_amount) as revenue')])
            ->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'name' => $row->name,
                'sales_count' => (int) $row->sales_count,
                'revenue' => Money::fromDecimal((string) $row->revenue),
            ])->all();

        return [
            'top_products' => $topProducts,
            'category_margins' => $categoryMargins,
            'branch_trend' => $branchTrend,
            'top_customers' => $topCustomers,
        ];
    }

    /** Rasio integer → string desimal (mis. 750/100 → "7.50"). */
    private static function ratioString(int $scaled, int $divisor, int $scaleDigits): string
    {
        $value = intdiv($scaled, $divisor);
        $whole = intdiv($value, 100);
        $fraction = abs($value % 100);

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
