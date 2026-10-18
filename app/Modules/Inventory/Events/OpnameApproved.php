<?php

namespace App\Modules\Inventory\Events;

use App\Modules\Inventory\Models\StockOpname;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Opname disetujui & gerakan selisih sudah terbit (F4.1).
 * Didispatch OpnameService di dalam transaksi approval sesudah
 * gerakan 'opname' ditulis, agar listener jurnal (Accounting)
 * membaca gerakan final — kegagalan jurnal menggulung approval.
 */
class OpnameApproved
{
    use Dispatchable;

    public function __construct(public readonly StockOpname $opname) {}
}
