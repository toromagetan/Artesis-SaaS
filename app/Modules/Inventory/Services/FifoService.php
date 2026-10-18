<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Models\StockLayerConsumption;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Purchasing\Models\PurchaseReturnItem;
use App\Modules\Purchasing\Models\ReceiptItem;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * FIFO per cabang (ADR-014, F4.2) — satu-satunya pengelola
 * stock_layers & stock_layer_consumptions.
 *
 * Dipanggil InventoryService di dalam transaksi dokumen sumber
 * (applyMovement) dan oleh perintah migrasi lentera:fifo-migrasi
 * (rebuildFromHistory memainkan ulang seluruh riwayat gerakan
 * dengan aturan yang sama persis).
 *
 * Kaidah:
 * - Gerakan keluar mengonsumsi lapisan tertua cabang (lockForUpdate
 *   per lapisan); retur pembelian mendahulukan lapisan receipt-nya
 *   sendiri. Nilai konsumsi dicap ke gerakan (total_cost).
 * - Gerakan masuk membentuk lapisan baru; qty-nya PERTAMA menutupi
 *   backorder tertua yang belum teralokasi (penangguhan HPP
 *   ADR-014) — konsumsi penutup tertaut ke gerakan keluar ASAL,
 *   dan nilainya dibaca listener Accounting sebagai jurnal HPP
 *   susulan (coverageValueForLayers).
 * - Void penjualan memulihkan konsumsi gerakan asalnya ke lapisan
 *   asal (qty_remaining kembali, baris konsumsi dihapus) karena
 *   penjualan & jurnalnya dibatalkan total.
 */
class FifoService
{
    /**
     * Terapkan efek FIFO satu gerakan yang BARU ditulis (atau sedang
     * dimainkan ulang): cap unit_cost/total_cost + ubah lapisan.
     *
     * $context: inboundUnitCost (string desimal), receiptItemId,
     * preferReceiptItemId, mirrorMovementId.
     */
    public function applyMovement(StockMovement $movement, array $context = []): void
    {
        $qty = (int) $movement->qty;
        if ($qty < 0) {
            $value = $this->consume($movement, -$qty, $context['preferReceiptItemId'] ?? null);
            $this->stamp($movement, $value, -$qty);

            return;
        }

        match ($movement->reason) {
            'sale_void' => $this->applyVoidRestore($movement),
            'transfer_in' => $this->applyTransferIn($movement, $context['mirrorMovementId'] ?? null),
            default => $this->applyPlainInbound($movement, $context),
        };
    }

    /** Gerakan masuk biasa: satu lapisan baru + tutupi backorder. */
    private function applyPlainInbound(StockMovement $movement, array $context): void
    {
        $unit = $context['inboundUnitCost']
            ?? (string) $movement->product->cost_price;
        $layer = $this->createLayer(
            $movement, (int) $movement->qty, $unit, $context['receiptItemId'] ?? null,
        );
        $this->stamp($movement, Money::fromDecimal($unit)->mul((int) $movement->qty), (int) $movement->qty);
        $this->coverBackorders($layer);
    }

    /** sale_void: pulihkan konsumsi gerakan penjualan asalnya. */
    private function applyVoidRestore(StockMovement $movement): void
    {
        $original = StockMovement::withoutGlobalScopes()
            ->where('reference_type', $movement->reference_type)
            ->where('reference_id', $movement->reference_id)
            ->where('reason', 'sale')
            ->where('product_id', $movement->product_id)
            ->where('branch_id', $movement->branch_id)
            ->where('id', '<', $movement->id)
            ->whereHas('consumptions')
            ->orderBy('id')
            ->first();
        if ($original === null) {
            $this->applyPlainInbound($movement, []);

            return;
        }
        $value = $this->restoreConsumptions($original);
        $this->stamp($movement, $value, (int) $movement->qty);
    }

