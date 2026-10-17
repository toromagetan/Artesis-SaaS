<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pengguna yang dinonaktifkan di tengah sesi langsung dikeluarkan
 * (matriks keamanan G.1.2 kasus e: penolakan berlaku di semua rute).
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'login' => __('auth.inactive'),
            ]);
        }

        return $next($request);
    }
}
