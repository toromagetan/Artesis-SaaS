<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Inventaris (Blueprint D.8) — SATU-SATUNYA penulis
 * stock_movements. Setiap modul meminta gerakan lewat sini.
 *
 * - recordMovement WAJIB dipanggil di dalam transaksi DB dokumen
 *   sumbernya (dijaga kontrak, bukan cek runtime): saldo dibaca dengan
 *   lockForUpdate atas gerakan terakhir (produk, cabang) sehingga
 *   transaksi paralel terserialisasi dan saldo tidak pernah balapan.
 * - Tipe ketat (frame, aksesori): saldo tidak boleh menjadi negatif.
 *   Tipe lensa (lens, rx, softlens) boleh negatif = backorder.
 * - Saldo resmi = balance_after gerakan terakhir (B.4).
 */
class InventoryService
{
    public function __construct(private readonly FifoService $fifo) {}

    /**
     * $fifoContext (F4.2, ADR-014): inboundUnitCost (harga lapisan
     * masuk), receiptItemId (tautan lapisan ke item penerimaan),
     * preferReceiptItemId (retur pembelian: dahulukan lapisan
     * receipt ini), mirrorMovementId (transfer masuk: tiru rincian
     * konsumsi gerakan keluar ini).
     */
    public function recordMovement(
        User $actor,
        Model $detail,
        Branch $branch,
        string $reason,
        int $qty,
        string $referenceType,
        int $referenceId,
        array $fifoContext = [],
    ): StockMovement {
        if (! in_array($reason, StockMovement::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('ui.stock.reason_invalid')]);
        }

        // Kunci opname (PRD 7.4, F4.1): selama opname cabang berstatus
        // counting/review, mutasi stok cabang itu terkunci kecuali
        // Owner. Gerakan 'opname' sendiri dikecualikan — approval
        // sudah mengangkat kunci lewat transisi status lebih dulu,
        // pengecualian ini pagar kedua untuk jalur internal.
        if ($reason !== 'opname' && ! $actor->hasRole('Owner')) {
            $lockedByOpname = StockOpname::withoutGlobalScopes()
                ->where('branch_id', $branch->id)
                ->whereIn('status', StockOpname::LOCKING_STATUSES)
                ->exists();
            if ($lockedByOpname) {
                throw ValidationException::withMessages([
                    'qty' => __('ui.opname.branch_locked', ['branch' => $branch->name]),
                ]);
            }
        }
        if ($qty === 0) {
            throw ValidationException::withMessages(['qty' => __('ui.stock.qty_zero')]);
        }

        $lastBalance = StockMovement::withoutGlobalScopes()
            ->where('detail_id', $detail->id)
            ->where('detail_type', $detail->getMorphClass())
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('balance_after') ?? 0;

        $newBalance = $lastBalance + $qty;

        // Add-on proses (aksesoris is_process_item, owner 2026-10-27)
        // dikecualikan dari pagar ketat: ia pekerjaan, bukan ready stok —
        // kekurangannya menjadi backorder PR seperti Lensa RX.
        $alias = $detail::DETAIL_TYPE;
        $isStrict = in_array($alias, StockMovement::STRICT_TYPES, true)
            && ! ($alias === 'accessory' && $detail->is_process_item);
        if ($newBalance < 0 && $isStrict) {
            throw ValidationException::withMessages([
                'qty' => __('ui.stock.insufficient', ['product' => $detail->product->name, 'branch' => $branch->name]),
            ]);
        }

        $movement = StockMovement::create([
            'detail_id' => $detail->id,
            'detail_type' => $detail->getMorphClass(),
            'branch_id' => $branch->id,
            'reason' => $reason,
            'qty' => $qty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'balance_after' => $newBalance,
            'created_by' => $actor->id,
        ]);

        // FIFO (ADR-014): cap biaya + efek lapisan pada transaksi
        // pembuatan yang sama — baris tidak pernah diubah sesudahnya.
        $this->fifo->applyMovement($movement, $fifoContext);

        return $movement;
    }

    /** Saldo resmi satu detail di satu cabang (0 bila belum ada gerakan). */
    public function currentStock(string $detailType, int $detailId, int $branchId): int
    {
        return StockMovement::withoutGlobalScopes()
            ->where('detail_type', $detailType)
            ->where('detail_id', $detailId)
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;
    }

    /**
     * Peta saldo terakhir per detail untuk satu cabang (A1:
     * berkunci komposit "alias:id" karena id detail hanya unik
     * per tabel tipenya).
     *
     * @return array<string, int> "detail_type:detail_id" => balance
     */
    public function balancesForBranch(int $branchId): array
    {
        $rows = DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereIn('sm.id', function ($query) use ($branchId) {
                $query->selectRaw('MAX(id)')
                    ->from('stock_movements')
                    ->where('branch_id', $branchId)
                    ->groupBy('detail_type', 'detail_id');
            })
            ->get(['sm.detail_type', 'sm.detail_id', 'sm.balance_after']);

        $map = [];
        foreach ($rows as $row) {
            $map[$row->detail_type.':'.$row->detail_id] = (int) $row->balance_after;
        }

        return $map;
    }
}
