<?php

namespace App\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

/**
 * Namespace berkas unggahan per pemilik (F-01; audit A-01).
 *
 * Setiap berkas milik bisnis disimpan di bawah direktori ember
 * pemiliknya pada disk yang sama:
 *
 *   tenants/{tenant_id}/…   mode tenant
 *   standalone/…            instalasi mandiri
 *   platform/…              aktor platform (seharusnya jarang)
 *
 * Ember diturunkan dari TenantContext aktor — satu-satunya sumber
 * identitas tenant — bukan dari input pengguna, dan resolver ini
 * GAGAL-TERTUTUP (koreksi audit F-01-R3, sejajar disiplin
 * Setting::actorBucketForWrite): mode tenant hanya sah bila
 * konteksnya valid (baris tenant benar-benar ada, isValid());
 * tenant menggantung, unassigned, dan tamu DITOLAK dengan
 * AuthorizationException — tidak ada fallback ke tenant_id mentah
 * milik aktor. Berkas WAJIB ditulis lewat namespace ini agar
 * unggahan satu tenant tidak pernah berbagi direktori (atau nama
 * berkas) dengan tenant lain.
 */
class TenantStorage
{
    public static function directory(User $actor, string $base): string
    {
        $context = TenantContext::for($actor);

        $bucket = match (true) {
            $context->isTenant() && $context->isValid() => 'tenants/'.$context->tenantId(),
            $context->isStandalone() => 'standalone',
            $context->isPlatform() => 'platform',
            default => throw new AuthorizationException('Konteks aktor tidak berhak atas penyimpanan berkas bisnis.'),
        };

        return $bucket.'/'.trim($base, '/');
    }

    /**
     * URL tampil untuk path berkas tersimpan. Path berdirektori
     * dibaca dari disk publik; nama berkas polos adalah warisan
     * lama di akar web (mis. logo sebelum F-01) dan dirujuk apa
     * adanya.
     */
    public static function url(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return str_contains($path, '/')
            ? Storage::disk('public')->url($path)
            : asset($path);
    }
}