    /** transfer_in: lapisan tujuan meniru rincian konsumsi gerakan keluarnya. */
    private function applyTransferIn(StockMovement $movement, ?int $mirrorMovementId): void
    {
        $mirror = $mirrorMovementId !== null
            ? StockMovement::withoutGlobalScopes()->find($mirrorMovementId)
            : StockMovement::withoutGlobalScopes()
                ->where('reference_type', $movement->reference_type)
                ->where('reference_id', $movement->reference_id)
                ->where('reason', 'transfer_out')
                ->where('product_id', $movement->product_id)
                ->where('id', '<', $movement->id)
                ->orderBy('id')
                ->first();
        $breakdown = $mirror !== null
            ? StockLayerConsumption::where('movement_id', $mirror->id)->orderBy('id')->get()
            : collect();
        if ($breakdown->isEmpty()) {
            $this->applyPlainInbound($movement, [
                'inboundUnitCost' => $mirror?->unit_cost !== null ? (string) $mirror->unit_cost : null,
            ]);

            return;
        }
        $total = Money::zero();
        $coveredQty = 0;
        foreach ($breakdown as $row) {
            $this->createLayer($movement, (int) $row->qty, (string) $row->unit_cost, null);
            $total = $total->add(Money::fromDecimal((string) $row->unit_cost)->mul((int) $row->qty));
            $coveredQty += (int) $row->qty;
        }
        $remaining = (int) $movement->qty - $coveredQty;
        if ($remaining > 0) {
            $unit = $mirror->unit_cost !== null ? (string) $mirror->unit_cost : (string) $movement->product->cost_price;
            $this->createLayer($movement, $remaining, $unit, null);
            $total = $total->add(Money::fromDecimal($unit)->mul($remaining));
        }
        $this->stamp($movement, $total, (int) $movement->qty);
        foreach ($movement->layers()->get() as $layer) {
            $this->coverBackorders($layer);
        }
    }

    public function createLayer(StockMovement $source, int $qty, string $unitCost, ?int $receiptItemId = null, bool $isOpening = false): StockLayer
    {
        return StockLayer::create([
            'product_id' => $source->product_id,
            'branch_id' => $source->branch_id,
            'receipt_item_id' => $receiptItemId,
            'source_movement_id' => $source->id,
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'unit_cost' => $unitCost,
            'is_opening' => $isOpening,
        ]);
    }

