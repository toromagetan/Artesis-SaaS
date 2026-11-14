<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockTransferItem;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Sales\Models\InsuranceUsage;
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
    /**
     * Histori pemakaian asuransi (paritas Artesis: laporan CRM dari
     * snapshot insurance_usages) — daftar pemakaian pada rentang
     * tanggal di cabang yang diizinkan, terbaru dulu.
     *
     * @return array<int, InsuranceUsage>
     */
    public function insuranceUsages(string $from, string $to, array $branchIds): array
    {
        return InsuranceUsage::withoutGlobalScopes()
            ->with(['sale', 'customer', 'insurance', 'branch'])
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('used_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->orderByDesc('used_at')
            ->orderByDesc('id')
            ->get()
            ->all();
    }

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
        // A1: saldo per DETAIL (morph) — nama/SKU dihidrasi dari
        // model detail per tipe karena tak ada satu tabel produk.
        $latest = StockMovement::withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->whereIn('branch_id', $branchIds)
            ->groupBy('detail_id', 'detail_type', 'branch_id');

        $balances = StockMovement::withoutGlobalScopes()
            ->joinSub($latest, 'latest', 'latest.id', '=', 'stock_movements.id')
            ->select('stock_movements.*')
            ->when($type !== null, fn ($q) => $q->where('stock_movements.detail_type', $type))
            ->get();

        $inTransitRows = StockTransferItem::query()
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.status', 'sent')
            ->whereIn('stock_transfers.to_branch_id', $branchIds)
            ->when($type !== null, fn ($q) => $q->where('stock_transfer_items.detail_type', $type))
            ->selectRaw('stock_transfers.to_branch_id as branch_id, stock_transfer_items.detail_id, stock_transfer_items.detail_type, SUM(stock_transfer_items.qty) as qty')
            ->groupBy('stock_transfers.to_branch_id', 'stock_transfer_items.detail_id', 'stock_transfer_items.detail_type')
            ->get();
        $inTransit = $inTransitRows->mapWithKeys(fn ($r) => [$r->branch_id.':'.$r->detail_type.':'.$r->detail_id => (int) $r->qty]);

        // Hidrasi detail per tipe (dengan master) untuk nama & SKU.
        $detailInfo = [];
        $needed = [];
        foreach ($balances as $m) {
            $needed[$m->detail_type][$m->detail_id] = true;
        }
        foreach ($inTransitRows as $r) {
            $needed[$r->detail_type][$r->detail_id] = true;
        }
        foreach ($needed as $alias => $ids) {
            $model = Product::DETAIL_MODELS[$alias] ?? null;
            if ($model === null) {
                continue;
            }
            foreach ($model::withoutGlobalScopes()->with('product')->whereIn('id', array_keys($ids))->get() as $detail) {
                $detailInfo[$alias.':'.$detail->id] = [
                    'product_name' => $detail->product?->name,
                    'sku' => $detail->sku,
                ];
            }
        }

        // Nilai persediaan FIFO (ADR-014): Σ lapisan tersisa per
        // detail-cabang — satu sumber dengan halaman Stok & jurnal.
        $fifoValues = $this->fifo->inventoryValues($branchIds);
        $result = $balances->map(function ($m) use ($inTransit, $fifoValues, $detailInfo) {
            $key = $m->detail_type.':'.$m->detail_id;
            $info = $detailInfo[$key] ?? ['product_name' => null, 'sku' => null];

            return [
                'branch_id' => $m->branch_id,
                'detail_id' => $m->detail_id,
                'detail_type' => $m->detail_type,
                'product_name' => $info['product_name'],
                'sku' => $info['sku'],
                'type' => $m->detail_type,
                'qty' => (int) $m->balance_after,
                'in_transit' => (int) ($inTransit[$m->branch_id.':'.$key] ?? 0),
                'value' => $fifoValues[$m->branch_id.':'.$key] ?? Money::zero(),
            ];
        })->sortBy(fn ($r) => $r['product_name'] ?? '')->values()->all();

        // Stok dalam perjalanan tetap terlihat walau cabang tujuan
        // belum memiliki gerakan apa pun (PRD 7.3).
        $known = collect($result)->map(fn ($r) => $r['branch_id'].':'.$r['detail_type'].':'.$r['detail_id'])->all();
        foreach ($inTransitRows as $row) {
            $key = $row->branch_id.':'.$row->detail_type.':'.$row->detail_id;
            if (! in_array($key, $known, true)) {
                $info = $detailInfo[$row->detail_type.':'.$row->detail_id] ?? ['product_name' => null, 'sku' => null];
                $result[] = [
                    'branch_id' => (int) $row->branch_id,
                    'detail_id' => (int) $row->detail_id,
                    'detail_type' => $row->detail_type,
                    'product_name' => $info['product_name'],
                    'sku' => $info['sku'],
                    'type' => $row->detail_type,
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
