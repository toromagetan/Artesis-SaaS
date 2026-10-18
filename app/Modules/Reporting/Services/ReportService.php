<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransferItem;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleReturn;
use App\Support\Money;

/**
 * Laporan inti (Roadmap M.10). Setiap metode menerima daftar
 * cabang yang BOLEH dilihat pemanggil (sudah dipagari BranchContext
 * di controller) — service tidak pernah memperluas cakupan itu.
 * Ekspor memakai metode yang sama persis dengan layar (uji
 * kesetaraan total G.1.3).
 */
class ReportService
{
    public function __construct(private readonly FifoService $fifo) {}

    /**
     * Penjualan per cabang pada rentang tanggal (status aktif saja;
     * void tidak dihitung sebagai penjualan). Retur dikurangkan
     * terpisah menjadi neto.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function salesSummary(string $from, string $to, array $branchIds): array
    {
        $rows = Sale::withoutGlobalScopes()
            ->selectRaw('branch_id, COUNT(*) as tx, SUM(subtotal_amount) as subtotal, SUM(discount_amount) as discount, SUM(tax_amount) as tax, SUM(total_amount) as total, SUM(paid_amount) as paid')
            ->where('status', 'active')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sale_date', [$from, $to])
            ->groupBy('branch_id')
            ->get()
            ->keyBy('branch_id');

        $returns = SaleReturn::query()
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->selectRaw('sales.branch_id as branch_id, SUM(sale_returns.total_amount) as total')
            ->whereIn('sales.branch_id', $branchIds)
            ->whereBetween('sale_returns.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('sales.branch_id')
            ->pluck('total', 'branch_id');

        $result = [];
        foreach ($branchIds as $branchId) {
            $row = $rows->get($branchId);
            $total = Money::fromDecimal((string) ($row->total ?? '0'));
            $returnTotal = Money::fromDecimal((string) ($returns[$branchId] ?? '0'));
            $paid = Money::fromDecimal((string) ($row->paid ?? '0'));
            $result[] = [
                'branch_id' => $branchId,
                'transactions' => (int) ($row->tx ?? 0),
                'subtotal' => Money::fromDecimal((string) ($row->subtotal ?? '0')),
                'discount' => Money::fromDecimal((string) ($row->discount ?? '0')),
                'tax' => Money::fromDecimal((string) ($row->tax ?? '0')),
                'total' => $total,
                'returns' => $returnTotal,
                'net' => $total->sub($returnTotal),
                'paid' => $paid,
                // Piutang = neto sesudah retur − terbayar (paid_amount
                // sudah neto terhadap refund tunai retur).
                'receivable' => $total->sub($returnTotal)->sub($paid),
            ];
        }

        return $result;
    }

    /**
     * Pembelian NETO (kontrak Opsi 2): bruto penerimaan + kolom
     * Retur + neto, per supplier.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function purchasesNet(string $from, string $to, array $branchIds): array
    {
        $receipts = Receipt::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'receipts.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->select('receipts.*')
            ->selectRaw('suppliers.id as supplier_id, suppliers.name as supplier_name')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->whereBetween('receipts.received_date', [$from, $to])
            ->with('items')
            ->get();

        $perSupplier = [];
        foreach ($receipts as $receipt) {
            $key = $receipt->supplier_id;
            $perSupplier[$key] ??= ['supplier_id' => $key, 'supplier_name' => $receipt->supplier_name, 'receipts' => 0, 'gross' => Money::zero(), 'returns' => Money::zero()];
            $perSupplier[$key]['receipts']++;
            $perSupplier[$key]['gross'] = $perSupplier[$key]['gross']->add($receipt->totalAmount());
        }

        $returns = PurchaseReturn::query()
            ->join('receipts', 'receipts.id', '=', 'purchase_returns.receipt_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'receipts.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->selectRaw('suppliers.id as supplier_id, suppliers.name as supplier_name, SUM(purchase_returns.total_amount) as total')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->whereBetween('purchase_returns.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->get();
        foreach ($returns as $row) {
            $key = $row->supplier_id;
            $perSupplier[$key] ??= ['supplier_id' => $key, 'supplier_name' => $row->supplier_name, 'receipts' => 0, 'gross' => Money::zero(), 'returns' => Money::zero()];
            $perSupplier[$key]['returns'] = Money::fromDecimal((string) $row->total);
        }

        return collect($perSupplier)->map(function ($row) {
            $row['net'] = $row['gross']->sub($row['returns']);

            return $row;
        })->values()->all();
    }

    /**
     * Stok per cabang: saldo buku besar + nilai (qty>0 × harga pokok)
     * + qty dalam perjalanan masuk (transfer sent menuju cabang).
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function stockReport(array $branchIds, ?string $type = null): array
    {
        $latest = StockMovement::withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->whereIn('branch_id', $branchIds)
            ->groupBy('product_id', 'branch_id');

        $balances = StockMovement::withoutGlobalScopes()
            ->joinSub($latest, 'latest', 'latest.id', '=', 'stock_movements.id')
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->select('stock_movements.*', 'products.name as product_name', 'products.sku', 'products.type', 'products.cost_price')
            ->when($type !== null, fn ($q) => $q->where('products.type', $type))
            ->orderBy('products.name')
            ->get();

        $inTransitRows = StockTransferItem::query()
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->join('products', 'products.id', '=', 'stock_transfer_items.product_id')
            ->where('stock_transfers.status', 'sent')
            ->whereIn('stock_transfers.to_branch_id', $branchIds)
            ->when($type !== null, fn ($q) => $q->where('products.type', $type))
            ->selectRaw('stock_transfers.to_branch_id as branch_id, stock_transfer_items.product_id, SUM(stock_transfer_items.qty) as qty, products.name as product_name, products.sku, products.type')
            ->groupBy('stock_transfers.to_branch_id', 'stock_transfer_items.product_id', 'products.name', 'products.sku', 'products.type')
            ->get();
        $inTransit = $inTransitRows->mapWithKeys(fn ($r) => [$r->branch_id.':'.$r->product_id => (int) $r->qty]);

        // Nilai persediaan FIFO (ADR-014): Σ lapisan tersisa per
        // produk-cabang — satu sumber dengan halaman Stok & jurnal.
        $fifoValues = $this->fifo->inventoryValues($branchIds);
        $result = $balances->map(fn ($m) => [
            'branch_id' => $m->branch_id,
            'product_id' => $m->product_id,
            'product_name' => $m->product_name,
            'sku' => $m->sku,
            'type' => $m->type,
            'qty' => (int) $m->balance_after,
            'in_transit' => (int) ($inTransit[$m->branch_id.':'.$m->product_id] ?? 0),
            'value' => $fifoValues[$m->branch_id.':'.$m->product_id] ?? Money::zero(),
        ])->all();

        // Stok dalam perjalanan tetap terlihat walau cabang tujuan
        // belum memiliki gerakan apa pun (PRD 7.3).
        $known = collect($result)->map(fn ($r) => $r['branch_id'].':'.$r['product_id'])->all();
        foreach ($inTransitRows as $row) {
            $key = $row->branch_id.':'.$row->product_id;
            if (! in_array($key, $known, true)) {
                $result[] = [
                    'branch_id' => (int) $row->branch_id,
                    'product_id' => (int) $row->product_id,
                    'product_name' => $row->product_name,
                    'sku' => $row->sku,
                    'type' => $row->type,
                    'qty' => 0,
                    'in_transit' => (int) $row->qty,
                    'value' => Money::zero(),
                ];
            }
        }

        return $result;
    }

    /**
     * Utang supplier (kontrak INKLUSI): SEMUA receipt dengan sisa > 0
     * tanpa filter tanggal + kredit supplier dari retur yang sudah
     * lunas, per supplier.
     *
     * @param  int[]  $branchIds
     * @return array{suppliers: array<int, array<string, mixed>>, grand_total: Money}
     */
    public function supplierPayables(array $branchIds): array
    {
        $receipts = Receipt::query()
            ->join('purchase_orders', 'purchase_orders.id', '=', 'receipts.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->where('purchase_orders.status', '!=', 'void')
            ->select('receipts.*', 'suppliers.name as supplier_name', 'purchase_orders.number as po_number', 'purchase_orders.branch_id as po_branch_id')
            ->with(['items', 'allocations', 'returns'])
            ->orderBy('receipts.received_date')
            ->get();

        $suppliers = [];
        $grand = Money::zero();
        foreach ($receipts as $receipt) {
            $remaining = $receipt->remainingAmount();
            if (! $remaining->isPositive()) {
                continue;
            }
            $key = $receipt->supplier_name;
            $suppliers[$key] ??= ['supplier_name' => $key, 'receipts' => [], 'total' => Money::zero(), 'credit' => Money::zero()];
            $suppliers[$key]['receipts'][] = [
                'number' => $receipt->number,
                'po_number' => $receipt->po_number,
                'date' => $receipt->received_date,
                'total' => $receipt->totalAmount(),
                'allocated' => $receipt->allocatedAmount(),
                'remaining' => $remaining,
            ];
            $suppliers[$key]['total'] = $suppliers[$key]['total']->add($remaining);
            $grand = $grand->add($remaining);
        }

        // Kredit supplier: retur berstatus supplier_credit (uang kami di supplier).
        $credits = PurchaseReturn::query()
            ->join('receipts', 'receipts.id', '=', 'purchase_returns.receipt_id')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'receipts.purchase_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_returns.credit_status', 'supplier_credit')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->selectRaw('suppliers.name as supplier_name, SUM(purchase_returns.total_amount) as total')
            ->groupBy('suppliers.name')
            ->pluck('total', 'supplier_name');
        foreach ($credits as $name => $total) {
            $suppliers[$name] ??= ['supplier_name' => $name, 'receipts' => [], 'total' => Money::zero(), 'credit' => Money::zero()];
            $suppliers[$name]['credit'] = Money::fromDecimal((string) $total);
        }

        return ['suppliers' => array_values($suppliers), 'grand_total' => $grand];
    }
}
