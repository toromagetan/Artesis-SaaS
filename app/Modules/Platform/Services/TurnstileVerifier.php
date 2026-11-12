<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi Cloudflare Turnstile (S-05) untuk pendaftaran publik.
 *
 * Kontrak:
 * - Fitur AKTIF hanya bila site key DAN secret terisi di config
 *   (services.turnstile). Tanpa kunci, enabled() false dan
 *   pendaftaran berjalan seperti semula — penyebaran lokal/dev
 *   tidak pernah terkunci oleh layanan luar.
 * - Begitu aktif, verifikasi GAGAL-TERTUTUP: token kosong, respons
 *   Cloudflare success=false, respons tidak OK, dan kegagalan
 *   jaringan/timeout semuanya berarti DITOLAK. Token tidak pernah
 *   dicatat ke log.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret'));
    }

    public function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');

        return filled($key) ? (string) $key : null;
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! filled($token)) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => (string) config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

            return $response->ok() && $response->json('success') === true;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification unreachable; failing closed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
