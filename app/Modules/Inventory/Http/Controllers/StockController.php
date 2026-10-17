<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Stok per cabang & pergerakan (PRD Detail 7.1/7.2).
 * Saldo selalu dari buku besar stock_movements (B.4) — halaman ini
 * murni membaca; tidak ada penulisan stok dari controller.
 */
class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

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

        $products = Product::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->when(in_array($type, Product::TYPES, true), fn ($query) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function (Product $product) use ($balances) {
                $qty = $balances[$product->id] ?? 0;
                $value = $qty > 0
                    ? Money::fromDecimal((string) $product->cost_price)->mul($qty)
                    : Money::zero();

                return [
                    'product' => $product,
                    'qty' => $qty,
                    'value' => $value,
                    'status' => $qty < 0 ? 'backorder' : ($qty === 0 ? 'empty' : ($qty <= 5 ? 'low' : 'safe')),
                ];
            })
            ->filter(fn ($row) => $row['qty'] !== 0 || $q !== '' || $type !== '');

        return view('inventory.stock.index', [
            'rows' => $products,
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

        $movements = StockMovement::with(['product', 'branch', 'creator'])
            ->when($branchId, fn ($query) => $query->where('branch_id', (int) $branchId))
            ->when(in_array($reason, StockMovement::REASONS, true), fn ($query) => $query->where('reason', $reason))
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('product', function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")->orWhere('sku', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('inventory.stock.movements', compact('movements', 'branches', 'branchId', 'reason', 'q'));
    }

    private function visibleBranches(BranchContext $context)
    {
        return $context->hasGlobalAccess()
            ? Branch::orderBy('name')->get()
            : Branch::whereIn('id', $context->managedBranchIds())->orderBy('name')->get();
    }
}
