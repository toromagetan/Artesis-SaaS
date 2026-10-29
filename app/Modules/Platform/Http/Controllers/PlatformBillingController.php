<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\TenantBillingRequest;
use App\Modules\Platform\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panel penagihan platform (M5): rekening tujuan transfer (pengaturan
 * global) + daftar permintaan naik paket untuk dikonfirmasi/ditolak.
 * Gerbang ganda sama seperti panel tenant: middleware 'platform' +
 * izin tenants.manage.
 */
class PlatformBillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function index(): View
    {
        $requests = TenantBillingRequest::with(['tenant', 'plan'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(100)
            ->get();

        return view('platform.billing.index', [
            'requests' => $requests,
            'bankName' => Setting::value('billing_bank_name'),
            'bankAccount' => Setting::value('billing_bank_account'),
            'accountName' => Setting::value('billing_account_name'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'billing_bank_name' => ['nullable', 'string', 'max:100'],
            'billing_bank_account' => ['nullable', 'string', 'max:50'],
            'billing_account_name' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, (string) ($value ?? ''));
        }

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.billing.saved'));
    }

    public function confirm(Request $request, TenantBillingRequest $billingRequest): RedirectResponse
    {
        $this->billing->confirm($billingRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.billing.confirmed_flash'));
    }

    public function reject(Request $request, TenantBillingRequest $billingRequest): RedirectResponse
    {
        $this->billing->reject($billingRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.billing.rejected_flash'));
    }
}