    /**
     * Konsumsi lapisan tertua untuk satu gerakan keluar; menulis
     * baris konsumsi & mengurangi qty_remaining. Mengembalikan
     * nilai yang TERKONSUMSI (bisa kurang dari kebutuhan bila
     * lapisan tidak cukup — sisanya backorder tertangguh).
     */
    public function consume(StockMovement $movement, int $qty, ?int $preferReceiptItemId = null): Money
    {
        $layers = StockLayer::where('product_id', $movement->product_id)
            ->where('branch_id', $movement->branch_id)
            ->where('qty_remaining', '>', 0)
            ->when($preferReceiptItemId !== null, function ($q) use ($preferReceiptItemId) {
                $q->orderByRaw('CASE WHEN receipt_item_id = ? THEN 0 ELSE 1 END', [$preferReceiptItemId]);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // F4.3: lapisan kedaluwarsa TIDAK PERNAH dikonsumsi oleh
        // penjualan — unit kedaluwarsa menunggu opname/write-off,
        // kekurangan penjualan menjadi backorder yang ditutup
        // penerimaan segar berikutnya.
        $skipExpired = $movement->reason === 'sale';
        $expiries = [];
        if ($skipExpired) {
            $receiptItemIds = $layers->pluck('receipt_item_id')->filter()->unique()->all();
            if ($receiptItemIds !== []) {
                $expiries = ReceiptItem::whereIn('id', $receiptItemIds)
                    ->pluck('expiry_date', 'id')->all();
            }
        }
        $today = now()->toDateString();

        $total = Money::zero();
        $need = $qty;
        foreach ($layers as $layer) {
            if ($need <= 0) {
                break;
            }
            if ($skipExpired && $layer->receipt_item_id !== null) {
                $expiry = $expiries[$layer->receipt_item_id] ?? null;
                $expiryStr = $expiry instanceof \DateTimeInterface ? $expiry->format('Y-m-d') : ($expiry !== null ? (string) $expiry : null);
                if ($expiryStr !== null && $expiryStr < $today) {
                    continue;
                }
            }
            $take = min($need, (int) $layer->qty_remaining);
            StockLayerConsumption::create([
                'movement_id' => $movement->id,
                'layer_id' => $layer->id,
                'qty' => $take,
                'unit_cost' => $layer->unit_cost,
            ]);
            $layer->update(['qty_remaining' => (int) $layer->qty_remaining - $take]);
            $total = $total->add(Money::fromDecimal((string) $layer->unit_cost)->mul($take));
            $need -= $take;
        }

        return $total;
    }

    /** Qty lapisan yang masih layak jual (belum kedaluwarsa) satu produk-cabang (F4.3). */
    public function sellableQty(int $productId, int $branchId): int
    {
        return (int) DB::table('stock_layers as l')
            ->leftJoin('receipt_items as ri', 'ri.id', '=', 'l.receipt_item_id')
            ->where('l.product_id', $productId)
            ->where('l.branch_id', $branchId)
            ->where('l.qty_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('ri.expiry_date')
                    ->orWhere('ri.expiry_date', '>=', now()->toDateString());
            })
            ->sum('l.qty_remaining');
    }

    /** Qty lapisan yang SUDAH kedaluwarsa satu produk-cabang (F4.3). */
    public function expiredQty(int $productId, int $branchId): int
    {
        return (int) DB::table('stock_layers as l')
            ->join('receipt_items as ri', 'ri.id', '=', 'l.receipt_item_id')
            ->where('l.product_id', $productId)
            ->where('l.branch_id', $branchId)
            ->where('l.qty_remaining', '>', 0)
            ->where('ri.expiry_date', '<', now()->toDateString())
            ->sum('l.qty_remaining');
    }

    /**
     * Lapisan yang kedaluwarsa atau akan kedaluwarsa dalam
     * $withinDays hari (termasuk yang sudah lewat), untuk
     * peringatan dashboard & daftar (PRD 4.4, F4.3).
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function expiringLayers(array $branchIds, int $withinDays = 90): array
    {
        if ($branchIds === []) {
            return [];
        }
        $today = now()->toDateString();
        $limit = now()->addDays($withinDays)->toDateString();

        return DB::table('stock_layers as l')
            ->join('receipt_items as ri', 'ri.id', '=', 'l.receipt_item_id')
            ->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('branches as b', 'b.id', '=', 'l.branch_id')
            ->whereIn('l.branch_id', $branchIds)
            ->where('l.qty_remaining', '>', 0)
            ->where('ri.expiry_date', '<=', $limit)
            ->orderBy('ri.expiry_date')
            ->get(['l.product_id', 'p.name as product_name', 'p.sku', 'l.branch_id', 'b.name as branch_name', 'ri.expiry_date', 'l.qty_remaining'])
            ->map(fn ($row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'branch_id' => (int) $row->branch_id,
                'branch_name' => $row->branch_name,
                'expiry_date' => (string) $row->expiry_date,
                'qty' => (int) $row->qty_remaining,
                'expired' => (string) $row->expiry_date < $today,
            ])->all();
    }

    /**
     * Tanggal kedaluwarsa terdekat per produk (lapisan bersisa),
     * untuk kolom daftar softlens (PRD 4.4).
     *
     * @param  int[]  $productIds
     * @param  int[]  $branchIds
     * @return array<int, array{date: string, expired: bool, soon: bool}>
     */
    public function nearestExpiries(array $productIds, array $branchIds): array
    {
        if ($productIds === [] || $branchIds === []) {
            return [];
        }
        $today = now()->toDateString();
        $soonLimit = now()->addDays(90)->toDateString();
        $rows = DB::table('stock_layers as l')
            ->join('receipt_items as ri', 'ri.id', '=', 'l.receipt_item_id')
            ->whereIn('l.product_id', $productIds)
            ->whereIn('l.branch_id', $branchIds)
            ->where('l.qty_remaining', '>', 0)
            ->whereNotNull('ri.expiry_date')
            ->groupBy('l.product_id')
            ->selectRaw('l.product_id, MIN(ri.expiry_date) as nearest')
            ->get();
        $map = [];
        foreach ($rows as $row) {
            $date = (string) $row->nearest;
            $map[(int) $row->product_id] = [
                'date' => $date,
                'expired' => $date < $today,
                'soon' => $date >= $today && $date <= $soonLimit,
            ];
        }

        return $map;
    }

    /** Pulihkan konsumsi satu gerakan (void): lapisan asal bertambah lagi. */
    public function restoreConsumptions(StockMovement $original): Money
    {
        $total = Money::zero();
        $rows = StockLayerConsumption::where('movement_id', $original->id)
            ->orderBy('id')->lockForUpdate()->get();
        foreach ($rows as $row) {
            $layer = StockLayer::where('id', $row->layer_id)->lockForUpdate()->first();
            if ($layer !== null) {
                $layer->update(['qty_remaining' => (int) $layer->qty_remaining + (int) $row->qty]);
            }
            $total = $total->add(Money::fromDecimal((string) $row->unit_cost)->mul((int) $row->qty));
            $row->delete();
        }

        return $total;
    }

    /**
     * Alokasikan sisa satu lapisan masuk ke gerakan keluar tertua
     * yang belum teralokasi penuh (backorder). Konsumsi penutup
     * tertaut ke gerakan keluar ASAL — bukan gerakan masuk ini.
     */
    public function coverBackorders(StockLayer $layer): Money
    {
        $total = Money::zero();
        $remaining = (int) $layer->qty_remaining;
        if ($remaining <= 0) {
            return $total;
        }
        $candidates = StockMovement::withoutGlobalScopes()
            ->where('product_id', $layer->product_id)
            ->where('branch_id', $layer->branch_id)
            ->where('qty', '<', 0)
            ->where('id', '<', $layer->source_movement_id)
            ->orderBy('id')
            ->get();
        foreach ($candidates as $outbound) {
            if ($remaining <= 0) {
                break;
            }
            $uncovered = $this->uncoveredQty($outbound);
            if ($uncovered <= 0) {
                continue;
            }
            $take = min($remaining, $uncovered);
            StockLayerConsumption::create([
                'movement_id' => $outbound->id,
                'layer_id' => $layer->id,
                'qty' => $take,
                'unit_cost' => $layer->unit_cost,
            ]);
            $layer->update(['qty_remaining' => (int) $layer->qty_remaining - $take]);
            $remaining -= $take;
            $total = $total->add(Money::fromDecimal((string) $layer->unit_cost)->mul($take));
        }

        return $total;
    }

    /** Qty gerakan keluar yang belum teralokasi ke lapisan mana pun. */
    public function uncoveredQty(StockMovement $movement): int
    {
        $consumed = (int) StockLayerConsumption::where('movement_id', $movement->id)->sum('qty');

        return max(0, abs((int) $movement->qty) - $consumed);
    }

    /**
     * Nilai HPP tertangguh yang ditutup lapisan-lapisan tertentu:
     * konsumsi yang gerakan keluarnya LEBIH DULU dari gerakan masuk
     * pemilik lapisan (movement_id < source_movement_id).
     *
     * @param  int[]  $layerIds
     */
    public function coverageValueForLayers(array $layerIds): Money
    {
        if ($layerIds === []) {
            return Money::zero();
        }
        $rows = DB::table('stock_layer_consumptions as c')
            ->join('stock_layers as l', 'l.id', '=', 'c.layer_id')
            ->whereIn('c.layer_id', $layerIds)
            ->whereColumn('c.movement_id', '<', 'l.source_movement_id')
            ->selectRaw('SUM(c.qty * c.unit_cost) as value')
            ->first();

        return Money::fromDecimal((string) ($rows->value ?? '0'));
    }

    /** Nilai persediaan FIFO satu produk-cabang = Σ lapisan tersisa. */
    public function inventoryValue(int $productId, int $branchId): Money
    {
        $value = DB::table('stock_layers')
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->selectRaw('SUM(qty_remaining * unit_cost) as value')
            ->value('value');

        return Money::fromDecimal((string) ($value ?? '0'));
    }

    /**
     * Peta nilai persediaan per "branch:product" untuk laporan.
     *
     * @param  int[]  $branchIds
     * @return array<string, Money>
     */
    public function inventoryValues(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }
        $rows = DB::table('stock_layers')
            ->whereIn('branch_id', $branchIds)
            ->groupBy('branch_id', 'product_id')
            ->selectRaw('branch_id, product_id, SUM(qty_remaining * unit_cost) as value')
            ->get();
        $map = [];
        foreach ($rows as $row) {
            $map[$row->branch_id.':'.$row->product_id] = Money::fromDecimal((string) ($row->value ?? '0'));
        }

        return $map;
    }

