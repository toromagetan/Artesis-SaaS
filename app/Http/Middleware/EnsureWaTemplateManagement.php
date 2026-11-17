<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengelolaan template WhatsApp (audit W-01 W1-3): template adalah
 * rekaman BERSAMA se-instalasi — badan template invoice, pengingat,
 * dll. dipakai semua tenant — sehingga menyunting dan menyetujui
 * template TIDAK PERNAH dibuka untuk aktor tenant.
 *
 * Gerbang ini SENGAJA terpisah dari EnsureWaApiAccess (wa.api):
 * bila permukaan WA API suatu saat diaktifkan lagi untuk tenant,
 * gerbang ini tetap menahan pengelolaan template hanya untuk aktor
 * platform dan instalasi mandiri.
 */
class EnsureWaTemplateManagement
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
