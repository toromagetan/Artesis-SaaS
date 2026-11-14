<?php

namespace App\Modules\Inventory\Listeners;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleReturned;
use App\Modules\Sales\Events\SaleVoided;

/**
 * Integrasi stok ↔ penjualan (Blueprint D.8 + M.4): Inventory adalah
 * satu-satunya penulis gerakan; listener ini menerjemahkan peristiwa
 * Sales menjadi gerakan di dalam transaksi dokumen sumbernya
 * (event diterbitkan SalesService di dalam DB::transaction, sehingga
 * kegagalan stok membatalkan seluruh penjualan — nol tercatat sebagian).
 */
class SyncStockWithSales
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FifoService $fifo,
    ) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $sale = $event->sale;
        $actor = User::findOrFail($sale->cashier_id);
        $branch = Branch::findOrFail($sale->branch_id);

        foreach ($sale->items()->with('detail')->get() as $item) {
            if ($item->detail === null) {
                continue;
            }
            $this->inventory->recordMovement(
                $actor, $item->detail, $branch, 'sale',
                -$item->qty, 'sale', $sale->id,
            );
        }
    }

    public function onSaleVoided(SaleVoided $event): void
    {
        $sale = $event->sale;
        $actor = User::findOrFail($sale->voided_by ?? $sale->cashier_id);
        $branch = Branch::findOrFail($sale->branch_id);

        foreach ($sale->items()->with('detail')->get() as $item) {
            if ($item->detail === null) {
                continue;
            }
            $this->inventory->recordMovement(
                $actor, $item->detail, $branch, 'sale_void',
                $item->qty, 'sale', $sale->id,
            );
        }
    }

    public function onSaleReturned(SaleReturned $event): void
    {
        $return = $event->saleReturn;
        $actor = User::findOrFail($return->created_by);
        $branch = Branch::findOrFail($return->branch_id);

        foreach ($return->items()->with('saleItem.detail')->get() as $returnItem) {
            // Hanya barang layak jual (resale) yang kembali ke stok;
            // barang rusak tidak menambah stok jual (PRD 2.5).
            if ($returnItem->condition !== 'resale') {
                continue;
            }
            $detail = $returnItem->saleItem?->detail;
            if ($detail === null) {
                continue;
            }
            // Lapisan retur berharga biaya ASAL konsumsinya (ADR-014);
            // fallback cost_price detail bila penjualan asal belum
            // terkonsumsi lapisan (mis. data pra-FIFO).
            $unitCost = $this->fifo->saleMovementUnitCost((int) $return->sale_id, $detail::DETAIL_TYPE, $detail->id)
                ?? (string) $detail->cost_price;
            $this->inventory->recordMovement(
                $actor, $detail, $branch, 'sale_return',
                $returnItem->qty, 'sale_return', $return->id,
                ['inboundUnitCost' => $unitCost],
            );
        }
    }
}
