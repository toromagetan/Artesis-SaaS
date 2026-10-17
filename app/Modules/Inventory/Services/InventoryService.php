<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Master\Models\Branch;
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
    public function recordMovement(
        User $actor,
        Product $product,
        Branch $branch,
        string $reason,
        int $qty,
        string $referenceType,
        int $referenceId,
    ): StockMovement {
        if (! in_array($reason, StockMovement::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => __('ui.stock.reason_invalid')]);
        }
        if ($qty === 0) {
            throw ValidationException::withMessages(['qty' => __('ui.stock.qty_zero')]);
        }

        $lastBalance = StockMovement::withoutGlobalScopes()
            ->where('product_id', $product->id)
            ->where('branch_id', $branch->id)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('balance_after') ?? 0;

        $newBalance = $lastBalance + $qty;

        if ($newBalance < 0 && in_array($product->type, StockMovement::STRICT_TYPES, true)) {
            throw ValidationException::withMessages([
                'qty' => __('ui.stock.insufficient', ['product' => $product->name, 'branch' => $branch->name]),
            ]);
        }

        return StockMovement::create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'reason' => $reason,
            'qty' => $qty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'balance_after' => $newBalance,
            'created_by' => $actor->id,
        ]);
    }

    /** Saldo resmi satu produk di satu cabang (0 bila belum ada gerakan). */
    public function currentStock(int $productId, int $branchId): int
    {
        return StockMovement::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->value('balance_after') ?? 0;
    }

    /**
     * Peta saldo terakhir per produk untuk satu cabang.
     *
     * @return array<int, int> product_id => balance
     */
    public function balancesForBranch(int $branchId): array
    {
        $rows = DB::table('stock_movements as sm')
            ->where('sm.branch_id', $branchId)
            ->whereIn('sm.id', function ($query) use ($branchId) {
                $query->selectRaw('MAX(id)')
                    ->from('stock_movements')
                    ->where('branch_id', $branchId)
                    ->groupBy('product_id');
            })
            ->pluck('sm.balance_after', 'sm.product_id');

        $map = [];
        foreach ($rows as $productId => $balance) {
            $map[(int) $productId] = (int) $balance;
        }

        return $map;
    }
}
