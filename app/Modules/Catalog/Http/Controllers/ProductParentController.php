<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Induk produk (ADR-015): detail induk = halaman penuh berisi
 * daftar variannya; perintah terkelompok DropdownAksi.
 */
class ProductParentController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function create(string $type): View
    {
        abort_unless(in_array($type, Product::TYPES, true), 404);

        return view('catalog.parents.create', compact('type'));
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, Product::TYPES, true), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
        ]);

        $parent = $this->service->createParent($request->user(), [...$validated, 'type' => $type]);

        return redirect()->route('products.parents.show', $parent)->with('status', __('ui.products.parent_created'));
    }

    public function show(Request $request, ProductParent $parent): View
    {
        $parent->load(['variants' => fn ($q) => $q->orderBy('sku')]);

        $context = BranchContext::for($request->user());
        $branchIds = $context->hasGlobalAccess()
            ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
            : $context->managedBranchIds();
        $inventory = app(InventoryService::class);
        $stockByVariant = [];
        foreach ($parent->variants as $variant) {
            $total = 0;
            foreach ($branchIds as $branchId) {
                $total += $inventory->currentStock($variant->id, $branchId);
            }
            $stockByVariant[$variant->id] = $total;
        }

        return view('catalog.parents.show', compact('parent', 'stockByVariant'));
    }

    public function edit(ProductParent $parent): View
    {
        return view('catalog.parents.edit', compact('parent'));
    }

    public function update(Request $request, ProductParent $parent): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
        ]);

        $this->service->updateParent($request->user(), $parent, $validated);

        return redirect()->route('products.parents.show', $parent)->with('status', __('ui.products.parent_updated'));
    }

    public function toggleActive(Request $request, ProductParent $parent): RedirectResponse
    {
        $this->service->setParentActive($request->user(), $parent, ! $parent->is_active);

        return back()->with('status', $parent->is_active ? __('ui.products.activated') : __('ui.products.deactivated'));
    }
}
