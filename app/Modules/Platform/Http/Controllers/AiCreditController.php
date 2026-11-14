<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\AiCreditService;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;

/**
 * Isi ulang saldo pertanyaan AI dari sisi tenant (bayar per
 * penggunaan, keputusan owner 2026-11-14): tenant mengajukan jumlah
 * pertanyaan; pembayaran dilakukan lewat transfer ke rekening
 * platform (halaman Paket Saya menampilkannya) dan saldo bergerak
 * hanya sesudah platform mengonfirmasi.
 */
#[Middleware('plan.feature:ai_assistant')]
class AiCreditController extends Controller
{
    public function store(Request $request, AiCreditService $credits, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'questions' => ['required', 'integer', 'min:1', 'max:100000'],
        ]);

        $tenant = $request->user()->tenant;
        if ($tenant === null) {
            abort(403);
        }

        $topup = $credits->requestTopup($tenant, (int) $validated['questions'], $request->user());

        $audit->log(
            action: 'ai_credit.request',
            subjectType: 'ai_credit_request',
            subjectId: $topup->id,
            newValues: ['questions' => $topup->questions, 'amount' => $topup->amount],
            actor: $request->user(),
        );

        return redirect()->route('plans.my')
            ->with('status', __('ui.ai_credits.requested_flash'));
    }
}
