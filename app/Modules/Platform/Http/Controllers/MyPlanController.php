<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\PlanGate;
use App\Modules\Platform\Services\PlanService;
use App\Support\PlanFeatureRegistry;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman "Paket Saya" (M4): ringkasan paket efektif tenant aktor —
 * fitur yang terbuka/terkunci dan pemakaian terhadap batas angka.
 * Aktor tanpa tenant melihat status Instalasi Mandiri (tanpa batas).
 */
class MyPlanController extends Controller
{
    public function __construct(
        private readonly PlanGate $gate,
        private readonly PlanService $plans,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tenant = $user->tenant;
        $subscription = $tenant?->currentSubscription();

        $usage = [];
        $limits = [];
        foreach (['products_max', 'users_max', 'branches_max'] as $key) {
            $usage[$key] = $this->gate->usage($user, $key);
            $limits[$key] = $this->gate->limit($user, $key);
        }

        return view('platform.my-plan', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'features' => $tenant !== null ? $this->plans->effectiveFeatures($tenant) : [],
            'registry' => PlanFeatureRegistry::features(),
            'usage' => $usage,
            'limits' => $limits,
        ]);
    }
}
