<?php

namespace App\Http\Middleware;

use App\Support\PermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang izin per rute: ->middleware('permission:sales.view').
 * String izin divalidasi ke registry — string liar = bug (gagal keras
 * saat pengembangan, 500 terkendali di produksi, tidak pernah lolos diam).
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        PermissionRegistry::assertExists($permission);

        $user = $request->user();

        if (! $user || ! $user->hasPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
