<?php

namespace App\Modules\Comms\Services;

use App\Modules\Comms\Jobs\SendCampaign;
use App\Modules\Comms\Models\WaCampaign;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Kampanye WA (F3.8, Blueprint D.9): MENOLAK kirim tanpa persetujuan.
 *
 * Gerbang ganda sebelum satu pun pesan dibuat:
 * 1. kampanye berstatus approved dengan approved_by terisi
 *    (persetujuan memakai izin wa.campaigns.approve — hanya Owner);
 * 2. template kampanye juga wajib approved_by terisi.
 * Opt-out selalu dihormati oleh MessagingService di lapisan kirim
 * (baris log failed/opt_out tetap tercatat per penerima).
 *
 * Segmen penerima (JSON): {"type": "all"} |
 * {"type": "level", "level_id": N} | {"type": "branch", "branch_id": N}.
 * Hanya pelanggan aktif bernomor telepon yang menjadi penerima.
 */
class CampaignService
{
    public function __construct(
        private readonly MessagingService $messaging,
        private readonly AuditLogger $audit,
    ) {}

    /** @param  array<string, mixed>  $segment */
    public function create(User $actor, string $name, int $templateId, array $segment): WaCampaign
    {
        $campaign = WaCampaign::create([
            'name' => $name,
            'segment' => $segment,
            'template_id' => $templateId,
            'status' => 'draft',
            'created_by' => $actor->id,
        ]);
        $this->audit->log(action: 'wa.campaign.create', subjectType: 'wa_campaign', subjectId: $campaign->id, newValues: ['name' => $name], actor: $actor);

        return $campaign;
    }

    /** @return Collection<int, Customer> */
    public function resolveRecipients(WaCampaign $campaign): Collection
    {
        $segment = $campaign->segment ?? [];
        $type = (string) ($segment['type'] ?? 'all');

        return Customer::where('is_active', true)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->when($type === 'level', fn ($q) => $q->where('membership_level_id', (int) ($segment['level_id'] ?? 0)))
            ->when($type === 'branch', fn ($q) => $q->where('branch_id', (int) ($segment['branch_id'] ?? 0)))
            ->orderBy('id')
            ->get();
    }

    public function approve(User $actor, WaCampaign $campaign): WaCampaign
    {
        if (! $actor->hasPermission('wa.campaigns.approve')) {
            throw ValidationException::withMessages(['campaign' => __('ui.campaigns.approve_forbidden')]);
        }
        if ($campaign->status !== 'draft') {
            throw ValidationException::withMessages(['campaign' => __('ui.campaigns.not_draft')]);
        }
        $campaign->update(['status' => 'approved', 'approved_by' => $actor->id]);
        $this->audit->log(action: 'wa.campaign.approve', subjectType: 'wa_campaign', subjectId: $campaign->id, actor: $actor);

        return $campaign;
    }

    public function cancel(User $actor, WaCampaign $campaign): WaCampaign
    {
        if (! in_array($campaign->status, ['draft', 'approved'], true)) {
            throw ValidationException::withMessages(['campaign' => __('ui.campaigns.not_cancellable')]);
        }
        $campaign->update(['status' => 'cancelled']);
        $this->audit->log(action: 'wa.campaign.cancel', subjectType: 'wa_campaign', subjectId: $campaign->id, actor: $actor);

        return $campaign;
    }

    /**
     * Minta pengiriman: gerbang ganda diperiksa DI SINI — tanpa
     * approved_by kampanye atau template, tidak ada pesan yang dibuat.
     */
    public function requestSend(User $actor, WaCampaign $campaign): WaCampaign
    {
        if ($campaign->status !== 'approved' || $campaign->approved_by === null) {
            throw ValidationException::withMessages(['campaign' => __('ui.campaigns.not_approved')]);
        }
        $template = $campaign->template;
        if ($template === null || $template->approved_by === null) {
            throw ValidationException::withMessages(['campaign' => __('ui.campaigns.template_unapproved')]);
        }
        $campaign->update(['status' => 'sending']);
        $this->audit->log(action: 'wa.campaign.send', subjectType: 'wa_campaign', subjectId: $campaign->id, actor: $actor);
        SendCampaign::dispatch($campaign->id);

        return $campaign;
    }

    /** Eksekusi pengiriman (dipanggil job SendCampaign). */
    public function deliver(WaCampaign $campaign): void
    {
        $campaign = $campaign->refresh();
        if ($campaign->status !== 'sending') {
            return;
        }
        $template = $campaign->template;
        $count = 0;
        foreach ($this->resolveRecipients($campaign) as $customer) {
            $this->messaging->sendTemplate($template, $customer, [
                'cabang' => $customer->branch?->name ?? '',
            ], 'campaign', null, $campaign->id);
            $count++;
        }
        $campaign->update(['recipient_count' => $count, 'status' => 'done']);
    }
}
