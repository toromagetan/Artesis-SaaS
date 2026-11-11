<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penanda request HTTP tamu (audit lanjutan temuan 8): request
 * web/api tanpa pengguna terautentikasi dinyatakan sebagai konteks
 * TAMU pada TenantContext, sehingga TenantScope/BranchScope
 * gagal-tertutup untuk seluruh query model terpagar selama request
 * itu. Penanda dibersihkan saat aplikasi terminate agar tidak
 * bocor ke request/pemanggilan berikutnya pada proses yang sama.
 * Request dengan aktor (sesi atau token) tidak ditandai — pagar
 * mengikuti mode aktor seperti biasa.
 */
class MarkGuestRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            TenantContext::markGuestHttp();
            app()->terminating(static fn () => TenantContext::forgetGuestHttp());
        }

        return $next($request);
    }
}
