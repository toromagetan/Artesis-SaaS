<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Services\PayrollService;
use App\Modules\Identity\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Payroll (F1.7): entri gaji per karyawan per periode + posting
 * periode. Semua aksi sepagar payroll.manage.
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll) {}

    public function index(Request $request): View
    {
        $period = $request->query('periode', now()->format('Y-m'));
        $entries = Payroll::with('employee')
            ->where('period', $period)
            ->orderBy('employee_user_id')
            ->get();
        $employees = User::where('is_active', true)->where('is_platform', false)->orderBy('name')->get(['id', 'name', 'username']);
        $totalNet = $entries->sum(fn (Payroll $p) => $p->net()->cents());
        $allPosted = $entries->isNotEmpty() && $entries->every(fn (Payroll $p) => $p->isPosted());

        return view('accounting.payroll.index', compact('entries', 'employees', 'period', 'allPosted'))
            ->with('totalNetFormatted', Money::of($totalNet)->format());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_user_id' => ['required', 'integer'],
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'base_amount' => ['required', 'numeric', 'min:0'],
            'allowance_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->payroll->upsertEntry(
            $request->user(),
            (int) $validated['employee_user_id'],
            $validated['period'],
            (string) $validated['base_amount'],
            (string) ($validated['allowance_amount'] ?? '0'),
            (string) ($validated['deduction_amount'] ?? '0'),
        );

        return redirect()->route('payroll.index', ['periode' => $validated['period']])
            ->with('status', __('ui.payroll.entry_saved'));
    }

    public function post(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'method' => ['required', 'in:cash,transfer'],
        ]);
        $journal = $this->payroll->postPeriod($request->user(), $validated['period'], $validated['method']);

        return redirect()->route('payroll.index', ['periode' => $validated['period']])
            ->with('status', $journal === null
                ? __('ui.payroll.nothing_to_post')
                : __('ui.payroll.posted', ['number' => $journal->number]));
    }
}
