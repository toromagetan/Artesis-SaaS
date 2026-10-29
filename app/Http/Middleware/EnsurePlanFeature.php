<?php

namespace App\Http\Middleware;

use App\Modules\Platform\Services\PlanGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware 'plan.feature:<kunci>' (M4): menolak (403) aktor tenant
 * SaaS yang paket efektifnya tidak membuka fitur tersebut. Aktor
 * tanpa tenant (instalasi mandiri) selalu lolos — lihat PlanGate.
 */
class EnsurePlanFeature
{
    public function __construct(private readonly PlanGate $gate) {}

    public function handle(Request $request, Closure $next, string $featureKey): Response
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        $this->gate->assertEnabled($user, $featureKey);

        return $next($request);
    }
}
