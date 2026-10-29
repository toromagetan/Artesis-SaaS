<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Sisi tenant dari penagihan (M5): admin tenant mengajukan naik
 * paket dari halaman Paket Saya. Pembayaran = transfer manual;
 * konfirmasi dilakukan aktor platform.
 */
class BillingController extends Controller
{
    public function __construct(private readonly BillingService $billing) {}

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        abort_unless($tenant !== null, 403);

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'months' => ['required', 'integer', 'in:1,3,6,12'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        $plan = Plan::findOrFail($data['plan_id']);
        $this->billing->createRequest(
            $tenant,
            $plan,
            (int) $data['months'],
            $data['note'] ?? null,
            $request->user(),
        );

        return redirect()->route('plans.my')
            ->with('status', __('ui.billing.submitted'));
    }
}
