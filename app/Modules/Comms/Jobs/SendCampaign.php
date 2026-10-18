<?php

namespace App\Modules\Comms\Jobs;

use App\Modules\Comms\Models\WaCampaign;
use App\Modules\Comms\Services\CampaignService;
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
        $campaign = WaCampaign::find($this->campaignId);
        if ($campaign !== null) {
            $campaigns->deliver($campaign);
        }
    }
}
