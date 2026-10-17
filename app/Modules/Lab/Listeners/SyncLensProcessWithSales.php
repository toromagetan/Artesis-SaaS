<?php

namespace App\Modules\Lab\Listeners;

use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleVoided;

/**
 * Siklus hidup antrean lab mengikuti penjualan (M.5): penjualan berisi
 * item lensa membuat proses waiting; void membuang proses yang belum
 * selesai (void pada proses done sudah ditolak SalesService).
 */
class SyncLensProcessWithSales
{
    public function __construct(private readonly LensProcessService $lab) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $this->lab->ensureForSale($event->sale);
    }

    public function onSaleVoided(SaleVoided $event): void
    {
        $this->lab->removeForVoidedSale($event->sale);
    }
}
