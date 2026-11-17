<?php

namespace App\Modules\Comms\Jobs;

use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pengiriman WhatsApp asinkron (Blueprint D.9): antrean + coba lagi
 * 3× dengan jeda menaik (60/300/900 detik). Tanpa gateway aktif pesan
 * DIBIARKAN 'queued' — bukan gagal. Kegagalan penyedia dicatat di
 * kolom error setiap percobaan; sesudah percobaan terakhir status
 * menjadi 'failed'. Pekerjaan ini tidak pernah menggagalkan transaksi
 * pemicu: ia berjalan di antrean database (ADR-009).
 *
 * Kedaluwarsa antrean (keputusan owner 17 Nov 2026, audit lanjutan
 * WA2-2 opsi B): pesan yang masih 'queued' sesudah EXPIRY_HOURS jam
 * ditandai 'failed' dengan error 'expired' dan TIDAK dikirim —
 * termasuk pesan yang tertahan selama tenant ditangguhkan lalu
 * aktif lagi, dan pesan yang menunggu konfigurasi gateway.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Batas umur pesan 'queued' sebelum kedaluwarsa (jam). */
    public const EXPIRY_HOURS = 24;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly int $messageId) {}

    public function handle(GatewayFactory $factory): void
    {
        // Job antrean berjalan tanpa sesi aktor: nyatakan konteks
        // sistem secara eksplisit agar query model terpagar tenant/
        // cabang di dalamnya tidak bergantung pada ketiadaan auth()
        // (audit lanjutan temuan 8).
        TenantContext::asSystem(fn () => $this->handleAsSystem($factory));
    }

    private function handleAsSystem(GatewayFactory $factory): void
    {
        $message = WaMessage::find($this->messageId);
        if ($message === null || $message->direction !== 'out' || $message->status !== 'queued') {
            return;
        }

        // Kedaluwarsa didahulukan dari semua penahanan lain: pesan
        // yang sudah melewati batas umur tidak boleh terkirim hanya
        // karena tenant aktif lagi atau gateway baru terpasang.
        if ($message->created_at->lte(now()->subHours(self::EXPIRY_HOURS))) {
            $message->update(['status' => 'failed', 'error' => 'expired']);

            return;
        }

        // Audit W-01 W1-4: pesan milik tenant NONAKTIF (tersuspensi)
        // tidak dikirim — tetap 'queued' sampai tenant aktif lagi.
        // Antrean tidak melewati middleware HTTP, jadi penegakan
        // suspensi harus hidup di sini juga.
        if ($message->tenant_id !== null
            && ! Tenant::whereKey($message->tenant_id)->value('is_active')) {
            return;
        }

        // WA per tenant: pesan selalu dikirim lewat gateway milik
        // tenant pemilik pesan; tenant tanpa gateway aktif membiarkan
        // pesan 'queued' (ADR-009), tidak menumpang gateway lain.
        $gateway = $factory->forTenant($message->tenant_id);
        if ($gateway === null) {
            return; // Menunggu konfigurasi gateway; tetap tercatat 'queued'.
        }

        try {
            $providerId = $gateway->send($message);
        } catch (GatewayException $e) {
            $message->update(['error' => mb_substr($e->getMessage(), 0, 255)]);
            throw $e;
        }

        $message->update([
            'status' => 'sent',
            'provider_message_id' => $providerId,
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    /** Dipanggil antrean sesudah percobaan terakhir gagal. */
    public function failed(GatewayException $exception): void
    {
        WaMessage::where('id', $this->messageId)
            ->where('status', 'queued')
            ->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 255),
            ]);
    }
}
