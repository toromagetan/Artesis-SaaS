<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengelolaan template titik QC (audit isolasi menyeluruh
 * 18 Nov 2026, ISO-3): template adalah rekaman BERSAMA se-instalasi —
 * daftar periksa QC yang dipakai lab semua tenant — sehingga
 * membuat, menyunting, dan menonaktifkan titik QC TIDAK PERNAH
 * dibuka untuk aktor tenant; satu tenant tidak boleh mengubah
 * daftar periksa tenant lain.
 *
 * Gerbang ini sejajar EnsureWaTemplateManagement (wa.template-admin):
 * hanya aktor platform dan instalasi mandiri yang lolos. Aktor
 * tenant tetap dapat MEMBACA daftar titik (rute index) dan memakai
 * titik aktif pada formulir QC lab.
 */
class EnsureQcTemplateManagement
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = TenantContext::for($request->user());

        if (! $context->isPlatform() && ! $context->isStandalone()) {
            abort(403);
        }

        return $next($request);
    }
}
