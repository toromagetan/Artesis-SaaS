<?php

namespace App\Modules\Comms\Services;

use App\Modules\Comms\Contracts\MessagingGateway;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Services\Gateways\MetaCloudApiGateway;

/**
 * Memilih implementasi gateway dari konfigurasi aktif (F3.4).
 * Mengembalikan null bila belum ada gateway aktif — pemanggil
 * membiarkan pesan berstatus 'queued' (tercatat, menunggu konfigurasi),
 * BUKAN menggagalkan transaksi pemicu (ADR-009).
 */
class GatewayFactory
{
    public function activeGateway(): ?WaGateway
    {
        return WaGateway::where('is_active', true)->orderBy('id')->first();
    }

    public function active(): ?MessagingGateway
    {
        $gateway = $this->activeGateway();
        if ($gateway === null) {
            return null;
        }

        return match ($gateway->provider) {
            'meta_cloud_api' => new MetaCloudApiGateway($gateway->credentials()),
            default => null,
        };
    }
}
