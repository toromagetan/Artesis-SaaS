<?php

namespace App\Modules\Lab\Services;

use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Sales\Models\Sale;
use Illuminate\Support\Collection;

/**
 * Status proses lensa TURUNAN (paritas Artesis, koreksi owner
 * 2026-10-18): dihitung dari rantai pengadaan penjualan — PR tertaut
 * -> PO -> penerimaan -> transfer — bukan kolom tersendiri.
 *
 * Tahap (kunci lang ui.lab.stages.*): stock (lensa dari stok cabang,
 * tanpa PR) -> pr -> po -> ordered -> received; shipped menyela bila
 * lensa sedang berpindah cabang lewat transfer dari PO terkait;
 * done bila proses lensa penjualan sudah selesai.
 */
class ProcurementStatusService
{
    private const LAB_TYPES = ['lens', 'rx', 'softlens'];

    public function stageFor(Sale $sale): ?string
    {
        $hasLabItem = $sale->items()->whereIn('type', self::LAB_TYPES)->exists();
        if (! $hasLabItem) {
            return null;
        }
        // Baca segar dari DB — status turunan tidak boleh bergantung
        // pada relasi yang mungkin basi di instance pemanggil.
        $processDone = LensProcess::where('sale_id', $sale->id)
            ->where('status', 'done')
            ->exists();
        if ($processDone) {
            return 'done';
        }

        $prs = $sale->purchaseRequests()
            ->whereIn('status', ['submitted', 'approved', 'converted'])
            ->get();
        if ($prs->isEmpty()) {
            return 'stock';
        }

        $orders = $this->ordersOf($prs);
        if ($orders->isEmpty()) {
            return 'pr';
        }

        // Transfer yang masih di jalan dari salah satu PO terkait:
        // lensa sedang dikirim ke cabang tujuannya.
        $inTransit = StockTransfer::withoutGlobalScopes()
            ->where('source_type', 'purchase_order')
            ->whereIn('source_id', $orders->pluck('id'))
            ->where('status', 'sent')
            ->exists();
        if ($inTransit) {
            return 'shipped';
        }
        if ($orders->every(fn (PurchaseOrder $po) => $po->status === 'complete')) {
            return 'received';
        }
        if ($orders->contains(fn (PurchaseOrder $po) => in_array($po->status, ['sent', 'partial'], true))) {
            return 'ordered';
        }

        return 'po';
    }

    /**
     * Lensa boleh mulai dikerjakan (edging) hanya bila sudah diterima:
     * tidak ada PR aktif yang menggantung — tanpa PR tertaut berarti
     * stok cabang memenuhi; dengan PR, semua PO-nya harus complete.
     * PR yang dibatalkan tidak menghalangi (gudang memutuskan manual).
     */
    public function lensesAvailable(Sale $sale): bool
    {
        $prs = $sale->purchaseRequests()
            ->whereIn('status', ['submitted', 'approved', 'converted'])
            ->get();
        if ($prs->isEmpty()) {
            return true;
        }
        $orders = $this->ordersOf($prs);

        return $orders->isNotEmpty()
            && $orders->every(fn (PurchaseOrder $po) => $po->status === 'complete');
    }

    /**
     * Semua PO yang lahir dari kumpulan PR (tautan Lentera:
     * purchase_request_items.purchase_order_id terisi saat konversi).
     *
     * @param  Collection<int, PurchaseRequest>  $prs
     * @return Collection<int, PurchaseOrder>
     */
    private function ordersOf($prs)
    {
        $poIds = $prs->flatMap(fn (PurchaseRequest $pr) => $pr->items()->pluck('purchase_order_id'))
            ->filter()
            ->unique();

        return PurchaseOrder::withoutGlobalScopes()
            ->whereIn('id', $poIds)
            ->get();
    }
}
