<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Master\Models\Branch;
use App\Support\TenantStorage;
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
            'is_process_item' => ['nullable', 'boolean'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Simpan berkas gambar ke namespace pemilik aktor (F-01).
     * Penghapusan berkas lama selalu terjadi SETELAH perubahan
     * data berhasil — kegagalan layanan tidak pernah menghapus
     * gambar yang masih dirujuk produk (audit A-02).
     */
    private function storeImageFile(Request $request): string
    {
        return $request->file('image')->store(
            TenantStorage::directory($request->user(), 'products'),
            'public',
        );
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);

        $validated = $request->validate($this->variantRules());
        // Checkbox tak tercentang tidak terkirim — normalkan eksplisit.
        $validated['is_process_item'] = $request->boolean('is_process_item');
        unset($validated['image'], $validated['image_remove']);

        // Data DULU, berkas kemudian (A-02): penolakan layanan —
        // termasuk batas produk paket — tidak meninggalkan berkas
        // yatim, karena belum ada berkas yang ditulis sama sekali.
        $product = $this->service->create(
            $request->user(),
            [...$validated, 'type' => $type, 'image_path' => null],
            $validated['attributes'] ?? [],
        );

        if ($request->hasFile('image')) {
            $path = $this->storeImageFile($request);
            try {
                $product->forceFill(['image_path' => $path])->save();
            } catch (\Throwable $e) {
                Storage::disk('public')->delete($path);
                throw $e;
            }
        }

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
        // Checkbox tak tercentang tidak terkirim — normalkan eksplisit.
        $validated['is_process_item'] = $request->boolean('is_process_item');
        unset($validated['image'], $validated['image_remove']);

        $oldPath = $product->image_path;
        $removeImage = $request->boolean('image_remove');
        $newPath = null;

        if ($removeImage) {
            $validated['image_path'] = null;
        } elseif ($request->hasFile('image')) {
            // Berkas baru ditulis ke namespace pemilik TANPA
            // menyentuh berkas lama; berkas lama baru dihapus
            // setelah pembaruan berhasil (A-02).
            $newPath = $this->storeImageFile($request);
            $validated['image_path'] = $newPath;
        }

        try {
            $this->service->update(
                $request->user(),
                $product,
                $validated,
                $validated['attributes'] ?? [],
            );
        } catch (\Throwable $e) {
            // Pembaruan gagal: berkas baru dibersihkan, berkas lama
            // dan data produk tetap utuh seperti sebelum permintaan.
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }

        if (($newPath !== null || $removeImage) && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('products.show', $product)->with('status', __('ui.products.updated'));
    }

    public function toggleActive(Request $request, Product $product): RedirectResponse
    {
        $this->service->setActive($request->user(), $product, ! $product->is_active);

        return back()->with('status', $product->is_active ? __('ui.products.activated') : __('ui.products.deactivated'));
    }
}
