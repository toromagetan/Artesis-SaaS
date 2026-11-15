<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Skema WhatsApp (keputusan owner 15 Nov 2026): tenant memakai tombol
 * Kirim WA (wa.me) yang membuka aplikasi WhatsApp staf, sehingga
 * permukaan API — gateway, inbox, kampanye, log, dan template —
 * dinonaktifkan untuk aktor tenant. Kode API tetap berjalan untuk
 * aktor platform dan instalasi mandiri; aktor lain menerima 403.
 */
class EnsureWaApiAccess
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
