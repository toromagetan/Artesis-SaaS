<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Sales\Models\InsuranceUsage;
use App\Modules\Sales\Services\InsuranceClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Klaim Asuransi: daftar pemakaian (penjualan aktif) + detail
 * halaman penuh + pencatatan penerimaan dari perusahaan
 * asuransi. Daftar/detail sepagar sales.view, terima sepagar
 * sales.pay; pagar cabang lewat BranchContext.
 */
class InsuranceClaimController extends Controller
{
    public function index(Request $request): View
    {
        $context = BranchContext::for($request->user());
        $usages = InsuranceUsage::with(['sale', 'insurance', 'customer', 'branch', 'receipts'])
            ->whereHas('sale', fn ($q) => $q->where('status', 'active'))
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('branch_id', $context->managedBranchIds()))
            ->latest('id')
            ->paginate(20);

        return view('sales.insurance-claims.index', ['usages' => $usages]);
    }

    public function show(Request $request, InsuranceUsage $usage): View
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($usage->branch_id);
        $usage->load(['sale', 'insurance', 'customer', 'branch', 'receipts.receiver']);

        return view('sales.insurance-claims.show', ['usage' => $usage]);
    }

    public function receive(Request $request, InsuranceUsage $usage, InsuranceClaimService $service): RedirectResponse
    {
        $context = BranchContext::for($request->user());
        $context->assertCanAccess($usage->branch_id);
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(InsuranceClaimService::METHODS)],
        ]);
        $service->receive($request->user(), $usage, (string) $validated['amount'], $validated['method']);

        return back()->with('status', __('ui.insurance_claims.received'));
    }
}
