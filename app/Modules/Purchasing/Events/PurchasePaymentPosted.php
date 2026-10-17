<?php

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\PurchasePayment;
use Illuminate\Foundation\Events\Dispatchable;

/** Pembayaran supplier terposting, tunggal maupun kolektif (konsumen: Accounting M.9). */
class PurchasePaymentPosted
{
    use Dispatchable;

    public function __construct(public readonly PurchasePayment $payment) {}
}
