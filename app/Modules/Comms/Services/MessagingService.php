<?php

namespace App\Modules\Comms\Services;

use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Layanan pesan WhatsApp (Blueprint D.9).
 *
 * Setiap pesan keluar WAJIB lewat sini agar tercatat di wa_messages
 * (gerbang Fase 3: log 100%). Alur: render template -> tulis baris
 * log 'queued' -> antrekan SendWhatsAppMessage. Opt-out pelanggan
 * selalu dihormati: baris log tetap ditulis dengan status 'failed'
 * + error 'opt_out', tanpa pengiriman. Kegagalan antrean/gateway
 * tidak pernah melempar ke pemanggil — transaksi pemicu aman.
 */
class MessagingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Normalisasi nomor Indonesia ke format Meta: 62xxxxxxxxxx. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        return $digits;
    }

    /**
     * Kirim pesan berbasis template ke pelanggan.
     *
     * @param  array<string, scalar>  $context  variabel tambahan di luar
     *                                          {nama} & {nomor} bawaan.
     */
    public function send(string $templateKey, Customer $customer, array $context = [], string $trigger = 'manual', ?User $actor = null): WaMessage
    {
        $template = WaTemplate::where('key', $templateKey)->first();
        if ($template === null) {
            throw ValidationException::withMessages(['template' => __('ui.wa.template_missing')]);
        }

        return $this->sendTemplate($template, $customer, $context, $trigger, $actor);
    }

    /**
     * Kirim memakai REKAMAN template tertentu (dipakai kampanye F3.8 —
     * template kampanye dipilih per rekaman, bukan per kunci bawaan).
     *
     * @param  array<string, scalar>  $context
     */
    public function sendTemplate(WaTemplate $template, Customer $customer, array $context = [], string $trigger = 'manual', ?User $actor = null, ?int $campaignId = null): WaMessage
    {
        $body = $template->render(array_merge([
            'nama' => $customer->name,
            'nomor' => '',
        ], $context));

        return $this->queueOutbound($customer, $body, $template->key, $trigger, $actor, $campaignId);
    }

    /** Kirim teks bebas (uji kirim, balasan inbox). */
    public function sendText(Customer $customer, string $body, string $trigger = 'manual', ?User $actor = null): WaMessage
    {
        return $this->queueOutbound($customer, $body, null, $trigger, $actor);
    }

    private function queueOutbound(Customer $customer, string $body, ?string $templateKey, string $trigger, ?User $actor, ?int $campaignId = null): WaMessage
    {
        $phone = self::normalizePhone((string) $customer->phone);

        if ($customer->wa_opt_out) {
            return WaMessage::create([
                'customer_id' => $customer->id,
                'phone' => $phone,
                'template_key' => $templateKey,
                'body' => $body,
                'direction' => 'out',
                'status' => 'failed',
                'trigger' => $trigger,
                'campaign_id' => $campaignId,
                'sent_by' => $actor?->id,
                'error' => 'opt_out',
            ]);
        }

        $message = WaMessage::create([
            'customer_id' => $customer->id,
            'phone' => $phone,
            'template_key' => $templateKey,
            'body' => $body,
            'direction' => 'out',
            'status' => 'queued',
            'trigger' => $trigger,
            'campaign_id' => $campaignId,
            'sent_by' => $actor?->id,
        ]);

        if ($actor !== null) {
            $this->audit->log(
                action: 'wa.send',
                subjectType: 'wa_message',
                subjectId: $message->id,
                newValues: ['template_key' => $templateKey, 'trigger' => $trigger, 'customer_id' => $customer->id],
                branchId: $customer->branch_id,
                actor: $actor,
            );
        }

        try {
            // Dispatch polos di dalam transaksi pemanggil disengaja:
            // baris log + baris antrean commit/rollback BERSAMA transaksi
            // pemicu (pekerja antrean tidak dapat melihat baris yang
            // belum commit), dan tidak ada eksekusi tertunda yang bocor
            // ke fase commit transaksi.
            SendWhatsAppMessage::dispatch($message->id);
        } catch (\Throwable) {
            // Antrean bermasalah tidak boleh menggagalkan pemanggil;
            // baris log sudah tercatat 'queued' untuk dikirim ulang.
        }

        return $message;
    }
}
