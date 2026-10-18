<?php

namespace App\Modules\Lab\Events;

use App\Modules\Lab\Models\LabOrder;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Order lab diterima (status received). Konsumen: Accounting —
 * lab_cost diposting ke HPP (Dr HPP / Cr Utang Supplier) (F2.3).
 */
class LabOrderReceived
{
    use Dispatchable;

    public function __construct(public readonly LabOrder $order) {}
}
