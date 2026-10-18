<?php

namespace App\Modules\Membership\Listeners;

use App\Modules\Membership\Services\PointsService;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleVoided;

/**
 * Sinkronisasi poin dengan penjualan (F3.6): earn saat penjualan
 * selesai; pembalikan penuh (earn + redeem) saat penjualan di-void.
 * Berjalan di transaksi penjualan yang sama — gagal di sini berarti
 * penjualan ikut gulung, menjaga invariant liabilitas = Σ poin aktif.
 */
class SyncPointsWithSales
{
    public function __construct(private readonly PointsService $points) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $this->points->earnForSale($event->sale);
    }

    public function onSaleVoided(SaleVoided $event): void
    {
        $this->points->reverseForVoidedSale($event->sale);
    }
}
