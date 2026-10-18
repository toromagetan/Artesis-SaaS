<?php

namespace App\Modules\Comms\Listeners;

use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\Sales\Events\SaleCompleted;

/** Invoice via WA sesudah transaksi penjualan selesai (F3.5). */
class SendSaleInvoiceMessage
{
    public function __construct(private readonly WaAutomationService $automation) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $this->automation->sendInvoiceForSale($event->sale);
    }
}
