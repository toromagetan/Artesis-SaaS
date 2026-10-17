<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Penjualan selesai dibuat. Konsumen: Lab (proses lensa, M.5),
 * Inventory (stok keluar, M.4), Accounting (jurnal, M.9).
 */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(public readonly Sale $sale) {}
}
