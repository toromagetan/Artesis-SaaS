<?php

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\PurchaseReturn;
use Illuminate\Foundation\Events\Dispatchable;

/** Retur pembelian disimpan (konsumen: Accounting M.9). */
class PurchaseReturned
{
    use Dispatchable;

    public function __construct(public readonly PurchaseReturn $purchaseReturn) {}
}
