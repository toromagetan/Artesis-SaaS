<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Master\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Katalog produk (Blueprint D.3 + PRD Detail menu Katalog).
 * Daftar per tipe; detail = halaman penuh; perintah terkelompok
 * Perintah sejajar AksiBar (ADR-018).
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

        // ADR-015: daftar katalog menampilkan INDUK beserta variannya.
        $parents = ProductParent::with(['variants' => fn ($query) => $query->orderBy('sku')])
            ->where('type', $type)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%{$q}%"));
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Varian lama yang belum berinduk tetap ditampilkan.
        $orphans = Product::where('type', $type)->whereNull('parent_id')->orderBy('name')->get();

        // F4.3 (PRD 4.4): daftar softlens menampilkan tanggal
        // kedaluwarsa terdekat dari lapisan bersisa di cabang yang
        // terlihat pemanggil — dipertahankan penuh pada model baru.
        $expiries = [];
        if ($type === 'softlens') {
            $context = BranchContext::for($request->user());
            $branchIds = $context->hasGlobalAccess()
                ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
                : $context->managedBranchIds();
            $variantIds = $parents->flatMap(fn ($p) => $p->variants->pluck('id'))->all();
            $expiries = app(FifoService::class)->nearestExpiries($variantIds, $branchIds);
        }

        return view('catalog.products.index', compact('parents', 'orphans', 'type', 'q', 'expiries'));
    }

    public function show(Product $product): View
    {
        $product->load(['productAttributes', 'parent']);

        return view('catalog.products.show', compact('product'));
    }

    public function create(Request $request, string $type): View
    {
        $this->assertType($type);

        $parent = $request->query('induk') !== null
            ? ProductParent::where('type', $type)->find($request->query('induk'))
            : null;

        return view('catalog.products.create', [
            'type' => $type,
            'parent' => $parent,
            'parents' => ProductParent::where('type', $type)->orderBy('name')->get(),
            'attributeKeys' => ProductService::ATTRIBUTE_KEYS[$type],
        ]);
    }

    /** Aturan validasi bersama buat/ubah varian (ADR-015). */
    private function variantRules(?Product $product = null): array
    {
        $id = $product?->id;

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('product_parents', 'id')],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:50', Rule::unique('products', 'barcode')->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:50'],
            'sph' => ['nullable', 'numeric', 'between:-30,30'],
            'cyl' => ['nullable', 'numeric', 'between:-30,30'],
            'add' => ['nullable', 'numeric', 'between:0,4'],
            'power' => ['nullable', 'numeric', 'between:-30,30'],
            'eye_side' => ['nullable', Rule::in(['R', 'L'])],
            'image' => ['nullable', 'image', 'max:2048'],
            'image_remove' => ['nullable', 'boolean'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:150'],
        ];
    }

    /** Simpan/hapus berkas gambar varian; kembalikan path final. */
    private function handleImage(Request $request, ?Product $existing = null): ?string
    {
        if ($request->boolean('image_remove')) {
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            return null;
        }
        if ($request->hasFile('image')) {
            if ($existing?->image_path) {
                Storage::disk('public')->delete($existing->image_path);
            }

            return $request->file('image')->store('products', 'public');
        }

        return $existing?->image_path;
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);

        $validated = $request->validate($this->variantRules());
        $validated['image_path'] = $this->handleImage($request);
        unset($validated['image'], $validated['image_remove']);

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
            'parents' => ProductParent::where('type', $product->type)->orderBy('name')->get(),
            'attributeKeys' => ProductService::ATTRIBUTE_KEYS[$product->type],
            'typeLocked' => $this->service->typeIsLocked($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            ...$this->variantRules($product),
            'type' => ['required', Rule::in(Product::TYPES)],
        ]);
        $validated['image_path'] = $this->handleImage($request, $product);
        unset($validated['image'], $validated['image_remove']);

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
