<?php

namespace App\Modules\Master\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Master\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        return view('master.branches.index', [
            'branches' => Branch::orderBy('name')->paginate(20),
        ]);
    }

    /** Detail cabang — HALAMAN PENUH dengan rute sendiri (F.2 butir 7). */
    public function show(Branch $branch): View
    {
        $branch->loadCount('users');

        return view('master.branches.show', compact('branch'));
    }

    public function create(): View
    {
        return view('master.branches.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        Branch::create($data);

        return redirect()->route('branches.index')->with('status', __('ui.branches.created'));
    }

    public function edit(Branch $branch): View
    {
        return view('master.branches.edit', ['branch' => $branch]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $branch->update($data);

        return redirect()->route('branches.index')->with('status', __('ui.branches.updated'));
    }

    public function toggleActive(Branch $branch): RedirectResponse
    {
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
