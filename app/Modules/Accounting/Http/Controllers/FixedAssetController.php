<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\FixedAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\View\View;

/** Aset tetap (F1.4): master + pelepasan + picu penyusutan. */
#[Middleware('plan.feature:accounting_full')]
class FixedAssetController extends Controller
{
    public function __construct(private readonly FixedAssetService $assets) {}

    public function index(): View
    {
        $assets = FixedAsset::orderBy('name')->get();

        return view('accounting.assets.index', compact('assets'));
    }

    public function create(): View
    {
        return view('accounting.assets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'acquired_at' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'gt:0'],
            'useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
        ]);

        $asset = $this->assets->create($request->user(), [
            'name' => $validated['name'],
            'acquired_at' => $validated['acquired_at'],
            'acquisition_cost' => (string) $validated['acquisition_cost'],
            'useful_life_months' => (int) $validated['useful_life_months'],
        ]);

        return redirect()->route('assets.show', $asset)->with('status', __('ui.assets.created'));
    }

    public function show(FixedAsset $asset): View
    {
        $journals = Journal::where('source_type', 'depreciation')
            ->where('source_id', $asset->id)
            ->orderByDesc('journal_date')
            ->get();
        $monthly = $this->assets->monthlyAmount($asset);

        return view('accounting.assets.show', compact('asset', 'journals', 'monthly'));
    }

    public function dispose(Request $request, FixedAsset $asset): RedirectResponse
    {
        $this->assets->dispose($request->user(), $asset);

        return redirect()->route('assets.show', $asset)->with('status', __('ui.assets.disposed_msg'));
    }

    public function depreciate(Request $request): RedirectResponse
    {
        $period = now()->format('Y-m');
        $result = $this->assets->depreciatePeriod($request->user(), $period);

        return redirect()->route('assets.index')->with('status', __('ui.assets.depreciation_done', [
            'period' => $period, 'count' => $result['posted'], 'total' => $result['total']->format(),
        ]));
    }
}
