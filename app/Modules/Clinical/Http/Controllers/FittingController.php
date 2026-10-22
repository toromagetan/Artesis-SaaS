<?php

namespace App\Modules\Clinical\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinical\Models\FittingMeasurement;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Platform\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fitting AI (F5.2) — halaman ukur kamera di perangkat toko +
 * penyimpanan angka fitting ke pelanggan. Angka kamera adalah
 * pendamping; staf dapat mencatat nilai pupilometer pembanding
 * (pd_reference) dan menimpa angka kamera sebelum menyimpan.
 */
class FittingController extends Controller
{
    public function measure(Customer $customer): View
    {
        return view('clinical.fitting.measure', [
            'customer' => $customer,
            'history' => $customer->fittingMeasurements()->with('measurer')->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(Request $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'in:camera,manual'],
            'pd_binocular' => ['nullable', 'numeric', 'min:40', 'max:90'],
            'pd_mono_od' => ['nullable', 'numeric', 'min:15', 'max:50'],
            'pd_mono_os' => ['nullable', 'numeric', 'min:15', 'max:50'],
            'pupil_height_od' => ['nullable', 'numeric', 'min:5', 'max:45'],
            'pupil_height_os' => ['nullable', 'numeric', 'min:5', 'max:45'],
            'face_width' => ['nullable', 'numeric', 'min:90', 'max:180'],
            'pd_reference' => ['nullable', 'numeric', 'min:40', 'max:90'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $pdBinocular = $validated['pd_binocular'] ?? null;
        $pdReference = $validated['pd_reference'] ?? null;
        if ($pdBinocular === null && $pdReference === null) {
            return back()->withErrors(['pd_binocular' => __('ui.fitting.pd_required')])->withInput();
        }

        $actor = $request->user();
        $branchId = BranchContext::for($actor)->currentBranchId() ?? $customer->branch_id;
        $faceWidth = isset($validated['face_width']) ? $validated['face_width'] + 0 : null;

        $measurement = FittingMeasurement::create([
            'customer_id' => $customer->id,
            'branch_id' => $branchId,
            'measured_by' => $actor->id,
            'method' => $validated['method'],
            'pd_binocular' => $pdBinocular,
            'pd_mono_od' => $validated['pd_mono_od'] ?? null,
            'pd_mono_os' => $validated['pd_mono_os'] ?? null,
            'pupil_height_od' => $validated['pupil_height_od'] ?? null,
            'pupil_height_os' => $validated['pupil_height_os'] ?? null,
            'face_width' => $validated['face_width'] ?? null,
            'pd_reference' => $pdReference,
            'frame_size_recommendation' => $faceWidth !== null ? FittingMeasurement::sizeForFaceWidth($faceWidth) : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Rekam klinis dibuat — berjejak audit seperti resep
        // (audit 23 Okt 2026 A4).
        $audit->log(
            action: 'clinical.fitting.record',
            subjectType: 'fitting_measurement',
            subjectId: $measurement->id,
            newValues: ['method' => $measurement->method, 'customer_id' => $customer->id],
            branchId: $branchId,
            actor: $actor,
        );

        return redirect()->route('fittings.measure', $customer)->with('status', __('ui.fitting.saved'));
    }
}
