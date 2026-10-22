<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Supplier;
use App\Modules\Sales\Models\BrokenStock;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\WarrantyCase;
use App\Modules\Sales\Services\WarrantyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Menu Garansi (paritas after-sales Artesis) — daftar, buat dari
 * item penjualan, dan detail satu halaman penuh dengan seluruh
 * tahap alur sebagai aksi. Logika tahap di WarrantyService.
 */
class WarrantyController extends Controller
{
    public function __construct(private readonly WarrantyService $warranty) {}

    public function index(Request $request): View
    {
        $actor = $request->user();
        // Terlihat oleh penjualan (sales.view) ATAU gudang (stock.view):
        // antrean inspeksi stok rusak adalah pekerjaan gudang.
        if (! $actor->hasPermission('sales.view') && ! $actor->hasPermission('stock.view')) {
            abort(403);
        }
        $context = BranchContext::for($actor);
        $query = WarrantyCase::with(['sale.customer', 'saleItem', 'branch'])
            ->orderByDesc('id');
        if (! $context->hasGlobalAccess()) {
            $warehouseId = $this->warranty->warehouseBranch()->id;
            $query->whereIn('branch_id', [...$context->managedBranchIds(), $warehouseId]);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Antrean stok rusak mengikuti pagar keterlihatan yang sama
        // dengan daftar kasus (audit 23 Okt 2026 A2): aktor non-global
        // hanya melihat baris cabangnya + gudang utama.
        $brokenQuery = BrokenStock::with(['product', 'warrantyCase'])
            ->whereIn('status', ['pending_inspection', 'at_warehouse']);
        if (! $context->hasGlobalAccess()) {
            $brokenQuery->whereIn('branch_id', [
                ...$context->managedBranchIds(),
                $this->warranty->warehouseBranch()->id,
            ]);
        }

        return view('sales.warranty.index', [
            'cases' => $query->paginate(20)->withQueryString(),
            'brokenPending' => $brokenQuery->orderBy('id')->limit(20)->get(),
            'warehouse' => $this->warranty->warehouseBranch(),
        ]);
    }

    public function create(Request $request): View
    {
        $sale = null;
        if ($request->filled('invoice')) {
            $context = BranchContext::for($request->user());
            $sale = Sale::with(['items.product', 'customer'])
                ->where('number', $request->string('invoice'))
                ->first();
            if ($sale !== null) {
                $context->assertCanAccess($sale->branch_id);
            }
        }

        return view('sales.warranty.create', ['sale' => $sale, 'invoice' => $request->string('invoice')]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'sale_item_id' => ['required', 'integer', 'exists:sale_items,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $sale = Sale::findOrFail($validated['sale_id']);
        $item = $sale->items()->findOrFail($validated['sale_item_id']);
        $case = $this->warranty->createCase($request->user(), $sale, $item,
            $validated['quantity'], $validated['reason'], $validated['description'] ?? null);

        return redirect()->route('warranties.show', $case)->with('status', __('ui.warranty.created'));
    }

    public function show(Request $request, WarrantyCase $warranty): View
    {
        $actor = $request->user();
        if (! $actor->hasPermission('sales.view') && ! $actor->hasPermission('stock.view')) {
            abort(403);
        }
        // Pagar cabang untuk BACA (audit 23 Okt 2026 A2): kasus hanya
        // terlihat oleh aktor global, pengelola cabang kasus, atau
        // pengelola gudang utama (pemroses sentral). Di luar itu 404 —
        // sama seperti daftar; aksi-aksi tahap tetap ditegakkan ulang
        // di WarrantyService.
        $context = BranchContext::for($actor);
        if (! $context->hasGlobalAccess()
            && ! in_array($warranty->branch_id, [
                ...$context->managedBranchIds(),
                $this->warranty->warehouseBranch()->id,
            ], true)) {
            abort(404);
        }
        $warranty->load(['sale.customer', 'saleItem.product', 'branch', 'creator',
            'replacement.product', 'replacement.purchaseOrder', 'brokenStocks', 'payments']);

        return view('sales.warranty.show', [
            'case' => $warranty,
            'ready' => $this->warranty->replacementReady($warranty),
            'products' => Product::where('is_active', true)->orderBy('name')->limit(300)->get(),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'warehouse' => $this->warranty->warehouseBranch(),
        ]);
    }

    public function sendOld(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $this->warranty->sendOldItem($request->user(), $warranty);

        return back()->with('status', __('ui.warranty.sent_old'));
    }

    public function receiveOld(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $this->warranty->receiveOldItem($request->user(), $warranty);

        return back()->with('status', __('ui.warranty.received_old'));
    }

    public function inspect(Request $request, BrokenStock $brokenStock): RedirectResponse
    {
        $validated = $request->validate([
            'condition' => ['required', 'in:good,broken'],
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->warranty->inspectBroken($request->user(), $brokenStock, $validated['condition'],
            $validated['reason'], $validated['note'] ?? null);

        return back()->with('status', __('ui.warranty.inspected'));
    }

    public function adjust(Request $request, BrokenStock $brokenStock): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
            'note' => ['required', 'string', 'max:1000'],
        ]);
        $this->warranty->adjustBroken($request->user(), $brokenStock, $validated['reason'], $validated['note']);

        return back()->with('status', __('ui.warranty.adjusted'));
    }

    public function prepare(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $validated = $request->validate([
            'source' => ['required', 'in:stock,purchase_order'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'customer_charge' => ['required', 'numeric', 'min:0'],
        ]);
        $this->warranty->prepareReplacement($request->user(), $warranty, $validated['source'],
            Product::findOrFail($validated['product_id']), (string) $validated['customer_charge']);

        return back()->with('status', __('ui.warranty.prepared'));
    }

    public function replacementPo(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $validated = $request->validate(['supplier_id' => ['required', 'integer', 'exists:suppliers,id']]);
        $this->warranty->createReplacementPo($request->user(), $warranty, Supplier::findOrFail($validated['supplier_id']));

        return back()->with('status', __('ui.warranty.po_created'));
    }

    public function ship(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $this->warranty->shipReplacement($request->user(), $warranty);

        return back()->with('status', __('ui.warranty.shipped'));
    }

    public function receiveReplacement(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $this->warranty->receiveReplacement($request->user(), $warranty);

        return back()->with('status', __('ui.warranty.received_replacement'));
    }

    public function pay(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);
        $this->warranty->pay($request->user(), $warranty, $validated['method'], (string) $validated['amount']);

        return back()->with('status', __('ui.warranty.paid'));
    }

    public function complete(Request $request, WarrantyCase $warranty): RedirectResponse
    {
        $this->warranty->complete($request->user(), $warranty);

        return back()->with('status', __('ui.warranty.completed'));
    }
}
