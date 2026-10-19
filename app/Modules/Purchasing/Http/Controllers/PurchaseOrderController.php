<?php

namespace App\Modules\Purchasing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Catalog\Services\LensMatrixService;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Purchase Order + penerimaan dari detailnya (PRD Detail 6.2/6.3). */
class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $orders,
        private readonly ReceiptService $receipts,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $orders = PurchaseOrder::with(['supplier', 'branch'])
            ->when(in_array($status, PurchaseOrder::STATUSES, true), fn ($query) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->where('number', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.orders.index', compact('orders', 'status', 'q'));
    }

    public function create(Request $request): View
    {
        $context = BranchContext::current();
        $branches = $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get();

        // Alat bantu matriks lensa (bukan menu): grid rentang lengkap
        // model Artesis untuk induk + cabang + saringan index/coating
        // yang dipilih lewat parameter GET.
        $matrixService = app(LensMatrixService::class);
        $lensParents = $matrixService->matrixParents();
        $matrix = null;
        $matrixParent = null;
        $matrixBranch = null;
        $matrixIndex = (string) $request->query('matriks_index', '');
        $matrixCoating = (string) $request->query('matriks_coating', '');
        $matrixOptions = ['indexes' => [], 'coatings' => []];
        $parentId = (int) $request->query('matriks_induk', 0);
        $branchId = (int) $request->query('matriks_cabang', 0);
        if ($parentId > 0 && $branchId > 0 && $context->allowsBranch($branchId)) {
            $matrixParent = $lensParents->firstWhere('id', $parentId);
            if ($matrixParent !== null) {
                $matrixBranch = $branches->firstWhere('id', $branchId);
                $matrixOptions = $matrixService->filterOptions($matrixParent);
                $matrix = $matrixService->gridForParent(
                    $matrixParent, $branchId,
                    $matrixIndex !== '' ? $matrixIndex : null,
                    $matrixCoating !== '' ? $matrixCoating : null,
                );
            }
        }

        return view('purchasing.orders.create', compact(
            'branches', 'products', 'suppliers', 'lensParents', 'matrix', 'matrixParent', 'matrixBranch', 'matrixService',
            'matrixIndex', 'matrixCoating', 'matrixOptions',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $items = collect((array) $request->input('items', []))
            ->filter(fn ($row) => is_array($row) && ! empty($row['product_id']) && ! empty($row['qty']))
            ->values()
            ->all();

        // Sel matriks lensa terisi menjadi item PO pada harga beli
        // master; baris yang sama digabung. Sel kosong yang diisi
        // melahirkan varian baru dari sel itu (model Artesis).
        $selection = json_decode((string) $request->input('matrix_selection', '{}'), true);
        if (is_array($selection) && $selection !== []) {
            $items = [...$items, ...$this->matrixItems($request, $selection)];
        }
        $items = collect($items)
            ->groupBy(fn ($row) => $row['product_id'].'|'.$row['unit_cost'])
            ->map(fn ($rows) => [
                'product_id' => $rows->first()['product_id'],
                'qty' => $rows->sum('qty'),
                'unit_cost' => $rows->first()['unit_cost'],
            ])
            ->values()
            ->all();
        $request->merge(['items' => $items]);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer'],
            'cabang_tujuan' => ['required', 'integer'],
            'order_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        $po = $this->orders->create(
            $request->user(),
            (int) $validated['supplier_id'],
            (int) $validated['cabang_tujuan'],
            collect($validated['items'])->map(fn ($row) => [
                'product_id' => (int) $row['product_id'],
                'qty' => (int) $row['qty'],
                'unit_cost' => (string) $row['unit_cost'],
            ])->all(),
            $validated['order_date'] ?? null,
        );

        return redirect()->route('po.show', $po)->with('status', __('ui.saved'));
    }

    /**
     * Ubah pilihan sel matriks menjadi baris item PO. Kunci "p{id}"
     * = varian yang sudah ada; kunci "n|sph|kedua|mata" = sel
     * kosong yang melahirkan varian baru pada induk matriks
     * (validasi langkah 0,25 + rentang per jenis, model Artesis).
     *
     * @param  array<string, mixed>  $selection
     * @return list<array{product_id: int, qty: int, unit_cost: string}>
     */
    private function matrixItems(Request $request, array $selection): array
    {
        $service = app(LensMatrixService::class);
        $parent = ProductParent::where('is_active', true)
            ->where('type', 'lens')
            ->find((int) $request->input('matrix_parent_id', 0));
        if ($parent === null) {
            throw ValidationException::withMessages([
                'matrix_selection' => __('ui.matrix.invalid_parent'),
            ]);
        }
        $kind = $service->kindForParent($parent);
        $sibling = $parent->variants()->where('is_active', true)->orderBy('sku')->first();
        $items = [];

        foreach ($selection as $key => $qtyRaw) {
            if (! is_numeric($qtyRaw) || (int) $qtyRaw <= 0) {
                continue;
            }
            $qty = (int) $qtyRaw;

            if (str_starts_with((string) $key, 'p')) {
                $product = Product::where('is_active', true)
                    ->where('parent_id', $parent->id)
                    ->find((int) substr((string) $key, 1));
                if ($product !== null) {
                    $items[] = ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => (string) $product->cost_price];
                }

                continue;
            }

            $parts = explode('|', (string) $key);
            if (count($parts) !== 4 || $parts[0] !== 'n' || $sibling === null) {
                continue;
            }
            [, $sphRaw, $secondRaw, $eye] = $parts;
            $sph = is_numeric($sphRaw) ? $sphRaw + 0 : null;
            $second = is_numeric($secondRaw) ? $secondRaw + 0 : null;
            if ($sph === null || $second === null || ! $this->isQuarterStep($sph) || ! $this->isQuarterStep($second)) {
                throw ValidationException::withMessages([
                    'matrix_selection' => __('ui.matrix.invalid_power'),
                ]);
            }
            $inRange = match ($kind) {
                LensMatrixService::KIND_PROGRESSIVE, LensMatrixService::KIND_BIFOCAL => $sph >= -2 && $sph <= 2 && $second >= 0.75 && $second <= 3,
                default => $sph >= -15 && $sph <= 6 && $second >= -4 && $second <= 0,
            };
            if (! $inRange || ($kind === LensMatrixService::KIND_PROGRESSIVE && ! in_array($eye, ['R', 'L'], true))) {
                throw ValidationException::withMessages([
                    'matrix_selection' => __('ui.matrix.invalid_power'),
                ]);
            }

            $isAddKind = $kind !== LensMatrixService::KIND_SV;
            $attributes = [
                'lens_kind' => $sibling->attributeValue('lens_kind'),
                'index' => ($request->input('matrix_index') ?: $sibling->attributeValue('index')),
                'coating' => ($request->input('matrix_coating') ?: $sibling->attributeValue('coating')),
                'diameter' => $sibling->attributeValue('diameter'),
            ];
            $product = app(ProductService::class)->create($request->user(), [
                'parent_id' => $parent->id,
                'sku' => $this->matrixSku($parent->id, $sph, $second, $isAddKind ? ($kind === LensMatrixService::KIND_PROGRESSIVE ? $eye : null) : null),
                'type' => 'lens',
                'name' => $parent->name,
                'brand' => $parent->brand,
                'sph' => number_format($sph, 2, '.', ''),
                'cyl' => $isAddKind ? null : number_format($second, 2, '.', ''),
                'add' => $isAddKind ? number_format($second, 2, '.', '') : null,
                'eye_side' => $kind === LensMatrixService::KIND_PROGRESSIVE ? $eye : null,
                'cost_price' => (string) $sibling->cost_price,
                'sale_price' => (string) $sibling->sale_price,
            ], array_filter($attributes, fn ($v) => $v !== null && $v !== ''));
            $items[] = ['product_id' => $product->id, 'qty' => $qty, 'unit_cost' => (string) $product->cost_price];
        }

        return $items;
    }

    /** Langkah 0,25 persis (kelipatan perempat). */
    private function isQuarterStep(float $value): bool
    {
        return abs($value * 4 - round($value * 4)) < 0.000001;
    }

    /** SKU varian lahir dari sel matriks; unik dengan sufiks bila bentrok. */
    private function matrixSku(int $parentId, float $sph, float $second, ?string $eye): string
    {
        $fmt = fn (float $v) => ($v < 0 ? 'M' : 'P').str_replace('.', '', number_format(abs($v), 2, '.', ''));
        $base = 'MX'.$parentId.'-'.$fmt($sph).'-'.$fmt($second).($eye !== null ? '-'.$eye : '');
        $sku = $base;
        $n = 2;
        while (Product::where('sku', $sku)->exists()) {
            $sku = $base.'-'.$n++;
        }

        return $sku;
    }

    public function show(PurchaseOrder $po): View
    {
        $po->load(['supplier', 'branch', 'creator', 'items.product', 'receipts.items', 'receipts.allocations']);

        // Panel distribusi (M.7): riwayat transfer dari PO ini + sisa
        // yang masih dapat dikirim per produk + pilihan cabang tujuan.
        $transfers = StockTransfer::with(['toBranch', 'fromBranch', 'items'])
            ->where('source_type', 'purchase_order')
            ->where('source_id', $po->id)
            ->orderByDesc('id')
            ->get();
        $distributable = app(TransferService::class)->distributableFromPo($po);
        $branches = Branch::orderBy('name')->get();

        return view('purchasing.orders.show', compact('po', 'transfers', 'distributable', 'branches'));
    }

    public function send(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $this->orders->markSent($request->user(), $po);

        return back()->with('status', __('ui.saved'));
    }

    public function void(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->orders->void($request->user(), $po, $validated['reason']);

        return back()->with('status', __('ui.saved'));
    }

    public function receiveForm(PurchaseOrder $po): View
    {
        $po->load(['supplier', 'branch', 'items.product']);

        return view('purchasing.orders.receive', compact('po'));
    }

    public function storeReceipt(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $validated = $request->validate([
            'received_date' => ['nullable', 'date'],
            'delivery_note_no' => ['nullable', 'string', 'max:80'],
            'over_reason' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array'],
            'items.*.purchase_order_item_id' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:0'],
            'items.*.unit_cost_actual' => ['nullable', 'numeric', 'min:0'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);

        $receipt = $this->receipts->store(
            $request->user(),
            $po,
            collect($validated['items'])->map(fn ($row) => [
                'purchase_order_item_id' => (int) $row['purchase_order_item_id'],
                'qty' => (int) ($row['qty'] ?? 0),
                'unit_cost_actual' => isset($row['unit_cost_actual']) ? (string) $row['unit_cost_actual'] : null,
                'expiry_date' => $row['expiry_date'] ?? null,
            ])->all(),
            $validated['received_date'] ?? null,
            $validated['delivery_note_no'] ?? null,
            $validated['over_reason'] ?? null,
        );

        return redirect()->route('receipts.show', $receipt)->with('status', __('ui.saved'));
    }

    public function print(PurchaseOrder $po): View
    {
        $po->load(['supplier', 'branch', 'creator', 'items.product']);

        return view('purchasing.orders.print', [
            'po' => $po,
            'storeName' => Setting::value('store.name', 'Lentera'),
        ]);
    }
}
