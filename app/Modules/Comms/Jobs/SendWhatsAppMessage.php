<?php

namespace App\Modules\Comms\Jobs;

use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
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
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(public readonly int $messageId) {}

    public function handle(GatewayFactory $factory): void
    {
        $message = WaMessage::find($this->messageId);
        if ($message === null || $message->direction !== 'out' || $message->status !== 'queued') {
            return;
        }

        $gateway = $factory->active();
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
