<?php

namespace App\Modules\Clinical\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\Clinical\Services\PrescriptionService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Resep (Blueprint D.5 + PRD Detail 3.3).
 * Formulir resep selalu lahir dari konteks pelanggan (rute bersarang) —
 * kaitan pelanggan tidak pernah diambil dari input bebas.
 * Precal BUKAN turunan resep — precal manual hidup di item penjualan
 * (ADR-019); halaman/cetakan precal resep telah dicabut.
 */
class PrescriptionController extends Controller
{
    public function __construct(
        private readonly PrescriptionService $service,
    ) {}

    public function create(Customer $customer): View
    {
        return view('clinical.prescriptions.create', [
            'customer' => $customer,
            'examiners' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        $validated = $this->validateGrid($request);

        $prescription = $this->service->create($request->user(), $customer, $validated);

        return redirect()->route('prescriptions.show', $prescription)
            ->with('status', __('ui.prescriptions.created'));
    }

    public function show(Prescription $prescription): View
    {
        $prescription->load(['customer', 'branch', 'examiner']);

        return view('clinical.prescriptions.show', compact('prescription'));
    }

    public function edit(Prescription $prescription): View
    {
        $prescription->load('customer');

        return view('clinical.prescriptions.edit', [
            'prescription' => $prescription,
            'examiners' => User::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Prescription $prescription): RedirectResponse
    {
        $validated = $this->validateGrid($request);

        $this->service->update($request->user(), $prescription, $validated);

        return redirect()->route('prescriptions.show', $prescription)
            ->with('status', __('ui.prescriptions.updated'));
    }

    public function duplicate(Request $request, Prescription $prescription): RedirectResponse
    {
        $copy = $this->service->duplicate($request->user(), $prescription);

        return redirect()->route('prescriptions.edit', $copy)
            ->with('status', __('ui.prescriptions.duplicated'));
    }

    public function print(Prescription $prescription): View
    {
        $prescription->load(['customer', 'branch', 'examiner']);

        return view('clinical.prescriptions.print', [
            'prescription' => $prescription,
            'storeName' => Setting::value('store.name', 'Lentera'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateGrid(Request $request): array
    {
        return $request->validate([
            'examined_at' => ['required', 'date'],
            'examiner_id' => ['nullable', Rule::exists('users', 'id')],
            'od_sph' => ['required', 'numeric', 'between:-60,60'],
            'od_cyl' => ['required', 'numeric', 'between:-20,20'],
            'od_axis' => ['nullable', 'integer', 'between:0,180'],
            'od_add' => ['nullable', 'numeric', 'between:0,5'],
            'od_pdr' => ['nullable', 'numeric', 'between:20,45'],
            'os_sph' => ['required', 'numeric', 'between:-60,60'],
            'os_cyl' => ['required', 'numeric', 'between:-20,20'],
            'os_axis' => ['nullable', 'integer', 'between:0,180'],
            'os_add' => ['nullable', 'numeric', 'between:0,5'],
            'os_pdl' => ['nullable', 'numeric', 'between:20,45'],
            'pd_total' => ['nullable', 'numeric', 'between:40,90'],
            'prism' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
