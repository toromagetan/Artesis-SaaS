<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Transfer distribusi (PRD Detail 7.3): dari stok cabang bebas atau
 * dari detail PO. Pagar: aktor wajib mengelola cabang asal (kirim/
 * batal) atau cabang tujuan (terima); daftar hanya menampilkan
 * transfer yang menyentuh cabang kelolaannya.
 */
class TransferController extends Controller
{
    public function __construct(private readonly TransferService $transfers) {}

    public function index(Request $request): View
    {
        $context = BranchContext::current();
        $status = (string) $request->query('status', '');

        $transfers = StockTransfer::with(['fromBranch', 'toBranch', 'sender'])
            ->withCount('items')
            ->when(! $context->hasGlobalAccess(), function ($query) use ($context) {
                $ids = $context->managedBranchIds();
                $query->where(fn ($q) => $q->whereIn('from_branch_id', $ids)->orWhereIn('to_branch_id', $ids));
            })
            ->when(in_array($status, StockTransfer::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.transfers.index', compact('transfers', 'status'));
    }

    public function create(): View
    {
        $context = BranchContext::current();
        $branches = Branch::orderBy('name')->get();
        $managed = $context->hasGlobalAccess()
            ? $branches
            : $branches->whereIn('id', $context->managedBranchIds())->values();
        $products = Product::where('is_active', true)->orderBy('type')->orderBy('name')->get();

        return view('inventory.transfers.create', compact('branches', 'managed', 'products'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSend($request);
        $transfer = $this->transfers->sendFromBranchStock(
            $request->user(),
            (int) $validated['dari'],
            (int) $validated['tujuan'],
            $validated['items'],
            $validated['notes'] ?? null,
        );

        return redirect()->route('transfers.show', $transfer)->with('status', __('ui.saved'));
    }

    /** Kirim distribusi dari detail PO (sumber purchase_order). */
    public function storeFromPo(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $validated = $this->validateSend($request, fromPo: true);
        $transfer = $this->transfers->sendFromPo(
            $request->user(), $po,
            (int) $validated['tujuan'],
            $validated['items'],
            $validated['notes'] ?? null,
        );

        return redirect()->route('transfers.show', $transfer)->with('status', __('ui.saved'));
    }

    public function show(StockTransfer $transfer): View
    {
        $this->assertVisible($transfer);
        $transfer->load(['fromBranch', 'toBranch', 'sender', 'receiver', 'items.product', 'purchaseOrder']);

        return view('inventory.transfers.show', compact('transfer'));
    }

    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->transfers->receive($request->user(), $transfer);

        return back()->with('status', __('ui.saved'));
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->transfers->cancel($request->user(), $transfer);

        return back()->with('status', __('ui.saved'));
    }

    public function print(StockTransfer $transfer): View
    {
        $this->assertVisible($transfer);
        $transfer->load(['fromBranch', 'toBranch', 'sender', 'items.product']);

        return view('inventory.transfers.print', [
            'transfer' => $transfer,
            'storeName' => Setting::value('store.name', 'Artesis'),
        ]);
    }

    private function validateSend(Request $request, bool $fromPo = false): array
    {
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']) && ! empty($row['qty']))
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $rules = [
            'tujuan' => ['required', 'integer'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
        if (! $fromPo) {
            $rules['dari'] = ['required', 'integer'];
        }

        return $request->validate($rules);
    }

    private function assertVisible(StockTransfer $transfer): void
    {
        $context = BranchContext::current();
        abort_unless(
            $context->allowsBranch($transfer->from_branch_id) || $context->allowsBranch($transfer->to_branch_id),
            404,
        );
    }
}
