<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Purchase Request (PRD Detail 6.1). */
class PurchaseRequestController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $requests = PurchaseRequest::with(['requester', 'branch'])
            ->withCount('items')
            ->when(in_array($status, PurchaseRequest::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where('number', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.requests.index', compact('requests', 'status', 'q'));
    }

    public function create(): View
    {
        $context = BranchContext::current();
        $branches = $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchasing.requests.create', compact('branches', 'products', 'suppliers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']) && ! empty($row['qty']))
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'cabang_tujuan' => ['required', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.estimated_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.supplier_id' => ['nullable', 'integer'],
        ]);

        $pr = $this->service->create(
            $request->user(),
            (int) $validated['cabang_tujuan'],
            collect($validated['items'])->map(fn ($row) => [
                'product_id' => (int) $row['product_id'],
                'qty' => (int) $row['qty'],
                'estimated_price' => $row['estimated_price'] ?? null,
                'supplier_id' => $row['supplier_id'] ?? null,
            ])->all(),
            $validated['notes'] ?? null,
        );

        return redirect()->route('pr.show', $pr)->with('status', __('ui.saved'));
    }

    public function show(PurchaseRequest $pr): View
    {
        $pr->load(['requester', 'branch', 'items.product', 'items.supplier', 'items.purchaseOrder']);
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        return view('purchasing.requests.show', compact('pr', 'suppliers'));
    }

    public function submit(Request $request, PurchaseRequest $pr): RedirectResponse
    {
        $this->service->submit($request->user(), $pr);

        return back()->with('status', __('ui.saved'));
    }

    public function approve(Request $request, PurchaseRequest $pr): RedirectResponse
    {
        $this->service->approve($request->user(), $pr);

        return back()->with('status', __('ui.saved'));
    }

    /** Dokumen cetak PR (audit 23 Okt 2026 B4). */
    public function print(PurchaseRequest $pr): View
    {
        $pr->load(['branch', 'requester', 'items.product', 'items.supplier', 'sale']);

        return view('purchasing.requests.print', [
            'pr' => $pr,
            'storeName' => Setting::value('store.name', 'Artesis'),
            'printedBy' => auth()->user(),
        ]);
    }

    public function cancel(Request $request, PurchaseRequest $pr): RedirectResponse
    {
        $this->service->cancel($request->user(), $pr);

        return back()->with('status', __('ui.saved'));
    }

    public function convert(Request $request, PurchaseRequest $pr): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'order_date' => ['nullable', 'date'],
        ]);

        $po = $this->service->convertToPo(
            $request->user(), [$pr], (int) $validated['supplier_id'], $validated['order_date'] ?? null,
        );

        return redirect()->route('po.show', $po)->with('status', __('ui.saved'));
    }

    /** Gabung beberapa PR approved (dari daftar, checkbox) menjadi satu PO. */
    public function convertMultiple(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pr_ids' => ['required', 'array', 'min:1'],
            'pr_ids.*' => ['integer'],
            'supplier_id' => ['required', 'integer'],
            'order_date' => ['nullable', 'date'],
        ]);

        $prs = PurchaseRequest::whereIn('id', $validated['pr_ids'])->get()->all();
        $po = $this->service->convertToPo(
            $request->user(), $prs, (int) $validated['supplier_id'], $validated['order_date'] ?? null,
        );

        return redirect()->route('po.show', $po)->with('status', __('ui.saved'));
    }
}
