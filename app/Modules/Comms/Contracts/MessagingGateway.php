<?php

namespace App\Modules\Comms\Contracts;

use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Models\WaMessage;

/**
 * Antarmuka gateway pesan (ADR-009): penyedia dapat diganti tanpa
 * mengubah pemanggil. Keputusan owner 2026-10-18: implementasi pertama
 * Meta Cloud API langsung; sifatnya sementara.
 */
interface MessagingGateway
{
    /**
     * Kirim satu pesan keluar; kembalikan ID pesan dari penyedia.
     *
     * @throws GatewayException bila penyedia menolak/gagal.
     */
    public function send(WaMessage $message): string;
}
