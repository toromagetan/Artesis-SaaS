<?php

namespace App\Modules\Platform\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifikasi Cloudflare Turnstile (S-05) untuk pendaftaran publik.
 *
 * Kontrak konfigurasi (koreksi audit S-05-01):
 * - Kedua kunci KOSONG: fitur nonaktif — penyebaran lokal/dev tidak
 *   pernah terkunci oleh layanan luar.
 * - Kedua kunci TERISI: fitur aktif, verifikasi wajib.
 * - Hanya SATU kunci terisi: konfigurasi TIDAK VALID (misconfigured)
 *   — pendaftaran DITOLAK dan kesalahan dicatat ke log, bukan
 *   diam-diam nonaktif. Kesalahan salin-tempel satu variabel di
 *   server tidak boleh mematikan anti-bot tanpa jejak.
 *
 * Kontrak verifikasi (begitu aktif, selalu GAGAL-TERTUTUP):
 * - Bentuk token tidak sah (bukan string / lebih dari 2.048
 *   karakter, karakteristik token Cloudflare) → ditolak sebelum
 *   jaringan dipanggil (S-05-03).
 * - Token kosong, respons tidak OK, success bukan true → ditolak.
 * - action respons harus "signup" (widget menetapkannya lewat
 *   data-action) dan hostname respons harus termasuk daftar
 *   hostname tepercaya yang dikonfigurasi eksplisit
 *   (services.turnstile.hostnames; bawaan: host dari APP_URL) —
 *   tidak pernah dari input pengguna (S-05-02). Pemeriksaan
 *   hostname TIDAK PERNAH dilewati: daftar tepercaya kosong atau
 *   respons tanpa hostname sama-sama ditolak (S-05-04).
 * - Kegagalan jaringan/timeout → ditolak. Token dan secret tidak
 *   pernah dicatat ke log.
 */
class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const EXPECTED_ACTION = 'signup';

    private const MAX_TOKEN_LENGTH = 2048;

    public function enabled(): bool
    {
        return filled(config('services.turnstile.site_key'))
            && filled(config('services.turnstile.secret'));
    }

    public function misconfigured(): bool
    {
        return filled(config('services.turnstile.site_key'))
            !== filled(config('services.turnstile.secret'));
    }

    /** Apakah jalur pendaftaran wajib melewati verifikasi anti-bot. */
    public function requiresVerification(): bool
    {
        return $this->enabled() || $this->misconfigured();
    }

    public function siteKey(): ?string
    {
        $key = config('services.turnstile.site_key');

        return filled($key) ? (string) $key : null;
    }

    /** @return list<string> */
    private function allowedHostnames(): array
    {
        $configured = config('services.turnstile.hostnames', []);
        $hosts = array_values(array_filter(array_map(
            fn ($host) => strtolower(trim((string) $host)),
            is_array($configured) ? $configured : [],
        )));

        if ($hosts !== []) {
            return $hosts;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return filled($appHost) ? [strtolower((string) $appHost)] : [];
    }

    public function verify(mixed $token, ?string $remoteIp = null): bool
    {
        if ($this->misconfigured()) {
            Log::error('Turnstile misconfigured: exactly one of site key / secret is set; denying signups.');

            return false;
        }

        if (! $this->enabled()) {
            return true;
        }

        if (! is_string($token) || ! filled($token) || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, array_filter([
                'secret' => (string) config('services.turnstile.secret'),
                'response' => $token,
                'remoteip' => $remoteIp,
            ]));

            if (! $response->ok() || $response->json('success') !== true) {
                return false;
            }

            if ($response->json('action') !== self::EXPECTED_ACTION) {
                return false;
            }

            // Hostname selalu diperiksa (S-05-04): tanpa daftar
            // tepercaya yang valid, tidak ada respons yang boleh
            // lolos — daftar kosong bukan alasan melewati gerbang.
            $allowed = $this->allowedHostnames();
            $hostname = $response->json('hostname');
            if ($allowed === []
                || ! is_string($hostname)
                || ! in_array(strtolower($hostname), $allowed, true)) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Turnstile verification unreachable; failing closed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
