<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang aktor platform (M3): panel SaaS (paket & tenant) hanya untuk
 * pengguna is_platform. Ini batas keras yang berdiri sendiri di atas
 * izin — pemilik tenant memegang SEMUA izin registry lewat peran
 * Owner ('*'), jadi izin saja tidak cukup memisahkan panel platform
 * dari tenant. Pelaku bisnis selalu 403 di sini.
 */
class EnsurePlatformUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_platform, 403);

        return $next($request);
    }
}
