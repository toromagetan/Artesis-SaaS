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
    /** Gateway global instalasi (baris tenant_id NULL) — mode mandiri / platform. */
    public function activeGateway(): ?WaGateway
    {
        return WaGateway::activeForTenant(null);
    }

    public function active(): ?MessagingGateway
    {
        return $this->build($this->activeGateway());
    }

    /**
     * Gateway milik tenant tertentu (keputusan owner 2026-11-14):
     * tenant TIDAK pernah memakai gateway tenant lain atau gateway
     * global; belum punya gateway aktif = null (pesan menunggu).
     */
    public function forTenant(?int $tenantId): ?MessagingGateway
    {
        return $this->build(WaGateway::activeForTenant($tenantId));
    }

    public function gatewayRowForTenant(?int $tenantId): ?WaGateway
    {
        return WaGateway::activeForTenant($tenantId);
    }

    private function build(?WaGateway $gateway): ?MessagingGateway
    {
        if ($gateway === null) {
            return null;
        }

        return match ($gateway->provider) {
            'meta_cloud_api' => new MetaCloudApiGateway($gateway->credentials()),
            default => null,
        };
    }
}
