<?php

namespace App\Http\Middleware;

use App\Modules\Platform\Services\ApiTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentikasi API read-only (F4.6): header Authorization Bearer.
 * Parameter rute = cakupan yang disyaratkan (mis. api.token:stock.read).
 * 401 tanpa token sah, 403 bila cakupan tak tercakup token.
 */
class AuthenticateApiToken
{
    public function __construct(private readonly ApiTokenService $tokens) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $this->tokens->verify($request->bearerToken());
        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! $token->allows($scope)) {
            return response()->json(['message' => 'Scope tidak tercakup token.'], 403);
        }
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
