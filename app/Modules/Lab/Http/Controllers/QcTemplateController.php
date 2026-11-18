<?php

namespace App\Modules\Lab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\QcTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * UI kelola template titik QC (F2.1). Melihat: izin lab.view.
 * Mengelola: hanya aktor platform/instalasi mandiri (gerbang rute
 * qc.template-admin + QcTemplateService) — template bersama
 * se-instalasi tidak pernah dikelola aktor tenant (ISO-3).
 */
class QcTemplateController extends Controller
{
    public function __construct(private readonly QcTemplateService $service) {}

    public function index(): View
    {
        $templates = QcPointTemplate::orderBy('sort_order')->orderBy('id')->get();
        $canManage = $this->canManage();

        return view('lab.qc-templates.index', compact('templates', 'canManage'));
    }

    public function create(): View
    {
        $this->guardManagePage();

        return view('lab.qc-templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $this->service->create(
            $request->user(),
            $validated['name'],
            (bool) ($validated['is_required'] ?? false),
            $validated['sort_order'] ?? null,
        );

        return redirect()->route('qc-templates.index')->with('status', __('ui.qc.created'));
    }

    public function edit(QcPointTemplate $template): View
    {
        $this->guardManagePage();

        return view('lab.qc-templates.edit', compact('template'));
    }

    public function update(Request $request, QcPointTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_required' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        $this->service->update(
            $request->user(),
            $template,
            $validated['name'],
            (bool) ($validated['is_required'] ?? false),
            (int) $validated['sort_order'],
        );

        return redirect()->route('qc-templates.index')->with('status', __('ui.qc.updated'));
    }

    public function toggleActive(Request $request, QcPointTemplate $template): RedirectResponse
    {
        $this->service->setActive($request->user(), $template, ! $template->is_active);

        return redirect()->route('qc-templates.index')->with('status', __('ui.qc.updated'));
    }

    private function canManage(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        // Konteks dulu (ISO-3): aktor tenant tidak pernah mengelola
        // template bersama, sekalipun memegang izinnya — tombol
        // kelola tidak ditawarkan kepada mereka.
        $context = TenantContext::for($user);

        if (! $context->isPlatform() && ! $context->isStandalone()) {
            return false;
        }

        // Izin khusus (audit 23 Okt 2026 A3): sumber kebenaran izin
        // adalah lab.templates.manage (Owner '*' + Admin).
        return $user->hasPermission('lab.templates.manage');
    }

    private function guardManagePage(): void
    {
        if (! $this->canManage()) {
            abort(403, __('ui.qc.manager_only'));
        }
    }
}
