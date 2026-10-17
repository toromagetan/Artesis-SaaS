<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Penjualan di-void dengan pembalikan penuh. Konsumen: Inventory
 * (stok kembali, M.4), Accounting (jurnal balik, M.9).
 */
class SaleVoided
{
    use Dispatchable;

    public function __construct(public readonly Sale $sale) {}
}
