<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\ProductService;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Inventory\Services\FifoService;
use App\Modules\Master\Models\Branch;
use App\Support\TenantStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Katalog produk (A1 master + detail; A2 satu halaman kelola
 * persis Artesis). Halaman daftar per tipe adalah SATU-SATUNYA
 * permukaan kelola: buat/ubah berjalan lewat dialog formulir di
 * halaman itu (dialog untuk aksi berisi isian — ADR-018), master
 * dibuat/diperbarui dari formulir detail yang sama, dan tidak ada
 * lagi halaman induk terpisah. GET buat/ubah mengarah ke daftar.
 * Detail tetap halaman penuh; detail dialamatkan per tipe+id
 * karena id detail hanya unik per tabel tipenya.
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

        $relation = Product::DETAIL_RELATIONS[$type];

        $parents = Product::with([$relation => fn ($query) => $query->orderBy('sku')])
            ->whereHas('category', fn ($query) => $query->where('type', $type))
            ->when($q !== '', function ($query) use ($q, $relation) {
                $query->where(function ($sub) use ($q, $relation) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhereHas($relation, fn ($v) => $v->where('sku', 'like', "%{$q}%"));
                });
            })
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Normalisasi nama relasi untuk view: setiap master membawa
        // detailnya pada relasi `variants` (istilah view A1).
        foreach ($parents as $master) {
            $master->setRelation('variants', $master->getRelation($relation));
        }

        $expiries = [];
        if ($type === 'softlens') {
            $context = BranchContext::for($request->user());
            $branchIds = $context->hasGlobalAccess()
                ? Branch::pluck('id')->map(fn ($id) => (int) $id)->all()
                : $context->managedBranchIds();
            $variantIds = $parents->flatMap(fn ($p) => $p->variants->pluck('id'))->all();
            $expiries = app(FifoService::class)->nearestExpiries('softlens', $variantIds, $branchIds);
        }

        // Opsi master untuk pemilih pada dialog formulir (A2).
        $masterOptions = Product::whereHas('category', fn ($query) => $query->where('type', $type))
            ->orderBy('name')->get();

        return view('catalog.products.index', [
            'parents' => $parents, 'type' => $type, 'q' => $q, 'expiries' => $expiries,
            'masterOptions' => $masterOptions, 'attributeKeys' => $this->formAttributeKeys($type),
        ]);
    }

    /** Alih aktif master (A2 — pengganti permukaan induk lama). */
    public function toggleMasterActive(Request $request, Product $parent): RedirectResponse
    {
        $this->service->setMasterActive($request->user(), $parent, ! $parent->is_active);

        return back()->with('status', $parent->is_active ? __('ui.products.activated') : __('ui.products.deactivated'));
    }

    public function show(string $type, int $id): View
    {
        $this->assertType($type);
        $product = $this->service->findDetail($type, $id);
        $product->load(['product', 'detailAttributes']);

        // Baris atribut halaman detail: atribut bebas + nilai kolom
        // yang dulu atribut (index & jenis lensa; expiry_tracked).
        $displayAttributes = [];
        if ($type === 'lens') {
            $displayAttributes['index'] = $product->index !== '' ? $product->index : null;
            $displayAttributes['lens_kind'] = $product->type;
        }
        foreach (ProductService::ATTRIBUTE_KEYS[$type] as $key) {
            $displayAttributes[$key] = $product->attributeValue($key);
        }
        if (in_array($type, ['softlens', 'accessory'], true)) {
            $displayAttributes['expiry_tracked'] = $product->expiry_tracked ? __('ui.yes') : __('ui.no');
        }

        return view('catalog.products.show', compact('product', 'type', 'displayAttributes'));
    }

    /** A2: tidak ada halaman buat terpisah — formulirnya dialog di daftar. */
    public function create(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);

        return redirect()->route('products.index', $type);
    }

    /**
     * Kunci yang dirender form: atribut bebas + kolom berkunci
     * formulir (index & lens_kind lensa; expiry_tracked softlens)
     * agar bentuk form A1 tidak berubah.
     *
     * @return list<string>
     */
    private function formAttributeKeys(string $type): array
    {
        return match ($type) {
            'lens' => ['index', 'coating', 'diameter', 'lens_kind'],
            'softlens' => ['wear_period', 'expiry_tracked'],
            default => ProductService::ATTRIBUTE_KEYS[$type],
        };
    }

    /**
     * Pisahkan masukan form menjadi kolom detail ($data) dan
     * atribut bebas: index/lens_kind lensa adalah kolom;
     * expiry_tracked softlens & aksesoris adalah kolom boolean.
     *
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function splitFormInput(string $type, array $validated): array
    {
        $attributes = $validated['attributes'] ?? [];
        unset($validated['attributes']);

        if ($type === 'lens') {
            foreach (['index', 'lens_kind'] as $key) {
                if (array_key_exists($key, $attributes)) {
                    $validated[$key] = $attributes[$key];
                    unset($attributes[$key]);
                }
            }
        }
        if (in_array($type, ['softlens', 'accessory'], true) && array_key_exists('expiry_tracked', $attributes)) {
            $validated['expiry_tracked'] = $attributes['expiry_tracked'];
            unset($attributes['expiry_tracked']);
        }

        return [$validated, $attributes];
    }

    /** Aturan validasi bersama buat/ubah detail (SKU unik per tenant, §192). */
    private function detailRules(string $type, ?Model $detail, ?int $tenantId): array
    {
        $table = (new (Product::DETAIL_MODELS[$type]))->getTable();
        $id = $detail?->id;
        $perTenant = fn ($rule) => $rule->where(fn ($q) => $q->where('tenant_id', $tenantId));

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
            'sku' => ['required', 'string', 'max:50', $perTenant(Rule::unique($table, 'sku'))->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:50', $perTenant(Rule::unique($table, 'barcode'))->ignore($id)],
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:50'],
            'sph' => ['nullable', 'numeric', 'between:-30,30'],
            'cyl' => ['nullable', 'numeric', 'between:-30,30'],
            'add' => ['nullable', 'numeric', 'between:0,4'],
            'power' => ['nullable', 'numeric', 'between:-30,30'],
            'eye_side' => ['nullable', Rule::in(['R', 'L', 'Both'])],
            'image' => ['nullable', 'image', 'max:2048'],
            'image_remove' => ['nullable', 'boolean'],
            'is_process_item' => ['nullable', 'boolean'],
            'expiry_tracked' => ['nullable', 'boolean'],
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
    private function storeImageFile(Request $request): ?string
    {
        $directory = TenantStorage::directory($request->user(), 'products');

        try {
            $path = $request->file('image')->store($directory, 'public');
        } catch (\Throwable) {
            return null;
        }

        return is_string($path) && $path !== '' ? $path : null;
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertType($type);

        $validated = $request->validate($this->detailRules($type, null, $request->user()->tenant_id));
        $validated['is_process_item'] = $request->boolean('is_process_item');
        $validated['expiry_tracked'] = $request->boolean('expiry_tracked');
        unset($validated['image'], $validated['image_remove']);

        [$data, $attributes] = $this->splitFormInput($type, $validated);
        // Nama field master pada form tetap parent_id (A1).
        $data['product_id'] = $data['parent_id'] ?? null;
        unset($data['parent_id']);

        // Data DULU, berkas kemudian (A-02).
        $product = $this->service->createDetail(
            $request->user(),
            $type,
            [...$data, 'image_path' => null],
            $attributes,
        );

        $warning = null;
        if ($request->hasFile('image')) {
            $path = $this->storeImageFile($request);
            if ($path === null) {
                $warning = __('ui.products.image_store_failed');
            } else {
                try {
                    $product->forceFill(['image_path' => $path])->save();
                } catch (\Throwable $e) {
                    Storage::disk('public')->delete($path);
                    throw $e;
                }
            }
        }

        $redirect = redirect()->route('products.show', [$type, $product->id])->with('status', __('ui.products.created'));
        if ($warning !== null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /** A2: tidak ada halaman ubah terpisah — formulirnya dialog di daftar. */
    public function edit(string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $this->service->findDetail($type, $id);

        return redirect()->route('products.index', $type);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $product = $this->service->findDetail($type, $id);

        $validated = $request->validate([
            ...$this->detailRules($type, $product, $request->user()->tenant_id),
            'type' => ['required', Rule::in(Product::TYPES)],
        ]);
        $validated['is_process_item'] = $request->boolean('is_process_item');
        $validated['expiry_tracked'] = $request->boolean('expiry_tracked');
        unset($validated['image'], $validated['image_remove']);

        $oldPath = $product->image_path;
        $removeImage = $request->boolean('image_remove');
        $newPath = null;

        if ($removeImage) {
            $validated['image_path'] = null;
        } elseif ($request->hasFile('image')) {
            $newPath = $this->storeImageFile($request);
            if ($newPath === null) {
                return back()->withInput()->withErrors(['image' => __('ui.products.image_store_failed_update')]);
            }
            $validated['image_path'] = $newPath;
        }

        $newType = $validated['type'];
        unset($validated['type']);
        [$data, $attributes] = $this->splitFormInput($newType, $validated);
        $data['product_id'] = $data['parent_id'] ?? null;
        unset($data['parent_id']);
        if ($newType !== $type) {
            $data['type'] = $newType;
        }

        try {
            $updated = $this->service->updateDetail(
                $request->user(),
                $product,
                $data,
                $attributes,
            );
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }

        if (($newPath !== null || $removeImage) && $oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('products.show', [$updated::DETAIL_TYPE, $updated->id])->with('status', __('ui.products.updated'));
    }

    public function toggleActive(Request $request, string $type, int $id): RedirectResponse
    {
        $this->assertType($type);
        $product = $this->service->findDetail($type, $id);

        $this->service->setDetailActive($request->user(), $product, ! $product->is_active);

        return back()->with('status', $product->is_active ? __('ui.products.activated') : __('ui.products.deactivated'));
    }
}
