<?php

namespace App\Modules\Master\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\PlanGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    /**
     * Query cabang dalam wilayah aktor (pagar tenant di server):
     * - tenant sah  : hanya cabang tenantnya sendiri;
     * - standalone  : hanya cabang tanpa tenant (tenant_id NULL);
     * - mode lain (unassigned, platform, tenant_id menggantung):
     *   himpunan kosong — tidak ada cabang bisnis yang terjangkau.
     *
     * Identitas tenant selalu dari klasifikasi TenantContext yang
     * tervalidasi, tidak pernah dari tenant_id mentah.
     */
    private function scopedQuery(User $user): Builder
    {
        $context = TenantContext::for($user);

        if ($context->isValid()) {
            return Branch::query()->where('tenant_id', $context->tenantId());
        }

        if ($context->isStandalone()) {
            return Branch::query()->whereNull('tenant_id');
        }

        return Branch::query()->whereRaw('1 = 0');
    }

    /**
     * Cabang sasaran wajib berada dalam wilayah aktor SEBELUM data
     * dibaca atau diubah — di luar wilayah penolakannya 404, sama
     * seperti cabang yang tidak ada.
     */
    private function assertInScope(Request $request, Branch $branch): void
    {
        $inScope = $this->scopedQuery($request->user())
            ->whereKey($branch->id)
            ->exists();

        abort_unless($inScope, 404);
    }

    /** Membuat cabang hanya untuk aktor bertenant sah atau standalone. */
    private function assertCanCreate(User $user): void
    {
        $context = TenantContext::for($user);

        if (! $context->isValid() && ! $context->isStandalone()) {
            throw new AuthorizationException('Aktor tanpa konteks tenant tidak dapat mengelola cabang bisnis.');
        }
    }

    public function index(Request $request): View
    {
        return view('master.branches.index', [
            'branches' => $this->scopedQuery($request->user())->orderBy('name')->paginate(20),
        ]);
    }

    /** Detail cabang — HALAMAN PENUH dengan rute sendiri (F.2 butir 7). */
    public function show(Request $request, Branch $branch): View
    {
        $this->assertInScope($request, $branch);
        $branch->loadCount('users');

        return view('master.branches.show', compact('branch'));
    }

    public function create(Request $request): View
    {
        $this->assertCanCreate($request->user());

        return view('master.branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->assertCanCreate($request->user());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // M4: batas cabang paket SaaS + cap tenant pemilik.
        app(PlanGate::class)->assertWithinLimit($request->user(), 'branches_max');
        // Cap tenant dari konteks yang tervalidasi (standalone: null).
        $data['tenant_id'] = TenantContext::for($request->user())->tenantId();

        Branch::create($data);

        return redirect()->route('branches.index')->with('status', __('ui.branches.created'));
    }

    public function edit(Request $request, Branch $branch): View
    {
        $this->assertInScope($request, $branch);

        return view('master.branches.edit', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->assertInScope($request, $branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_main_warehouse' => ['nullable', 'boolean'],
        ]);
        $data['is_main_warehouse'] = (bool) ($data['is_main_warehouse'] ?? false);
        if ($data['is_main_warehouse']) {
            // Tepat satu gudang utama PER PARTISI KEPEMILIKAN: hanya
            // cabang lain dengan tenant_id yang sama (atau sama-sama
            // standalone/NULL) yang kehilangan tanda — cabang tenant
            // lain tidak pernah tersentuh query ini.
            Branch::where('id', '!=', $branch->id)
                ->where('tenant_id', $branch->tenant_id)
                ->update(['is_main_warehouse' => false]);
        }

        $branch->update($data);

        return redirect()->route('branches.index')->with('status', __('ui.branches.updated'));
    }

    public function toggleActive(Request $request, Branch $branch): RedirectResponse
    {
        $this->assertInScope($request, $branch);

        if ($branch->is_active) {
            $blockers = $this->deactivationBlockers($branch);
            if ($blockers !== []) {
                throw ValidationException::withMessages([
                    'branch' => __('ui.branches.deactivation_blocked', ['blockers' => implode('; ', $blockers)]),
                ]);
            }
        }

        $branch->update(['is_active' => ! $branch->is_active]);

        return back()->with('status', $branch->is_active ? __('ui.branches.activated') : __('ui.branches.deactivated'));
    }

    /**
     * Penghalang penonaktifan cabang (Blueprint D.2): proses menggantung
     * (orderan belum selesai, transfer belum diterima). Modul-modul sumber
     * proses itu tiba di MVP — daftar ini menjadi hidup saat mereka ada.
     *
     * @return string[]
     */
    private function deactivationBlockers(Branch $branch): array
    {
        return [];
    }
}
