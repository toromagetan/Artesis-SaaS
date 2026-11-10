<?php

namespace App\Modules\Sales\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Services\OfflineDeviceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan perangkat kasir offline (F5.3) — sepagar
 * api_tokens.manage (keluarga kredensial integrasi yang sama).
 * Token mentah hanya tampil SEKALI lewat flash sesudah daftar.
 */
class OfflineDeviceController extends Controller
{
    public function index(): View
    {
        // Daftar perangkat, cabang, dan pengguna terpagari wilayah
        // perusahaan aktor — perangkat tenant lain tidak ditampilkan
        // dan tidak dapat dipilih untuk pendaftaran baru.
        $context = BranchContext::current();
        $branchIds = $context->companyBranchIds();
        $tenant = TenantContext::for(auth()->user());
        // Pagar lima mode TenantContext (putaran koreksi temuan 3):
        // mode unassigned kini menghasilkan daftar KOSONG, bukan
        // seluruh pengguna non-platform se-instalasi.
        $users = User::where('is_active', true)->orderBy('name');
        $tenant->constrainByTenant($users);
        if ($tenant->isStandalone()) {
            $users->where('is_platform', false);
        }

        return view('settings.offline-devices.index', [
            'devices' => OfflineDevice::with(['branch', 'user'])->whereIn('branch_id', $branchIds)->orderBy('name')->get(),
            'branches' => Branch::whereIn('id', $branchIds)->orderBy('name')->get(),
            'users' => $users->get(),
        ]);
    }

    public function store(Request $request, OfflineDeviceService $devices): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'branch_id' => ['required', 'exists:branches,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);
        $issued = $devices->register($request->user(), $validated['name'], (int) $validated['branch_id'], (int) $validated['user_id']);

        return redirect()->route('offline-devices.index')
            ->with('status', __('ui.offline.device_created'))
            ->with('plain_token', $issued['plain_token'])
            ->with('device_name', $issued['device']->name);
    }

    public function revoke(Request $request, OfflineDevice $device, OfflineDeviceService $devices): RedirectResponse
    {
        $devices->revoke($request->user(), $device);

        return redirect()->route('offline-devices.index')->with('status', __('ui.offline.device_revoked'));
    }
}
