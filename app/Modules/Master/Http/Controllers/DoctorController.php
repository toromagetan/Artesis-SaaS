<?php

namespace App\Modules\Master\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Doctor;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master dokter perujuk (PRD-DETAIL §12.4; audit 23 Okt 2026 B1) —
 * nama, klinik, kontak, tarif komisi, aktif/nonaktif, dengan total
 * rujukan & komisi di halaman detail penuh. Tanpa hapus — nonaktif
 * saja (aturan rumah tanpa-hapus). Izin: doctors.manage.
 */
class DoctorController extends Controller
{
    public function index(): View
    {
        $doctors = Doctor::orderBy('name')
            ->withCount(['sales as referrals_count' => fn ($q) => $q->where('status', '!=', 'void')])
            ->withSum('commissions as commission_total', 'amount')
            ->paginate(20);

        return view('master.doctors.index', compact('doctors'));
    }

    public function create(): View
    {
        return view('master.doctors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'clinic' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $doctor = Doctor::create($data + ['is_active' => true]);
        app(AuditLogger::class)->log(
            action: 'doctor.created',
            subjectType: 'doctor',
            subjectId: $doctor->id,
            newValues: ['name' => $doctor->name, 'commission_rate' => (string) $doctor->commission_rate],
            actor: $request->user(),
        );

        return redirect()->route('doctors.index')->with('status', __('ui.doctors.created'));
    }

    public function show(Doctor $doctor): View
    {
        $referrals = $doctor->sales()->where('status', '!=', 'void');
        $stats = [
            'referrals_count' => (clone $referrals)->count(),
            'referrals_total' => (clone $referrals)->sum('total_amount'),
            'commission_total' => $doctor->commissions()->sum('amount'),
        ];

        return view('master.doctors.show', [
            'doctor' => $doctor,
            'stats' => $stats,
            'recentSales' => $referrals->with('customer')->orderByDesc('id')->limit(10)->get(),
            'recentCommissions' => $doctor->commissions()->with('sale')
                ->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    public function edit(Doctor $doctor): View
    {
        return view('master.doctors.edit', compact('doctor'));
    }

    public function update(Request $request, Doctor $doctor): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'clinic' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $doctor->update($data);
        app(AuditLogger::class)->log(
            action: 'doctor.updated',
            subjectType: 'doctor',
            subjectId: $doctor->id,
            newValues: ['name' => $doctor->name, 'commission_rate' => (string) $doctor->commission_rate],
            actor: $request->user(),
        );

        return redirect()->route('doctors.index')->with('status', __('ui.doctors.updated'));
    }

    public function toggleActive(Request $request, Doctor $doctor): RedirectResponse
    {
        $doctor->update(['is_active' => ! $doctor->is_active]);
        app(AuditLogger::class)->log(
            action: 'doctor.toggled',
            subjectType: 'doctor',
            subjectId: $doctor->id,
            newValues: ['is_active' => $doctor->is_active],
            actor: $request->user(),
        );

        return back()->with('status', $doctor->is_active ? __('ui.doctors.activated') : __('ui.doctors.deactivated'));
    }
}
