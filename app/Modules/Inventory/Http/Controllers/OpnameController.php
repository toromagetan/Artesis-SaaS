<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\OpnameService;
use App\Modules\Master\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opname terjadwal (F4.1). Semua aksi sepagar stock.opname di rute;
 * pagar cabang ditegakkan BranchContext di OpnameService.
 */
class OpnameController extends Controller
{
    public function __construct(private readonly OpnameService $opnames) {}

    public function index(Request $request): View
    {
        $context = BranchContext::current();
        $branchIds = $context->hasGlobalAccess()
            ? Branch::query()->pluck('id')->all()
            : $context->managedBranchIds();
        $opnames = StockOpname::withoutGlobalScopes()
            ->whereIn('branch_id', $branchIds)
            ->with(['branch', 'creator', 'items'])
            ->withCount('items')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('inventory.opname.index', ['opnames' => $opnames]);
    }

    public function create(Request $request): View
    {
        $context = BranchContext::current();
        $branches = Branch::query()
            ->when(! $context->hasGlobalAccess(), fn ($q) => $q->whereIn('id', $context->managedBranchIds()))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('inventory.opname.create', ['branches' => $branches]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'scheduled_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $actor = $request->user();
        $opname = $this->opnames->schedule(
            $actor, (int) $data['branch_id'], $data['scheduled_date'], $data['notes'] ?? null,
        );

        return redirect()->route('opnames.show', $opname)->with('status', __('ui.opname.scheduled'));
    }

    public function show(Request $request, StockOpname $opname): View
    {
        $actor = $request->user();
        BranchContext::for($actor)->assertCanAccess($opname->branch_id);
        $opname->load(['branch', 'creator', 'approver', 'items.product']);

        return view('inventory.opname.show', ['opname' => $opname]);
    }

    public function start(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->opnames->startCounting($request->user(), $opname);

        return back()->with('status', __('ui.opname.started'));
    }

    public function count(Request $request, StockOpname $opname): RedirectResponse
    {
        $data = $request->validate([
            'counts' => ['present', 'array'],
            'counts.*' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->opnames->saveCounts($request->user(), $opname, $data['counts']);

        return back()->with('status', __('ui.opname.counted'));
    }

    public function review(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->opnames->submitReview($request->user(), $opname);

        return back()->with('status', __('ui.opname.in_review'));
    }

    public function approve(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->opnames->approve($request->user(), $opname);

        return back()->with('status', __('ui.opname.approved'));
    }

    public function cancel(Request $request, StockOpname $opname): RedirectResponse
    {
        $this->opnames->cancel($request->user(), $opname);

        return redirect()->route('opnames.index')->with('status', __('ui.opname.cancelled'));
    }
}
