<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\IncentiveRule;
use App\Modules\Accounting\Services\IncentiveService;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Insentif berkala: aturan + periode (generate → penyesuaian →
 * setujui → bayar). Sepagar payroll.manage; pagar cabang lewat
 * BranchContext. Persetujuan di service dibatasi Owner/Admin.
 */
class IncentiveController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $periods = IncentivePeriod::with(['branch', 'recipient'])
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('branch_id', $context->managedBranchIds()))
            ->latest('id')
            ->paginate(20);

        return view('accounting.incentives.index', [
            'periods' => $periods,
            'rules' => IncentiveRule::with('product')->latest('id')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->get(),
            'roles' => Role::orderBy('name')->pluck('name'),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name']),
        ]);
    }

    public function storeRule(Request $request, IncentiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'recipient_role' => ['required', Rule::exists('roles', 'name')],
            'product_id' => ['nullable', Rule::exists('products', 'id')],
            'calculation_type' => ['required', Rule::in(['percent', 'flat'])],
            'value' => ['required', 'numeric', 'gt:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);
        $service->createRule($request->user(), $validated);

        return back()->with('status', __('ui.incentives.rule_created'));
    }

    public function toggleRule(Request $request, IncentiveRule $rule, IncentiveService $service): RedirectResponse
    {
        $service->toggleRule($request->user(), $rule);

        return back()->with('status', __('ui.incentives.rule_toggled'));
    }

    public function generate(Request $request, IncentiveService $service): RedirectResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date'],
        ]);
        $context = BranchContext::for($request->user());
        $context->assertCanAccess((int) $validated['branch_id']);
        [$created, $skipped] = $service->generate(
            $request->user(),
            (int) $validated['branch_id'],
            $validated['period_start'],
            $validated['period_end'],
        );

        return back()->with('status', __('ui.incentives.generated', ['created' => $created, 'skipped' => $skipped]));
    }

    public function show(Request $request, IncentivePeriod $period): View
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($period->branch_id);
        $period->load(['branch', 'recipient', 'generator', 'approver', 'payer', 'items.rule', 'items.product', 'adjustments.creator']);

        return view('accounting.incentives.show', ['period' => $period]);
    }

    public function adjust(Request $request, IncentivePeriod $period, IncentiveService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($period->branch_id);
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'reason' => ['required', 'string', 'max:200'],
        ]);
        $service->addAdjustment($request->user(), $period, (string) $validated['amount'], $validated['reason']);

        return back()->with('status', __('ui.incentives.adjusted'));
    }

    public function approve(Request $request, IncentivePeriod $period, IncentiveService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($period->branch_id);
        $service->approve($request->user(), $period);

        return back()->with('status', __('ui.incentives.approved'));
    }

    public function pay(Request $request, IncentivePeriod $period, IncentiveService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($period->branch_id);
        $validated = $request->validate([
            'method' => ['required', Rule::in(IncentiveService::PAY_METHODS)],
        ]);
        $service->pay($request->user(), $period, $validated['method']);

        return back()->with('status', __('ui.incentives.paid'));
    }
}
