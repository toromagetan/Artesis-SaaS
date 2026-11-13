<?php

namespace App\Support;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Namespace kunci cache per pemilik (C-01; audit A-03, Blueprint
 * J.2 butir 5).
 *
 * Semua cache data bisnis WAJIB memakai awalan ember pemiliknya:
 *
 *   t{tenant_id}:…   mode tenant
 *   standalone:…     instalasi mandiri
 *   platform:…       aktor platform
 *   system:…         proses in-process tanpa aktor
 *
 * Ember diturunkan dari TenantContext aktor efektif (parameter
 * eksplisit mengalahkan sesi ambient — pola yang sama dengan
 * AuditLogger), bukan dari input pengguna. Mode tenant hanya sah
 * bila konteksnya valid (isValid()); tenant menggantung dan
 * unassigned DITOLAK dengan AuthorizationException — gagal-tertutup,
 * sejajar TenantStorage (F-01-R3).
 *
 * Tanpa aktor sama sekali kuncinya jatuh ke ember `system:`: kode
 * in-process tanpa aktor berada di dalam batas kepercayaan
 * (ADR-022), dan jalur HTTP tidak pernah mencapai cache bisnis
 * tanpa autentikasi + gerbang. Cache global tanpa awalan hanya
 * untuk data platform yang memang global (mis. definisi Plan yang
 * statis dari config) — bukan untuk data bisnis.
 */
class TenantCache
{
    public static function key(string $key, ?User $actor = null): string
    {
        $effectiveActor = $actor ?? auth()->user();

        if ($effectiveActor === null) {
            return 'system:'.$key;
        }

        $context = TenantContext::for($effectiveActor);

        $bucket = match (true) {
            $context->isTenant() && $context->isValid() => 't'.$context->tenantId(),
            $context->isStandalone() => 'standalone',
            $context->isPlatform() => 'platform',
            default => throw new AuthorizationException('Konteks aktor tidak berhak atas cache bisnis.'),
        };

        return $bucket.':'.$key;
    }
}
