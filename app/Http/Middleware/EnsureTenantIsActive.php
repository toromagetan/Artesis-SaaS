<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penegakan lifecycle tenant (S-01): tenant yang dinonaktifkan
 * platform berhenti beroperasi pada request berikutnya — termasuk
 * sesi yang sudah login — kecuali jalur pemulihan yang disetujui
 * pemilik proyek: logout dan halaman Paket Saya (melihat tagihan +
 * mengajukan pembayaran agar platform dapat mengaktifkan kembali).
 *
 * Permintaan web dialihkan ke Paket Saya dengan pemberitahuan;
 * permintaan JSON ditolak 403. Aktor platform, standalone, dan
 * tenant aktif tidak terpengaruh.
 */
class EnsureTenantIsActive
{
    /** Nama rute yang tetap terbuka untuk tenant nonaktif. */
    private const ALLOWED_ROUTES = ['logout', 'plans.my', 'billing.store'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! TenantContext::for($user)->isSuspended()) {
            return $next($request);
        }

        if ($request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('ui.plans.tenant_suspended'),
            ], 403);
        }

        return redirect()->route('plans.my')
            ->with('status', __('ui.plans.tenant_suspended'));
    }
}
