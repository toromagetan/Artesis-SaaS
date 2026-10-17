<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan locale aplikasi (ADR-011): preferensi pengguna yang login,
 * lalu sesi (tamu), lalu bawaan config. Nilai di luar daftar dukungan
 * jatuh ke locale bawaan.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['id', 'en']);
        $default = config('app.locale', 'id');

        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? $default;

        if (! in_array($locale, $supported, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
