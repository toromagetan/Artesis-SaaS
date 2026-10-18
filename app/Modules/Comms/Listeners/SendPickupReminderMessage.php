<?php

namespace App\Modules\Comms\Listeners;

use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\Lab\Events\LensProcessCompleted;

/** Pengingat pengambilan via WA sesudah proses lensa Complete (F3.5). */
class SendPickupReminderMessage
{
    public function __construct(private readonly WaAutomationService $automation) {}

    public function onLensProcessCompleted(LensProcessCompleted $event): void
    {
        $this->automation->sendPickupReminder($event->process);
    }
}
