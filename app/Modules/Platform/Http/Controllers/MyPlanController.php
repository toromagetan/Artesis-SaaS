<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AiCreditRequest;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\TenantBillingRequest;
use App\Modules\Platform\Services\AiCreditService;
use App\Modules\Platform\Services\BillingService;
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
        private readonly BillingService $billing,
        private readonly AiCreditService $aiCredits,
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

        // M5: bagian penagihan hanya untuk tenant SaaS.
        $paidPlans = collect();
        $monthlyTotals = [];
        $pendingRequest = null;
        if ($tenant !== null) {
            $paidPlans = Plan::where('is_active', true)
                ->where('price_monthly', '>', 0)
                ->orderBy('sort_order')
                ->get();
            $branchCount = max(1, $tenant->branches()->count());
            foreach ($paidPlans as $paidPlan) {
                $monthlyTotals[$paidPlan->id] = $this->billing->monthlyTotal($paidPlan, $branchCount);
            }
            $pendingRequest = $tenant->billingRequests()
                ->where('status', TenantBillingRequest::STATUS_PENDING)
                ->latest()
                ->first();
        }

        // AI bayar per penggunaan: saldo & pengajuan isi ulang hanya
        // untuk tenant yang paketnya membuka fitur asisten AI.
        $aiCredit = null;
        if ($tenant !== null && (bool) (($this->plans->effectiveFeatures($tenant))['ai_assistant'] ?? false)) {
            $aiCredit = [
                'balance' => $this->aiCredits->balance($tenant),
                'price' => $this->aiCredits->pricePerQuestion(),
                'pending' => $tenant->aiCreditRequests()
                    ->where('status', AiCreditRequest::STATUS_PENDING)
                    ->latest()
                    ->first(),
                'ledger' => $tenant->aiCreditLedger()->orderByDesc('id')->limit(10)->get(),
            ];
        }

        return view('platform.my-plan', [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
            'features' => $tenant !== null ? $this->plans->effectiveFeatures($tenant) : [],
            'registry' => PlanFeatureRegistry::features(),
            'usage' => $usage,
            'limits' => $limits,
            'paidPlans' => $paidPlans,
            'monthlyTotals' => $monthlyTotals,
            'pendingRequest' => $pendingRequest,
            'aiCredit' => $aiCredit,
            'bankName' => Setting::value('billing_bank_name'),
            'bankAccount' => Setting::value('billing_bank_account'),
            'accountName' => Setting::value('billing_account_name'),
        ]);
    }
}
