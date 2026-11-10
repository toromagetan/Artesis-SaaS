<?php

namespace App\Http\Middleware;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanGate;
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
    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly PlanGate $gate,
    ) {}

    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $this->tokens->verify($request->bearerToken());
        if ($token === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if (! $token->allows($scope)) {
            return response()->json(['message' => 'Scope tidak tercakup token.'], 403);
        }

        // S-01: token berhenti melayani begitu pembuatnya
        // dinonaktifkan, atau tenant pembuatnya dinonaktifkan
        // platform — lifecycle ditegakkan juga di jalur API.
        $creator = $token->created_by !== null ? User::find($token->created_by) : null;
        if ($creator !== null && ! $creator->is_active) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        if ($creator !== null && TenantContext::for($creator)->isSuspended()) {
            return response()->json(['message' => 'Tenant dinonaktifkan.'], 403);
        }

        // M4: akses API adalah fitur paket — token tenant tanpa fitur
        // api_access ditolak walau tokennya sah.
        if ($creator !== null && ! $this->gate->enabled($creator, 'api_access')) {
            return response()->json(['message' => 'Paket tenant tidak mencakup akses API.'], 403);
        }
        $request->attributes->set('api_token', $token);

        return $next($request);
    }
}
