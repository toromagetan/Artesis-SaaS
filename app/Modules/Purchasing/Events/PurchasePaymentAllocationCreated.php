<?php

namespace App\Modules\Purchasing\Events;

use App\Modules\Purchasing\Models\PurchasePaymentAllocation;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Satu alokasi pembayaran dibuat di dalam transaksi pembayaran.
 * Konsumen masa depan: notifikasi/laporan. Uji injeksi kegagalan
 * memakai peristiwa ini untuk meledak di tengah alokasi kolektif.
 */
class PurchasePaymentAllocationCreated
{
    use Dispatchable;

    public function __construct(public readonly PurchasePaymentAllocation $allocation) {}
}
