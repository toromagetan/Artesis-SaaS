<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PaygCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Isi ulang saldo transaksi PAYG dari sisi tenant (bayar per
 * transaksi penjualan, keputusan owner 2026-11-15): hanya tenant
 * berpaket PAYG yang boleh mengajukan; pembayaran dilakukan lewat
 * transfer ke rekening platform (halaman Paket Saya
 * menampilkannya) dan saldo bergerak hanya sesudah platform
 * mengonfirmasi.
 */
class PaygCreditController extends Controller
{
    public function store(Request $request, PaygCreditService $credits, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'credits' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $tenant = $request->user()->tenant;
        if ($tenant === null || $tenant->currentPlan()?->key !== 'payg') {
            abort(403);
        }

        $topup = $credits->requestTopup($tenant, (int) $validated['credits'], $request->user());

        $audit->log(
            action: 'payg_credit.request',
            subjectType: 'payg_credit_request',
            subjectId: $topup->id,
            newValues: ['credits' => $topup->credits, 'amount' => $topup->amount],
            actor: $request->user(),
        );

        return redirect()->route('plans.my')
            ->with('status', __('ui.payg_credits.requested_flash'));
    }
}
