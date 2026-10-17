<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Katalog produk (Blueprint D.3 + PRD Detail menu Katalog).
 * Daftar per tipe; detail = halaman penuh; perintah terkelompok
 * DropdownAksi (kontrak UI).
 */
class ProductController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    private function assertType(string $type): void
    {
        abort_unless(in_array($type, Product::TYPES, true), 404);
    }

    public function index(Request $request, string $type): View
    {
        $this->assertType($type);
        $q = trim((string) $request->query('q', ''));

        $products = Product::with('productAttributes')
            ->where('type', $type)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('sku', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('catalog.products.index', compact('products', 'type', 'q'));
    }

    public function show(Product $product): View
    {
        $product->load('productAttributes');

        return view('catalog.products.show', compact('product'));
    }

    public function create(string $type): View
    {
        $this->assertType($type);

        return view('catalog.products.create', [
            'type' => $type,
            'attributeKeys' => ProductService::ATTRIBUTE_KEYS[$type],
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);

        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:150'],
        ]);

        $product = $this->service->create(
            $request->user(),
            [...$validated, 'type' => $type],
            $validated['attributes'] ?? [],
        );

        return redirect()->route('products.show', $product)->with('status', __('ui.products.created'));
    }

    public function edit(Product $product): View
    {
        $product->load('productAttributes');

        return view('catalog.products.edit', [
            'product' => $product,
            'attributeKeys' => ProductService::ATTRIBUTE_KEYS[$product->type],
            'typeLocked' => $this->service->typeIsLocked($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($product->id)],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:50'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:150'],
        ]);

        $this->service->update(
            $request->user(),
            $product,
            $validated,
            $validated['attributes'] ?? [],
        );

        return redirect()->route('products.show', $product)->with('status', __('ui.products.updated'));
    }

    public function toggleActive(Request $request, Product $product): RedirectResponse
    {
        $this->service->setActive($request->user(), $product, ! $product->is_active);

        return back()->with('status', $product->is_active ? __('ui.products.activated') : __('ui.products.deactivated'));
    }
}
