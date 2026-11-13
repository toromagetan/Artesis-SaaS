<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang verifikasi email (B-01; keputusan kebijakan owner
 * 2026-11-13).
 *
 * Pengguna TENANT yang memiliki email tetapi belum
 * memverifikasinya boleh tetap masuk, namun hanya dapat membuka
 * halaman verifikasi, meminta pengiriman ulang, dan keluar —
 * seluruh rute bisnis lain dialihkan ke halaman verifikasi.
 *
 * Pembatasan melekat PER PENGGUNA (bukan per tenant): pengguna
 * lain yang sudah terverifikasi dalam tenant yang sama tidak
 * ikut terhalang. Aktor platform dan instalasi standalone
 * dikecualikan — rezim identitasnya berbeda dan tidak boleh
 * dirusak gerbang ini. Pengguna tanpa email juga dikecualikan:
 * tidak ada alamat yang dapat diverifikasi (identitasnya
 * dijamin pembuat akunnya, sesuai model pembuatan oleh
 * pengelola).
 */
class EnsureEmailVerified
{
    /** Rute yang tetap terbuka bagi pengguna belum terverifikasi. */
    private const ALLOWED_ROUTES = [
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        // Koreksi audit B-01-R2: permukaan PUBLIK ber-token bukan
        // sesi bisnis — portal member dan webhook Meta memakai
        // token mereka sendiri sebagai kredensial, dan tidak
        // boleh bergantung pada status verifikasi akun staf yang
        // kebetulan terautentikasi di browser yang sama.
        'portal.show',
        'wa-webhook.verify',
        'wa-webhook.handle',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null
            || $user->email === null
            || $user->hasVerifiedEmail()
            || ! TenantContext::for($user)->isTenant()
            || $request->routeIs(...self::ALLOWED_ROUTES)) {
            return $next($request);
        }

        return redirect()->route('verification.notice');
    }
}
