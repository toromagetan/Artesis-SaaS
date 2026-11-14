<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Models\AiCreditRequest;
use App\Modules\Platform\Services\AiCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Keputusan platform atas pengajuan isi ulang saldo AI (bayar per
 * penggunaan): konfirmasi menambah saldo tenant lewat buku besar;
 * penolakan menutup pengajuan tanpa pergerakan saldo. Gerbang sama
 * panel penagihan: middleware 'platform' + izin tenants.manage.
 */
class PlatformAiCreditController extends Controller
{
    public function __construct(private readonly AiCreditService $credits) {}

    public function confirm(Request $request, AiCreditRequest $aiCreditRequest): RedirectResponse
    {
        $this->credits->confirm($aiCreditRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.ai_credits.confirmed_flash'));
    }

    public function reject(Request $request, AiCreditRequest $aiCreditRequest): RedirectResponse
    {
        $this->credits->reject($aiCreditRequest, $request->user());

        return redirect()->route('platform.billing.index')
            ->with('status', __('ui.ai_credits.rejected_flash'));
    }
}