    /**
     * Harga satuan rata-rata tertimbang konsumsi gerakan penjualan
     * satu produk (untuk lapisan retur penjualan pada biaya asal).
     */
    public function saleMovementUnitCost(int $saleId, int $productId): ?string
    {
        $row = DB::table('stock_layer_consumptions as c')
            ->join('stock_movements as m', 'm.id', '=', 'c.movement_id')
            ->where('m.reference_type', 'sale')
            ->where('m.reference_id', $saleId)
            ->where('m.reason', 'sale')
            ->where('m.product_id', $productId)
            ->selectRaw('SUM(c.qty) as qty, SUM(c.qty * c.unit_cost) as value')
            ->first();
        if ($row === null || (int) ($row->qty ?? 0) <= 0) {
            return null;
        }
        $cents = Money::fromDecimal((string) $row->value)->cents();
        $qty = (int) $row->qty;
        $unit = intdiv($cents, $qty);
        if (($cents % $qty) * 2 >= $qty) {
            $unit++;
        }

        return Money::of($unit)->toDecimalString();
    }

    /** Cap biaya pada baris gerakan (transaksi pembuatan yang sama). */
    private function stamp(StockMovement $movement, Money $total, int $qtyAbs): void
    {
        $cents = $total->cents();
        $unitCents = $qtyAbs > 0 ? intdiv($cents, $qtyAbs) : 0;
        if ($qtyAbs > 0 && ($cents % $qtyAbs) * 2 >= $qtyAbs) {
            $unitCents++;
        }
        $movement->update([
            'total_cost' => $total->toDecimalString(),
            'unit_cost' => Money::of($unitCents)->toDecimalString(),
        ]);
    }

