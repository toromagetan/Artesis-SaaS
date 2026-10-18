<?php

namespace App\Modules\Lab\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Lab\Models\LabOrder;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Models\QcCheck;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\QcCheckService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Antrean & detail proses lensa (PRD Detail 3.1/3.2).
 * Pagar cabang lewat relasi sale (dokumen induk pembawa branch_id).
 */
class LabController extends Controller
{
    public function __construct(
        private readonly LensProcessService $lab,
        private readonly QcCheckService $qc,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $processes = $this->scopedQuery()
            ->with(['sale.customer', 'sale.branch', 'technician'])
            ->when(in_array($status, LensProcess::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('sale', function ($sub) use ($q) {
                    $sub->where('number', 'like', "%{$q}%")
                        ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderByRaw("FIELD(status, 'waiting', 'edging', 'qc', 'ready', 'done')")
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return view('lab.index', compact('processes', 'status', 'q'));
    }

    public function show(LensProcess $process): View
    {
        $this->assertVisible($process);
        $process->load(['sale.customer', 'sale.branch', 'sale.items', 'technician']);
        $technicians = User::where('is_active', true)
            ->where('is_platform', false)
            ->orderBy('name')
            ->get();

        // Daftar periksa QC (F2.2): titik aktif terurut + hasil terakhir
        // per titik + seluruh riwayat percobaan (tidak ditimpa).
        $qcPoints = QcPointTemplate::activeOrdered()->get();
        $qcLatest = $this->qc->latestByPoint($process);
        $qcHistory = QcCheck::where('lens_process_id', $process->id)
            ->with(['point', 'checker'])
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get();

        // Order lab milik penjualan ini (F2.3) — status terlihat pemegang lab.view.
        $labOrders = LabOrder::where('sale_id', $process->sale_id)->orderByDesc('id')->get();

        return view('lab.show', compact('process', 'technicians', 'qcPoints', 'qcLatest', 'qcHistory', 'labOrders'));
    }

    /** Catat satu percobaan QC (F2.2) — pagar izin lab.qc di rute. */
    public function recordQc(Request $request, LensProcess $process): RedirectResponse
    {
        $this->assertVisible($process);
        $validated = $request->validate([
            'point_id' => ['required', 'integer'],
            'result' => ['required', 'string', 'in:pass,fail'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $this->qc->record(
            $request->user(),
            $process,
            (int) $validated['point_id'],
            $validated['result'],
            $validated['notes'] ?? null,
        );

        return back()->with('status', __('ui.qc.recorded'));
    }

    public function assign(Request $request, LensProcess $process): RedirectResponse
    {
        $this->assertVisible($process);
        $validated = $request->validate([
            'technician_id' => ['required', 'integer'],
            'estimated_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);

        $this->lab->assignTechnician(
            $request->user(), $process,
            (int) $validated['technician_id'],
            isset($validated['estimated_days']) ? (int) $validated['estimated_days'] : null,
        );

        return back()->with('status', __('ui.saved'));
    }

    public function transition(Request $request, LensProcess $process): RedirectResponse
    {
        $this->assertVisible($process);
        $validated = $request->validate([
            'to' => ['required', 'string', 'in:edging,qc,ready'],
        ]);

        $this->lab->transition($request->user(), $process, $validated['to']);

        return back()->with('status', __('ui.saved'));
    }

    public function complete(Request $request, LensProcess $process): RedirectResponse
    {
        $this->assertVisible($process);
        $this->lab->complete($request->user(), $process);

        return back()->with('status', __('ui.lab.completed_ok'));
    }

    /** Query dasar terpagar cabang aktor lewat cabang penjualan induk. */
    private function scopedQuery()
    {
        $context = BranchContext::current();

        return LensProcess::query()->whereHas('sale', function ($query) use ($context) {
            if (! $context->hasGlobalAccess()) {
                $query->whereIn('sales.branch_id', $context->managedBranchIds());
            }
        });
    }

    private function assertVisible(LensProcess $process): void
    {
        $context = BranchContext::current();
        $branchId = $process->sale()->withoutGlobalScopes()->value('branch_id');
        abort_unless($branchId !== null && $context->allowsBranch((int) $branchId), 404);
    }
}
