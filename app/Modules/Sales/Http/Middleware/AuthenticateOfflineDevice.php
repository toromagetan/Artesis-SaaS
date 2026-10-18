<?php

namespace App\Modules\Sales\Http\Middleware;

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
        $request->attributes->set('offline_device', $device);

        return $next($request);
    }
}
