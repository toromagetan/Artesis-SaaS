<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\PaygCreditRequest;
use App\Modules\Platform\Services\PaygCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Keputusan platform atas pengajuan isi ulang saldo PAYG (bayar
 * per transaksi penjualan): konfirmasi menambah saldo tenant lewat
 * buku besar; penolakan menutup pengajuan tanpa pergerakan saldo.
 * Gerbang sama panel penagihan: middleware 'platform' + izin
 * tenants.manage.
 */
class PlatformPaygCreditController extends Controller
{
    public function __construct(private readonly PaygCreditService $credits) {}

    public function confirm(Request $request, PaygCreditRequest $paygCreditRequest): RedirectResponse
    {
        $this->credits->confirm($paygCreditRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.payg_credits.confirmed_flash'));
    }

    public function reject(Request $request, PaygCreditRequest $paygCreditRequest): RedirectResponse
    {
        $this->credits->reject($paygCreditRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.payg_credits.rejected_flash'));
    }
}
