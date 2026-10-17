<?php

namespace App\Modules\Lab\Events;

use App\Modules\Lab\Models\LensProcess;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Proses lensa selesai (Complete). Konsumen: Accounting —
 * akrual komisi teknisi/faset dari data final (F1.7).
 */
class LensProcessCompleted
{
    use Dispatchable;

    public function __construct(public readonly LensProcess $process) {}
}
