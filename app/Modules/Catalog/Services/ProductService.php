<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PlanGate;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Katalog (Blueprint D.3).
 *
 * - SKU unik (ditolak bila duplikat).
 * - Atribut divalidasi terhadap himpunan kunci sah per tipe (B.2).
 * - Produk yang sudah terpakai transaksi TIDAK dapat diubah tipenya
 *   (pemeriksaan aktif otomatis begitu tabel transaksi hadir).
 * - Harga dinormalisasi lewat Money (string desimal 2 digit).
 */
class ProductService
{
    /** Himpunan kunci atribut sah per tipe (Blueprint B.2). */
    public const ATTRIBUTE_KEYS = [
        'frame' => ['eye_size', 'bridge_size', 'temple_size'],
        'lens' => ['index', 'coating', 'diameter', 'lens_kind'],
        'rx' => ['sph_min', 'sph_max', 'cyl_min', 'cyl_max'],
        'softlens' => ['wear_period', 'expiry_tracked'],
        'accessory' => [],
    ];

    /**
     * @param  array<string, mixed>  $data  sku, type, name, brand, color, cost_price, sale_price
     * @param  array<string, string>  $attributes
     */
    public function create(User $actor, array $data, array $attributes = []): Product
    {
        // M4: batas produk paket SaaS (tenant aktor); instalasi mandiri lolos.
        app(PlanGate::class)->assertWithinLimit($actor, 'products_max');

        $attributes = $this->normalizeAttributes($data['type'], $attributes);

        return DB::transaction(function () use ($actor, $data, $attributes) {
            $parent = $this->resolveParent($data);

            $product = Product::create([
                'parent_id' => $parent->id,
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'type' => $data['type'],
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'sph' => $data['sph'] ?? null,
                'cyl' => $data['cyl'] ?? null,
                'add' => $data['add'] ?? null,
                'power' => $data['power'] ?? null,
                'eye_side' => $data['eye_side'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'cost_price' => Money::fromDecimal((string) $data['cost_price'])->toDecimalString(),
                'sale_price' => Money::fromDecimal((string) $data['sale_price'])->toDecimalString(),
                'is_active' => true,
                'tenant_id' => $actor->tenant_id,
                // Add-on proses hanya bermakna untuk aksesoris (owner 2026-10-27).
                'is_process_item' => $data['type'] === 'accessory'
                    && filter_var($data['is_process_item'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->syncAttributes($product, $attributes);

            app(AuditLogger::class)->log(
                action: 'products.create',
                subjectType: 'product',
                subjectId: $product->id,
                newValues: ['sku' => $product->sku, 'type' => $product->type, 'name' => $product->name],
                actor: $actor,
            );

            return $product;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $attributes
     */
    public function update(User $actor, Product $product, array $data, array $attributes = []): Product
    {
        $newType = $data['type'] ?? $product->type;

        if ($newType !== $product->type && $this->typeIsLocked($product)) {
            throw ValidationException::withMessages([
                'type' => __('ui.products.type_locked'),
            ]);
        }

        $attributes = $this->normalizeAttributes($newType, $attributes);

        return DB::transaction(function () use ($actor, $product, $data, $attributes, $newType) {
            $old = [
                'sku' => $product->sku, 'type' => $product->type, 'name' => $product->name,
                'sale_price' => (string) $product->sale_price,
            ];

            $product->update([
                'parent_id' => array_key_exists('parent_id', $data) && $data['parent_id'] !== null
                    ? $this->resolveParent([...$data, 'type' => $newType])->id
                    : $product->parent_id,
                'sku' => $data['sku'] ?? $product->sku,
                'barcode' => $data['barcode'] ?? null,
                'type' => $newType,
                'name' => $data['name'] ?? $product->name,
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'sph' => array_key_exists('sph', $data) ? $data['sph'] : $product->sph,
                'cyl' => array_key_exists('cyl', $data) ? $data['cyl'] : $product->cyl,
                'add' => array_key_exists('add', $data) ? $data['add'] : $product->add,
                'power' => array_key_exists('power', $data) ? $data['power'] : $product->power,
                'eye_side' => array_key_exists('eye_side', $data) ? $data['eye_side'] : $product->eye_side,
                'image_path' => array_key_exists('image_path', $data) ? $data['image_path'] : $product->image_path,
                'cost_price' => Money::fromDecimal((string) ($data['cost_price'] ?? $product->cost_price))->toDecimalString(),
                'sale_price' => Money::fromDecimal((string) ($data['sale_price'] ?? $product->sale_price))->toDecimalString(),
                'is_process_item' => $newType === 'accessory'
                    && filter_var($data['is_process_item'] ?? $product->is_process_item, FILTER_VALIDATE_BOOLEAN),
            ]);

            $this->syncAttributes($product, $attributes);

            app(AuditLogger::class)->log(
                action: 'products.update',
                subjectType: 'product',
                subjectId: $product->id,
                oldValues: $old,
                newValues: ['sku' => $product->sku, 'type' => $product->type, 'name' => $product->name, 'sale_price' => (string) $product->sale_price],
                actor: $actor,
            );

            return $product;
        });
    }

    /** Kategori bawaan/aktif untuk sebuah tipe sistem. */
    public function defaultCategoryFor(string $type): ?ProductCategory
    {
        return ProductCategory::where('type', $type)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->first();
    }

    /** Buat induk produk (ADR-015). */
    public function createParent(User $actor, array $data): ProductParent
    {
        $category = $this->resolveCategory($data, $data['type']);

        $parent = ProductParent::create([
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'category_id' => $category?->id,
            'type' => $category?->type ?? $data['type'],
            'is_active' => true,
        ]);

        app(AuditLogger::class)->log(
            action: 'products.parent.create',
            subjectType: 'product_parent',
            subjectId: $parent->id,
            newValues: ['type' => $parent->type, 'name' => $parent->name],
            actor: $actor,
        );

        return $parent;
    }

    /**
     * Tentukan kategori induk: category_id eksplisit wajib setipe
     * dengan induk; tanpa itu pakai kategori bawaan tipenya.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveCategory(array $data, ?string $mustType = null): ?ProductCategory
    {
        if (! empty($data['category_id'])) {
            $category = ProductCategory::findOrFail((int) $data['category_id']);
            if ($mustType !== null && $category->type !== $mustType) {
                throw ValidationException::withMessages([
                    'category_id' => __('ui.categories.type_mismatch'),
                ]);
            }

            return $category;
        }

        return isset($data['type']) ? $this->defaultCategoryFor($data['type']) : null;
    }

    /** Ubah induk produk (ADR-015). Kategori mengikuti tipe induk. */
    public function updateParent(User $actor, ProductParent $parent, array $data): ProductParent
    {
        $parent->update([
            'name' => $data['name'] ?? $parent->name,
            'brand' => array_key_exists('brand', $data) ? $data['brand'] : $parent->brand,
            'category_id' => $parent->category_id ?? $this->defaultCategoryFor($parent->type)?->id,
        ]);

        app(AuditLogger::class)->log(
            action: 'products.parent.update',
            subjectType: 'product_parent',
            subjectId: $parent->id,
            newValues: ['name' => $parent->name, 'brand' => $parent->brand],
            actor: $actor,
        );

        return $parent;
    }

    public function setParentActive(User $actor, ProductParent $parent, bool $active): ProductParent
    {
        $parent->update(['is_active' => $active]);

        app(AuditLogger::class)->log(
            action: $active ? 'products.parent.activate' : 'products.parent.deactivate',
            subjectType: 'product_parent',
            subjectId: $parent->id,
            newValues: ['is_active' => $active],
            actor: $actor,
        );

        return $parent;
    }

    /**
     * Tentukan induk sebuah varian (ADR-015): parent_id eksplisit
     * (wajib setipe) atau temukan/buat dari (type, brand, name).
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveParent(array $data): ProductParent
    {
        if (! empty($data['parent_id'])) {
            $parent = ProductParent::findOrFail((int) $data['parent_id']);
            if ($parent->type !== $data['type']) {
                throw ValidationException::withMessages([
                    'parent_id' => __('ui.products.parent_type_mismatch'),
                ]);
            }

            return $parent;
        }

        return ProductParent::firstOrCreate(
            ['type' => $data['type'], 'brand' => $data['brand'] ?? null, 'name' => $data['name']],
            ['is_active' => true, 'category_id' => $this->defaultCategoryFor($data['type'])?->id],
        );
    }

    public function setActive(User $actor, Product $product, bool $active): Product
    {
        $product->update(['is_active' => $active]);

        app(AuditLogger::class)->log(
            action: $active ? 'products.activate' : 'products.deactivate',
            subjectType: 'product',
            subjectId: $product->id,
            newValues: ['is_active' => $active],
            actor: $actor,
        );

        return $product;
    }

    /**
     * Produk terkunci tipenya bila sudah dirujuk transaksi
     * (sale_items / purchase_order_items / receipt_items). Pemeriksaan
     * hanya berjalan pada tabel yang sudah ada — aktif otomatis saat
     * modul transaksinya tiba.
     */
    public function typeIsLocked(Product $product): bool
    {
        foreach (['sale_items', 'purchase_order_items', 'receipt_items'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('product_id', $product->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $attributes */
    private function syncAttributes(Product $product, array $attributes): void
    {
        $product->productAttributes()->delete();
        foreach ($attributes as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $product->productAttributes()->create(['key' => $key, 'value' => (string) $value]);
        }
        $product->load('productAttributes');
    }

    /**
     * Normalisasi atribut mentah dari formulir/request: kunci di luar
     * himpunan sah tipe DITOLAK (bukan dibuang diam-diam);
     * expiry_tracked menjadi '1'/'0' dari keberadaan nilai.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, string>
     */
    private function normalizeAttributes(string $type, array $raw): array
    {
        $this->assertValidAttributes($type, $raw);

        $normalized = [];
        foreach ($raw as $key => $value) {
            $normalized[$key] = (string) $value;
        }
        if (in_array('expiry_tracked', self::ATTRIBUTE_KEYS[$type] ?? [], true)) {
            $normalized['expiry_tracked'] = ! empty($raw['expiry_tracked']) ? '1' : '0';
        }

        return $normalized;
    }

    /** @param array<string, mixed> $attributes */
    private function assertValidAttributes(string $type, array $attributes): void
    {
        $allowed = self::ATTRIBUTE_KEYS[$type] ?? null;
        if ($allowed === null) {
            throw ValidationException::withMessages(['type' => __('ui.products.type_invalid')]);
        }

        $unknown = array_diff(array_keys($attributes), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'attributes' => __('ui.products.attribute_unknown', ['keys' => implode(', ', $unknown)]),
            ]);
        }
    }
}
