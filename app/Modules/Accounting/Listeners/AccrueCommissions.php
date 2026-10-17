<?php

namespace App\Modules\Accounting\Listeners;

use App\Modules\Accounting\Services\CommissionService;
use App\Modules\Lab\Events\LensProcessCompleted;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleVoided;

/**
 * Akrual komisi dari data final (F1.7): penjualan selesai →
 * komisi dokter; proses lensa Complete → komisi teknisi;
 * penjualan di-void → baris penyeimbang. Berjalan sinkron di
 * dalam transaksi sumber, pola sama dengan listener jurnal.
 */
class AccrueCommissions
{
    public function __construct(private readonly CommissionService $commissions) {}

    public function onSaleCompleted(SaleCompleted $event): void
    {
        $this->commissions->accrueForDoctor($event->sale);
    }

    public function onSaleVoided(SaleVoided $event): void
    {
        $this->commissions->correctForVoidedSale($event->sale);
    }

    public function onLensProcessCompleted(LensProcessCompleted $event): void
    {
        $this->commissions->accrueForTechnician($event->process);
    }
}
