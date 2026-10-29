<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Plan;
use App\Support\PlanFeatureRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel paket langganan (M3) — khusus aktor platform (gerbang
 * middleware 'platform' + izin plans.manage). Mengubah harga, status
 * aktif, dan matriks fitur/batas tiap paket; berlaku untuk penetapan
 * berikutnya (tenant berjalan memakai fitur efektif dari baris ini).
 */
class PlanController extends Controller
{
    public function index(): View
    {
        return view('platform.plans.index', [
            'plans' => Plan::with('features')->orderBy('sort_order')->get(),
            'featureKinds' => PlanFeatureRegistry::features(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.name' => ['required', 'string', 'max:100'],
            'plans.*.price_monthly' => ['required', 'integer', 'min:0'],
            'plans.*.extra_branch_price' => ['required', 'integer', 'min:0'],
            'plans.*.is_active' => ['sometimes', 'boolean'],
            'plans.*.features' => ['array'],
        ]);

        foreach ($validated['plans'] as $planId => $data) {
            $plan = Plan::findOrFail((int) $planId);
            $plan->update([
                'name' => $data['name'],
                'price_monthly' => (int) $data['price_monthly'],
                'extra_branch_price' => (int) $data['extra_branch_price'],
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            foreach (PlanFeatureRegistry::features() as $featureKey => $meta) {
                $raw = $data['features'][$featureKey] ?? null;
                if ($meta['kind'] === 'boolean') {
                    $plan->features()->updateOrCreate(
                        ['feature_key' => $featureKey],
                        ['enabled' => $raw === '1' || $raw === 1 || $raw === true, 'limit_value' => null],
                    );
                } else {
                    $limit = ($raw === '' || $raw === null) ? null : max(0, (int) $raw);
                    $plan->features()->updateOrCreate(
                        ['feature_key' => $featureKey],
                        ['enabled' => null, 'limit_value' => $limit],
                    );
                }
            }
        }

        return back()->with('status', __('ui.plans.saved'));
    }
}
