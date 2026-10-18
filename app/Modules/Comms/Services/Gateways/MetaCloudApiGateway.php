<?php

namespace App\Modules\Comms\Services\Gateways;

use App\Modules\Comms\Contracts\MessagingGateway;
use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Models\WaMessage;
use Illuminate\Support\Facades\Http;

/**
 * Meta WhatsApp Cloud API langsung (keputusan owner 2026-10-18).
 * POST /{api_version}/{phone_number_id}/messages dengan Bearer token
 * dari kredensial terenkripsi gateway aktif. Pesan teks bebas dipakai
 * untuk jendela layanan 24 jam; badan berasal dari template lokal
 * Lentera yang sudah dirender MessagingService.
 */
class MetaCloudApiGateway implements MessagingGateway
{
    /** @param array<string, string> $credentials */
    public function __construct(private readonly array $credentials) {}

    public function send(WaMessage $message): string
    {
        $phoneNumberId = (string) ($this->credentials['phone_number_id'] ?? '');
        $token = (string) ($this->credentials['access_token'] ?? '');
        $version = (string) ($this->credentials['api_version'] ?? 'v21.0');
        if ($phoneNumberId === '' || $token === '') {
            throw new GatewayException('Kredensial Meta belum lengkap (phone_number_id / access_token).');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(20)
            ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $message->phone,
                'type' => 'text',
                'text' => ['body' => $message->body],
            ]);

        if (! $response->successful()) {
            $detail = (string) ($response->json('error.message') ?? $response->status());
            throw new GatewayException('Meta Cloud API: '.mb_substr($detail, 0, 200));
        }

        $id = (string) ($response->json('messages.0.id') ?? '');
        if ($id === '') {
            throw new GatewayException('Meta Cloud API tidak mengembalikan ID pesan.');
        }

        return $id;
    }
}
