<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Services\CommissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Komisi (F1.7): daftar akrual/koreksi, posting periode ke
 * Utang Komisi, dan pembayaran per penerima. Sepagar
 * payroll.manage (izin yang sama untuk Payroll & Komisi).
 */
class CommissionController extends Controller
{
    public function __construct(private readonly CommissionService $commissions) {}

    public function index(Request $request): View
    {
        $period = $request->query('periode', now()->format('Y-m'));
        $rows = Commission::with(['doctor', 'beneficiaryUser', 'sale'])
            ->where('period', $period)
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Ringkasan belum dibayar per penerima (terjurnal saja yang bisa dibayar).
        $payables = Commission::with(['doctor', 'beneficiaryUser'])
            ->whereNotNull('journal_id')
            ->whereIn('status', ['accrued', 'corrected'])
            ->get()
            ->groupBy(fn (Commission $c) => $c->type.':'.($c->beneficiary_doctor_id ?? $c->beneficiary_user_id))
            ->map(function ($group) {
                $first = $group->first();
                $net = $group->sum(fn (Commission $c) => $c->amountMoney()->cents());

                return [
                    'type' => $first->type,
                    'doctor_id' => $first->beneficiary_doctor_id,
                    'user_id' => $first->beneficiary_user_id,
                    'name' => $first->type === 'doctor' ? $first->doctor?->name : $first->beneficiaryUser?->name,
                    'net_cents' => $net,
                ];
            })
            ->filter(fn ($row) => $row['net_cents'] > 0)
            ->values();

        return view('accounting.commissions.index', compact('rows', 'period', 'payables'));
    }

    public function post(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $journal = $this->commissions->postPeriod($request->user(), $validated['period']);

        return redirect()->route('commissions.index', ['periode' => $validated['period']])
            ->with('status', $journal === null
                ? __('ui.payroll.nothing_to_post')
                : __('ui.payroll.posted', ['number' => $journal->number]));
    }

    public function pay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:doctor,technician,sales'],
            'doctor_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'method' => ['required', 'in:cash,transfer'],
        ]);
        $this->commissions->payBeneficiary(
            $request->user(),
            $validated['type'],
            isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null,
            isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            $validated['method'],
        );

        return redirect()->route('commissions.index')->with('status', __('ui.payroll.paid_msg'));
    }
}
