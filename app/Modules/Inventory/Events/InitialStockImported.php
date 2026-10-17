<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Master\Models\Branch;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Satu komitmen impor stok awal selesai untuk satu cabang.
 * Konsumen: Accounting M.9 — jurnal Dr Persediaan / Cr Modal
 * Persediaan Awal sebesar $totalValue (Σ qty × harga pokok).
 */
class InitialStockImported
{
    use Dispatchable;

    public function __construct(
        public readonly Branch $branch,
        public readonly string $totalValue,
        public readonly int $batchReferenceId,
    ) {}
}
