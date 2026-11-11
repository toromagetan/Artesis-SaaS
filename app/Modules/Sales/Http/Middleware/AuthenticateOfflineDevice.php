<?php

namespace App\Modules\Sales\Http\Middleware;

use App\Modules\Identity\Services\TenantContext;
use App\Modules\Sales\Services\OfflineDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi perangkat kasir offline (F5.3) — header
 * Authorization: Bearer <token perangkat>. Perangkat sah ditaruh
 * di atribut request 'offline_device'.
 */
class AuthenticateOfflineDevice
{
    public function __construct(private readonly OfflineDeviceService $devices) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $device = $token !== null && $token !== '' ? $this->devices->verify($token) : null;
        if ($device === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        // verify() sudah gagal-tertutup bila akun perangkat hilang
        // atau nonaktif (LNT-006). S-01: tenant yang dinonaktifkan
        // platform juga menghentikan perangkatnya, selaras token API.
        $user = $device->user;
        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (TenantContext::for($user)->isSuspended()) {
            return response()->json(['message' => 'Tenant dinonaktifkan.'], 403);
        }
        // Akun perangkat dipasang sebagai aktor permintaan (pola
        // yang sama dengan AuthenticateApiToken): pagar query
        // berbasis aktor (TenantScope/BranchScope) kini berlaku di
        // jalur perangkat. Tanpa aktor, snapshot katalog/pelanggan
        // dan pencarian duplikat sinkronisasi membaca lintas tenant
        // begitu penanda tamu dicabut — kebocoran P1 pada koreksi
        // audit PR #33 putaran 3. Token perangkat sah juga berarti
        // request berkredensial (bukan tamu): cabut penanda tamu.
        auth()->setUser($user);
        TenantContext::forgetGuestHttp();
        $request->attributes->set('offline_device', $device);

        return $next($request);
    }
}