    /**
     * Migrasi FIFO (ADR-014): mainkan ulang SELURUH riwayat gerakan
     * dari keadaan kosong dengan aturan live yang sama, lalu laporkan
     * nilai lapisan per cabang untuk jurnal penyesuaian pembuka.
     * Berjalan dalam satu transaksi pemanggil.
     *
     * @return array<int, Money> branch_id => nilai lapisan
     */
    public function rebuildFromHistory(): array
    {
        DB::table('stock_layer_consumptions')->delete();
        DB::table('stock_layers')->delete();
        StockMovement::withoutGlobalScopes()->update(['unit_cost' => null, 'total_cost' => null]);

        $receiptQueues = [];
        $returnQueues = [];
        $movements = StockMovement::withoutGlobalScopes()->with('product')->orderBy('id')->get();
        foreach ($movements as $movement) {
            $context = [];
            if ($movement->reason === 'receipt') {
                $key = $movement->reference_id.':'.$movement->product_id;
                if (! isset($receiptQueues[$key])) {
                    $receiptQueues[$key] = ReceiptItem::query()
                        ->whereHas('receipt', fn ($q) => $q->where('id', $movement->reference_id))
                        ->whereHas('purchaseOrderItem', fn ($q) => $q->where('product_id', $movement->product_id))
                        ->orderBy('id')->get()->all();
                }
                $item = array_shift($receiptQueues[$key]);
                if ($item !== null) {
                    $context = ['inboundUnitCost' => (string) $item->unit_cost_actual, 'receiptItemId' => $item->id];
                }
            } elseif ($movement->reason === 'purchase_return') {
                $key = $movement->reference_id.':'.$movement->product_id;
                if (! isset($returnQueues[$key])) {
                    $returnQueues[$key] = PurchaseReturnItem::query()
                        ->where('purchase_return_id', $movement->reference_id)
                        ->whereHas('receiptItem.purchaseOrderItem', fn ($q) => $q->where('product_id', $movement->product_id))
                        ->orderBy('id')->get()->all();
                }
                $item = array_shift($returnQueues[$key]);
                if ($item !== null) {
                    $context = ['preferReceiptItemId' => $item->receipt_item_id];
                }
            } elseif ($movement->reason === 'sale_return') {
                $return = DB::table('sale_returns')->where('id', $movement->reference_id)->first();
                if ($return !== null) {
                    $unit = $this->saleMovementUnitCost((int) $return->sale_id, (int) $movement->product_id);
                    if ($unit !== null) {
                        $context = ['inboundUnitCost' => $unit];
                    }
                }
            }
            $this->applyMovement($movement, $context);
        }

        $values = [];
        foreach (DB::table('stock_layers')->selectRaw('branch_id, SUM(qty_remaining * unit_cost) as value')->groupBy('branch_id')->get() as $row) {
            $values[(int) $row->branch_id] = Money::fromDecimal((string) ($row->value ?? '0'));
        }

        return $values;
    }
}
