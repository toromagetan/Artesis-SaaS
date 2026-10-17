<?php

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\Receipt;
use Illuminate\Foundation\Events\Dispatchable;

/** Penerimaan disimpan (konsumen: Accounting M.9 — jurnal persediaan). */
class ReceiptStored
{
    use Dispatchable;

    public function __construct(public readonly Receipt $receipt) {}
}
