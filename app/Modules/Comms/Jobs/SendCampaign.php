<?php

namespace App\Modules\Comms\Jobs;

use App\Modules\Comms\Models\WaCampaign;
use App\Modules\Comms\Services\CampaignService;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pengiriman kampanye WA di antrean (F3.8). Setiap penerima tetap
 * lewat MessagingService sehingga tercatat per pesan di wa_messages;
 * kampanye berhenti di status 'sending' bila job gagal total agar
 * terlihat dan dapat ditinjau (bukan 'done' semu).
 */
class SendCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $campaignId) {}

    public function handle(CampaignService $campaigns): void
    {
        // Konteks sistem eksplisit untuk job antrean (audit lanjutan
        // temuan 8): pengiriman kampanye tidak bergantung pada sesi.
        TenantContext::asSystem(function () use ($campaigns): void {
            $campaign = WaCampaign::find($this->campaignId);
            if ($campaign !== null) {
                $campaigns->deliver($campaign);
            }
        });
    }
}
