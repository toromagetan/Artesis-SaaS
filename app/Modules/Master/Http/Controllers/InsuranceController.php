<?php

namespace App\Modules\Master\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Insurance;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master asuransi (paritas Artesis InsuranceController): nama +
 * claim_value (nilai klaim yang ditanggung) + aktif/nonaktif.
 * Tanpa hapus — nonaktif saja (aturan rumah tanpa-hapus).
 */
class InsuranceController extends Controller
{
    public function index(): View
    {
        return view('master.insurances.index', [
            'insurances' => Insurance::orderBy('name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('master.insurances.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'claim_value' => ['required', 'numeric', 'min:0'],
        ]);

        $insurance = Insurance::create($data + ['is_active' => true]);
        app(AuditLogger::class)->log(
            action: 'insurance.created',
            subjectType: 'insurance',
            subjectId: $insurance->id,
            newValues: ['name' => $insurance->name, 'claim_value' => (string) $insurance->claim_value],
            actor: $request->user(),
        );

        return redirect()->route('insurances.index')->with('status', __('ui.insurances.created'));
    }

    public function edit(Insurance $insurance): View
    {
        return view('master.insurances.edit', compact('insurance'));
    }

    public function update(Request $request, Insurance $insurance): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'claim_value' => ['required', 'numeric', 'min:0'],
        ]);

        $insurance->update($data);
        app(AuditLogger::class)->log(
            action: 'insurance.updated',
            subjectType: 'insurance',
            subjectId: $insurance->id,
            newValues: ['name' => $insurance->name, 'claim_value' => (string) $insurance->claim_value],
            actor: $request->user(),
        );

        return redirect()->route('insurances.index')->with('status', __('ui.insurances.updated'));
    }

    public function toggleActive(Request $request, Insurance $insurance): RedirectResponse
    {
        $insurance->update(['is_active' => ! $insurance->is_active]);
        app(AuditLogger::class)->log(
            action: 'insurance.toggled',
            subjectType: 'insurance',
            subjectId: $insurance->id,
            newValues: ['is_active' => $insurance->is_active],
            actor: $request->user(),
        );

        return back()->with('status', $insurance->is_active ? __('ui.insurances.activated') : __('ui.insurances.deactivated'));
    }
}
