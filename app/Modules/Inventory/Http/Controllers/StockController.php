<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\LensMatrixService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Support\Money;
use App\Support\XlsxWriter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stok per cabang & pergerakan (PRD Detail 7.1/7.2).
 * Saldo selalu dari buku besar stock_movements (B.4) — halaman ini
 * murni membaca; tidak ada penulisan stok dari controller.
 * A1: unit stok adalah DETAIL produk (morph), bukan master.
 */
class StockController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FifoService $fifo,
    ) {}

    public function index(Request $request): View
    {
        $context = BranchContext::current();
        $branches = $this->visibleBranches($context);
        $branchId = (int) ($request->query('cabang') ?: ($branches->first()?->id ?? 0));
        if ($branchId !== 0 && ! $context->allowsBranch($branchId)) {
            $branchId = (int) ($branches->first()?->id ?? 0);
        }

        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('tipe', '');
        $balances = $branchId !== 0 ? $this->inventory->balancesForBranch($branchId) : [];
        $values = $branchId !== 0 ? $this->fifo->inventoryValues([$branchId]) : [];

        $types = in_array($type, Product::TYPES, true) ? [$type] : Product::TYPES;
        $details = collect();
        foreach ($types as $alias) {
            $model = Product::DETAIL_MODELS[$alias];
            $found = $model::with('product')
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($sub) use ($q) {
                        $sub->where('sku', 'like', "%{$q}%")
                            ->orWhere('barcode', 'like', "%{$q}%")
                            ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%"));
                    });
                })
                ->get();
            $details = $details->merge($found);
        }

        $rows = $details
            ->map(function (Model $detail) use ($balances, $values, $branchId) {
                $key = $detail::DETAIL_TYPE.':'.$detail->id;
                $qty = $balances[$key] ?? 0;
                // Nilai persediaan FIFO (ADR-014): Σ lapisan tersisa.
                $value = $values[$branchId.':'.$key] ?? Money::zero();

                return [
                    'product' => $detail,
                    'qty' => $qty,
                    'value' => $value,
                    'status' => $qty < 0 ? 'backorder' : ($qty === 0 ? 'empty' : ($qty <= 5 ? 'low' : 'safe')),
                ];
            })
            ->filter(fn ($row) => $row['qty'] !== 0 || $q !== '' || $type !== '')
            ->sortBy(fn ($row) => [$row['product']->product?->name ?? '', $row['product']->sku])
            ->values();

        return view('inventory.stock.index', [
            'rows' => $rows,
            'branches' => $branches,
            'branchId' => $branchId,
            'q' => $q,
            'type' => $type,
        ]);
    }

    public function movements(Request $request): View
    {
        $context = BranchContext::current();
        $branches = $this->visibleBranches($context);
        $branchId = $request->query('cabang');
        $reason = (string) $request->query('alasan', '');
        $q = trim((string) $request->query('q', ''));

        $movements = StockMovement::with(['detail.product', 'branch', 'creator'])
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->when(in_array($reason, StockMovement::REASONS, true), fn ($query) => $query->where('reason', $reason))
            ->when($q !== '', function ($query) use ($q) {
                // Pencarian lintas tipe detail: kumpulkan pasangan
                // (alias, id) yang SKU/nama masternya cocok.
                $pairs = [];
                foreach (Product::DETAIL_MODELS as $alias => $model) {
                    $ids = $model::where('sku', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%"))
                        ->pluck('id')->all();
                    if ($ids !== []) {
                        $pairs[] = [$alias, $ids];
                    }
                }
                $query->where(function ($sub) use ($pairs) {
                    foreach ($pairs as [$alias, $ids]) {
                        $sub->orWhere(fn ($w) => $w->where('detail_type', $alias)->whereIn('detail_id', $ids));
                    }
                    if ($pairs === []) {
                        $sub->whereRaw('1 = 0');
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.stock.movements', compact('movements', 'branches', 'branchId', 'reason', 'q'));
    }

    /**
     * Matriks stok lensa (A3 — persis Artesis): grid rentang
     * lengkap per master lensa pada satu cabang, saringan
     * index/coating/sisi mata. Cabang & master terpagar konteks.
     *
     * @return array{0: ?Product, 1: int, 2: Collection, 3: Collection}
     */
    private function matrixSelection(Request $request, LensMatrixService $matrix): array
    {
        $context = BranchContext::current();
        $branches = $this->visibleBranches($context);
        $branchId = (int) ($request->query('cabang') ?: ($branches->first()?->id ?? 0));
        if ($branchId !== 0 && ! $context->allowsBranch($branchId)) {
            $branchId = (int) ($branches->first()?->id ?? 0);
        }

        $parents = $matrix->matrixParents();
        $parent = $parents->firstWhere('id', (int) $request->query('induk', 0)) ?? $parents->first();

        return [$parent, $branchId, $branches, $parents];
    }

    public function lensMatrix(Request $request, LensMatrixService $matrix): View
    {
        [$parent, $branchId, $branches, $parents] = $this->matrixSelection($request, $matrix);

        $index = ($v = (string) $request->query('index', '')) !== '' ? $v : null;
        $coating = ($v = (string) $request->query('coating', '')) !== '' ? $v : null;
        $eyeSide = ($v = (string) $request->query('eye_side', '')) !== '' ? $v : null;

        $grid = $parent !== null && $branchId !== 0
            ? $matrix->gridForParent($parent, $branchId, $index, $coating, $eyeSide)
            : null;
        $options = $parent !== null ? $matrix->filterOptions($parent) : ['indexes' => [], 'coatings' => []];

        return view('inventory.stock.lens-matrix', compact(
            'parent', 'parents', 'branchId', 'branches', 'grid', 'options', 'index', 'coating', 'eyeSide',
        ) + ['matrix' => $matrix]);
    }

    public function lensMatrixExport(Request $request, LensMatrixService $matrix): StreamedResponse
    {
        [$parent, $branchId] = $this->matrixSelection($request, $matrix);
        if ($parent === null || $branchId === 0) {
            abort(404);
        }

        $index = ($v = (string) $request->query('index', '')) !== '' ? $v : null;
        $coating = ($v = (string) $request->query('coating', '')) !== '' ? $v : null;
        $eyeSide = ($v = (string) $request->query('eye_side', '')) !== '' ? $v : null;
        $grid = $matrix->gridForParent($parent, $branchId, $index, $coating, $eyeSide);

        $branchName = Branch::find($branchId)?->name ?? '';
        $rows = [[__('ui.matrix.title').' — '.$parent->name.' @ '.$branchName]];
        $header = ['SPH \\ '.$grid['col_label']];
        foreach ($grid['cols'] as $col) {
            $header[] = $matrix->display($col + 0);
        }
        $rows[] = $header;
        foreach ($grid['rows'] as $row) {
            $line = [$row['label']];
            foreach ($grid['cols'] as $col) {
                $cell = $row['cells'][$matrix->colKey($col + 0)] ?? null;
                $line[] = $cell === null ? '' : $cell['stock'];
            }
            $rows[] = $line;
        }
        $bytes = XlsxWriter::make([['title' => 'Matriks '.$parent->name, 'rows' => $rows]]);

        return response()->streamDownload(function () use ($bytes) {
            echo $bytes;
        }, 'matriks-lensa-'.$parent->id.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function visibleBranches(BranchContext $context)
    {
        return $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();
    }
}
