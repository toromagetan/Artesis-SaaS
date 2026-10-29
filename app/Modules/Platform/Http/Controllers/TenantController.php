<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Support\PlanFeatureRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Panel tenant SaaS (M3) — khusus aktor platform (gerbang middleware
 * 'platform' + izin tenants.manage). Membuat tenant, menetapkan
 * paket, memberi pengecualian fitur berbatas waktu, dan mengaktifkan/
 * menonaktifkan tenant (tanpa hapus — aturan berdiri owner).
 * Provisioning cabang + pengguna pemilik adalah modul M5.
 */
class TenantController extends Controller
{
    public function __construct(private readonly PlanService $plans) {}

    public function index(): View
    {
        return view('platform.tenants.index', [
            'tenants' => Tenant::with(['subscriptions.plan'])->withCount(['branches', 'users'])->latest('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('platform.tenants.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $slug = Str::slug($validated['name']);
        $base = $slug === '' ? 'tenant' : $slug;
        $slug = $base;
        $suffix = 2;
        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => true,
        ]);

        return redirect()
            ->route('platform.tenants.show', $tenant)
            ->with('status', __('ui.tenants.created'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['branches', 'users', 'subscriptions.plan', 'overrides']);

        return view('platform.tenants.show', [
            'tenant' => $tenant,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(),
            'featureKinds' => PlanFeatureRegistry::features(),
            'effectiveFeatures' => $this->plans->effectiveFeatures($tenant),
        ]);
    }

    public function assignPlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $plan = Plan::findOrFail((int) $validated['plan_id']);
        $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null;
        $this->plans->assignPlan($tenant, $plan, $expiresAt, $request->user());

        return back()->with('status', __('ui.tenants.assigned'));
    }

    public function storeOverride(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'feature_key' => ['required', 'string'],
            'enabled' => ['nullable', 'boolean'],
            'limit_value' => ['nullable', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        PlanFeatureRegistry::assertExists($validated['feature_key']);
        $expiresAt = isset($validated['expires_at']) ? Carbon::parse($validated['expires_at'])->endOfDay() : null;

        $this->plans->setOverride(
            $tenant,
            $validated['feature_key'],
            [
                'enabled' => isset($validated['enabled']) ? (bool) $validated['enabled'] : null,
                'limit_value' => $validated['limit_value'] ?? null,
            ],
            $expiresAt,
            $validated['reason'],
            $request->user(),
        );

        return back()->with('status', __('ui.tenants.override_saved'));
    }

    /** Aktif/nonaktif (tanpa hapus — aturan berdiri owner). */
    public function toggle(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return back()->with('status', __('ui.tenants.toggled'));
    }
}
