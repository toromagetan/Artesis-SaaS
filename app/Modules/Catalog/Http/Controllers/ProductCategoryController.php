<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kategori produk (paritas Artesis): dikelola sebagai data master;
 * tanpa hapus — nonaktif saja (aturan siklus hidup master).
 */
class ProductCategoryController extends Controller
{
    public function __construct(private readonly ProductService $service) {}

    public function index(): View
    {
        $categories = ProductCategory::withCount('parents')
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('catalog.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('catalog.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('product_categories', 'name')],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $this->service->createCategory($request->user(), $validated);

        return redirect()->route('products.categories.index')->with('status', __('ui.categories.created'));
    }

    public function edit(ProductCategory $category): View
    {
        return view('catalog.categories.edit', compact('category'));
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('product_categories', 'name')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $this->service->updateCategory($request->user(), $category, $validated);

        return redirect()->route('products.categories.index')->with('status', __('ui.categories.updated'));
    }

    public function toggleActive(Request $request, ProductCategory $category): RedirectResponse
    {
        $this->service->setCategoryActive($request->user(), $category, ! $category->is_active);

        return back()->with('status', $category->is_active ? __('ui.categories.activated') : __('ui.categories.deactivated'));
    }
}
