<?php

namespace App\Modules\Sales\Events;

use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Retur penjualan tercatat. Konsumen: Inventory (stok kembali sesuai
 * keadaan barang, M.4), Accounting (jurnal balik retur, M.9).
 */
class SaleReturned
{
    use Dispatchable;

    public function __construct(public readonly SaleReturn $saleReturn) {}
}
