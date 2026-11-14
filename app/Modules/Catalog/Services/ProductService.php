<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PlanGate;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Katalog (A1 — model master + detail persis Artesis).
 *
 * - Master (Product) ringan: kategori, brand, name. Unit jual/stok
 *   adalah detail per tipe di bawah master; SKU unik per tenant.
 * - Master ditentukan eksplisit (product_id, wajib setipe) atau
 *   ditemukan/dibuat dari (kategori tipe, brand, name) dalam
 *   lingkup tenant aktor — pola Artesis & ADR-015 sebelumnya.
 * - Atribut bebas divalidasi terhadap himpunan kunci sah per tipe;
 *   yang di Artesis berbentuk kolom (index & jenis lensa,
 *   expiry_tracked) dibaca dari kolom detail, bukan atribut.
 * - Detail yang sudah dirujuk transaksi TIDAK dapat dipindah
 *   tipenya (typeIsLocked); pemindahan tipe yang sah memindahkan
 *   baris antar tabel detail dengan id yang sama dan memperbarui
 *   detail_type seluruh rujukan morph.
 * - Harga dinormalisasi lewat Money (string desimal 2 digit).
 */
class ProductService
{
    /** Atribut BEBAS per tipe (di luar kolom detail). */
    public const ATTRIBUTE_KEYS = [
        'frame' => ['eye_size', 'bridge_size', 'temple_size'],
        'lens' => ['coating', 'diameter'],
        'rx' => ['sph_min', 'sph_max', 'cyl_min', 'cyl_max'],
        'softlens' => ['wear_period'],
        'accessory' => [],
    ];

    /** @return class-string<Model> */
    public function detailModel(string $type): string
    {
        return Product::DETAIL_MODELS[$type]
            ?? throw ValidationException::withMessages(['type' => __('ui.products.type_invalid')]);
    }

    public function findDetail(string $type, int $id): Model
    {
        $model = $this->detailModel($type);

        return $model::findOrFail($id);
    }

    /**
     * Buat detail baru di bawah master (ditentukan atau otomatis).
     *
     * @param  array<string, mixed>  $data  sku, name, brand, cost_price, sale_price + kolom per tipe
     * @param  array<string, string>  $attributes  atribut bebas per tipe
     */
    public function createDetail(User $actor, string $type, array $data, array $attributes = []): Model
    {
        // Batas produk paket SaaS dihitung per DETAIL (unit SKU);
        // instalasi mandiri lolos.
        app(PlanGate::class)->assertWithinLimit($actor, 'products_max');

        $model = $this->detailModel($type);
        $attributes = $this->normalizeAttributes($type, $attributes);

        return DB::transaction(function () use ($actor, $type, $model, $data, $attributes) {
            $master = $this->resolveMaster($data, $type, $actor);

            $detail = $model::create([
                ...$this->detailColumns($type, $data, null),
                'product_id' => $master->id,
                'sku' => $data['sku'],
                'tenant_id' => $actor->tenant_id,
                'is_active' => true,
            ]);

            $this->syncAttributes($detail, $attributes);

            app(AuditLogger::class)->log(
                action: 'products.create',
                subjectType: 'product_detail',
                subjectId: $detail->id,
                newValues: ['sku' => $detail->sku, 'type' => $type, 'name' => $master->name],
                actor: $actor,
            );

            return $detail;
        });
    }

    /**
     * Ubah detail. Tipe tidak berubah kecuali diminta lewat
     * $data['type'] dan detail belum dirujuk transaksi.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $attributes
     */
    public function updateDetail(User $actor, Model $detail, array $data, array $attributes = []): Model
    {
        $alias = $detail::DETAIL_TYPE;
        $newType = $data['type'] ?? $alias;

        if ($newType !== $alias) {
            if ($this->typeIsLocked($detail)) {
                throw ValidationException::withMessages([
                    'type' => __('ui.products.type_locked'),
                ]);
            }

            return $this->moveDetailType($actor, $detail, $newType, $data, $attributes);
        }

        $attributes = $this->normalizeAttributes($alias, $attributes);

        return DB::transaction(function () use ($actor, $detail, $alias, $data, $attributes) {
            $old = ['sku' => $detail->sku, 'type' => $alias, 'sale_price' => (string) $detail->sale_price];

            $master = array_key_exists('product_id', $data) && $data['product_id'] !== null
                ? $this->resolveMaster($data, $alias, $actor)
                : $detail->product;

            $detail->update([
                ...$this->detailColumns($alias, $data, $detail),
                'product_id' => $master->id,
                'sku' => $data['sku'] ?? $detail->sku,
            ]);

            if (array_key_exists('name', $data) || array_key_exists('brand', $data)) {
                $master->update([
                    'name' => $data['name'] ?? $master->name,
                    'brand' => array_key_exists('brand', $data) ? $data['brand'] : $master->brand,
                ]);
            }

            $this->syncAttributes($detail, $attributes);

            app(AuditLogger::class)->log(
                action: 'products.update',
                subjectType: 'product_detail',
                subjectId: $detail->id,
                oldValues: $old,
                newValues: ['sku' => $detail->sku, 'type' => $alias, 'sale_price' => (string) $detail->sale_price],
                actor: $actor,
            );

            return $detail;
        });
    }

    /**
     * Pindah tipe detail yang belum terkunci: baris dibuat ulang
     * pada tabel tipe baru dengan id yang sama, rujukan morph
     * diperbarui, atribut dipindah.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $attributes
     */
    private function moveDetailType(User $actor, Model $detail, string $newType, array $data, array $attributes): Model
    {
        $oldAlias = $detail::DETAIL_TYPE;
        $newModel = $this->detailModel($newType);
        $attributes = $this->normalizeAttributes($newType, $attributes);

        return DB::transaction(function () use ($actor, $detail, $oldAlias, $newType, $newModel, $data, $attributes) {
            $master = $this->resolveMaster(
                [...$data, 'product_id' => $data['target_master_id'] ?? null],
                $newType,
                $actor,
            );

            // Id dipertahankan lintas tabel (bukan kolom isian massal):
            // rujukan morph menunjuk id yang sama.
            $moved = new $newModel;
            $moved->forceFill([
                ...$this->detailColumns($newType, [...$detail->only(['color', 'sph', 'cyl', 'add', 'power']), ...$data], null),
                'product_id' => $master->id,
                'sku' => $data['sku'] ?? $detail->sku,
                'barcode' => array_key_exists('barcode', $data) ? $data['barcode'] : $detail->barcode,
                'image_path' => array_key_exists('image_path', $data) ? $data['image_path'] : $detail->image_path,
                'cost_price' => Money::fromDecimal((string) ($data['cost_price'] ?? $detail->cost_price))->toDecimalString(),
                'sale_price' => Money::fromDecimal((string) ($data['sale_price'] ?? $detail->sale_price))->toDecimalString(),
                'tenant_id' => $detail->tenant_id,
                'is_active' => $detail->is_active,
            ]);
            $moved->id = $detail->id;
            $moved->save();

            $detail->detailAttributes()->delete();
            $this->syncAttributes($moved, $attributes);
            $detail->delete();

            foreach (['sale_items', 'purchase_order_items', 'purchase_request_items', 'stock_layers', 'stock_movements', 'stock_opname_items', 'stock_transfer_items', 'broken_stocks', 'incentive_items', 'incentive_rules', 'incentive_adjustments'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'detail_type')) {
                    DB::table($table)
                        ->where('detail_id', $moved->id)->where('detail_type', $oldAlias)
                        ->update(['detail_type' => $newType]);
                }
            }
            if (Schema::hasTable('warranty_replacements') && Schema::hasColumn('warranty_replacements', 'replacement_detail_type')) {
                DB::table('warranty_replacements')
                    ->where('replacement_detail_id', $moved->id)->where('replacement_detail_type', $oldAlias)
                    ->update(['replacement_detail_type' => $newType]);
            }

            app(AuditLogger::class)->log(
                action: 'products.update',
                subjectType: 'product_detail',
                subjectId: $moved->id,
                oldValues: ['type' => $oldAlias],
                newValues: ['type' => $newType, 'sku' => $moved->sku],
                actor: $actor,
            );

            return $moved;
        });
    }

    public function setDetailActive(User $actor, Model $detail, bool $active): Model
    {
        $detail->update(['is_active' => $active]);

        app(AuditLogger::class)->log(
            action: $active ? 'products.activate' : 'products.deactivate',
            subjectType: 'product_detail',
            subjectId: $detail->id,
            newValues: ['is_active' => $active],
            actor: $actor,
        );

        return $detail;
    }

    /** Kategori bawaan/aktif untuk sebuah tipe sistem. */
    public function defaultCategoryFor(string $type): ?ProductCategory
    {
        return ProductCategory::where('type', $type)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->first();
    }

    /** Buat master produk (pengganti induk ADR-015). */
    public function createMaster(User $actor, array $data): Product
    {
        $category = $this->resolveCategory($data, $data['type']);

        $master = Product::create([
            'name' => $data['name'],
            'brand' => $data['brand'] ?? null,
            'category_id' => $category?->id,
            'is_active' => true,
            'tenant_id' => $actor->tenant_id,
        ]);

        app(AuditLogger::class)->log(
            action: 'products.parent.create',
            subjectType: 'product',
            subjectId: $master->id,
            newValues: ['type' => $category?->type ?? $data['type'], 'name' => $master->name],
            actor: $actor,
        );

        return $master;
    }

    /**
     * Tentukan kategori: category_id eksplisit wajib setipe;
     * tanpa itu pakai kategori bawaan tipenya.
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

        $type = $mustType ?? ($data['type'] ?? null);

        return $type !== null ? $this->defaultCategoryFor($type) : null;
    }

    /** Ubah master produk. Kategori mengikuti data atau bawaan tipenya. */
    public function updateMaster(User $actor, Product $master, array $data): Product
    {
        $master->update([
            'name' => $data['name'] ?? $master->name,
            'brand' => array_key_exists('brand', $data) ? $data['brand'] : $master->brand,
            'category_id' => $data['category_id'] ?? $master->category_id
                ?? $this->defaultCategoryFor($master->typeKey() ?? 'frame')?->id,
        ]);

        app(AuditLogger::class)->log(
            action: 'products.parent.update',
            subjectType: 'product',
            subjectId: $master->id,
            newValues: ['name' => $master->name, 'brand' => $master->brand],
            actor: $actor,
        );

        return $master;
    }

    public function setMasterActive(User $actor, Product $master, bool $active): Product
    {
        $master->update(['is_active' => $active]);

        app(AuditLogger::class)->log(
            action: $active ? 'products.parent.activate' : 'products.parent.deactivate',
            subjectType: 'product',
            subjectId: $master->id,
            newValues: ['is_active' => $active],
            actor: $actor,
        );

        return $master;
    }

    /**
     * Tentukan master sebuah detail: product_id eksplisit (wajib
     * setipe) atau temukan/buat dari (kategori tipe, brand, name)
     * di lingkup tenant aktor.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveMaster(array $data, string $type, User $actor): Product
    {
        if (! empty($data['product_id'])) {
            // TenantScope membuat master tenant lain tidak ditemukan.
            $master = Product::findOrFail((int) $data['product_id']);
            if ($master->typeKey() !== $type) {
                throw ValidationException::withMessages([
                    'parent_id' => __('ui.products.parent_type_mismatch'),
                ]);
            }

            return $master;
        }

        $category = $this->resolveCategory($data, $type);

        return Product::firstOrCreate(
            ['category_id' => $category?->id, 'brand' => $data['brand'] ?? null, 'name' => $data['name'], 'tenant_id' => $actor->tenant_id],
            ['is_active' => true],
        );
    }

    /**
     * Detail terkunci tipenya bila sudah dirujuk transaksi
     * (sale_items / purchase_order_items / receipt_items lewat item
     * PO) — rujukan morph detail sejak A1. Pemeriksaan hanya
     * berjalan pada tabel & kolom yang sudah ada.
     */
    public function typeIsLocked(Model $detail): bool
    {
        $alias = $detail::DETAIL_TYPE;

        foreach (['sale_items', 'purchase_order_items'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'detail_id')
                && DB::table($table)->where('detail_id', $detail->id)->where('detail_type', $alias)->exists()) {
                return true;
            }
        }

        if (Schema::hasTable('receipt_items') && Schema::hasTable('purchase_order_items')
            && DB::table('receipt_items')
                ->join('purchase_order_items', 'receipt_items.purchase_order_item_id', '=', 'purchase_order_items.id')
                ->where('purchase_order_items.detail_id', $detail->id)
                ->where('purchase_order_items.detail_type', $alias)
                ->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Petakan data mentah menjadi kolom detail per tipe. Bila
     * $existing diberikan, kunci yang tidak hadir pada $data
     * mempertahankan nilai lama (semantik ubah).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function detailColumns(string $type, array $data, ?Model $existing): array
    {
        $pick = fn (string $key, mixed $default = null) => array_key_exists($key, $data)
            ? $data[$key]
            : ($existing?->getAttribute($key) ?? $default);

        $columns = [
            'barcode' => $pick('barcode'),
            'image_path' => $pick('image_path'),
            'cost_price' => Money::fromDecimal((string) ($data['cost_price'] ?? $existing?->cost_price ?? 0))->toDecimalString(),
            'sale_price' => Money::fromDecimal((string) ($data['sale_price'] ?? $existing?->sale_price ?? 0))->toDecimalString(),
        ];

        switch ($type) {
            case 'frame':
                $columns['color'] = $pick('color');
                break;
            case 'lens':
                $columns['type'] = $this->lensKind($data, $existing);
                $columns['index'] = (string) ($data['index'] ?? $existing?->index ?? '');
                $columns['variant'] = $pick('variant');
                $columns['eye_side'] = $data['eye_side'] ?? $existing?->eye_side ?? 'Both';
                $columns['sph'] = $pick('sph');
                $columns['cyl'] = $pick('cyl');
                $columns['add'] = $pick('add');
                break;
            case 'rx':
                $columns['type'] = $pick('rx_type');
                $columns['index'] = $pick('index');
                $columns['variant'] = $pick('variant');
                break;
            case 'softlens':
                $columns['color'] = $pick('color');
                $columns['power'] = $pick('power');
                $columns['expiry_tracked'] = filter_var($data['expiry_tracked'] ?? $existing?->expiry_tracked ?? false, FILTER_VALIDATE_BOOLEAN);
                break;
            case 'accessory':
                $columns['variant'] = $pick('variant');
                // Add-on proses hanya bermakna untuk aksesoris (owner 2026-10-27).
                $columns['is_process_item'] = filter_var($data['is_process_item'] ?? $existing?->is_process_item ?? false, FILTER_VALIDATE_BOOLEAN);
                $columns['expiry_tracked'] = filter_var($data['expiry_tracked'] ?? $existing?->expiry_tracked ?? false, FILTER_VALIDATE_BOOLEAN);
                break;
        }

        return $columns;
    }

    /**
     * Jenis lensa ternormalisasi (pola LensMatrixService lama):
     * teks bebas lens_kind → enum Artesis SV/Progresif/Bifocal;
     * nilai enum yang sudah sah dipertahankan.
     *
     * @param  array<string, mixed>  $data
     */
    private function lensKind(array $data, ?Model $existing): string
    {
        $raw = strtolower((string) ($data['lens_kind'] ?? $data['lens_type'] ?? $existing?->type ?? ''));

        return match (true) {
            str_contains($raw, 'prog') => 'Progresif',
            str_contains($raw, 'bif') => 'Bifocal',
            default => 'SV',
        };
    }

    /** @param array<string, string> $attributes */
    private function syncAttributes(Model $detail, array $attributes): void
    {
        $detail->detailAttributes()->delete();
        foreach ($attributes as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $detail->detailAttributes()->create(['key' => $key, 'value' => (string) $value]);
        }
        $detail->load('detailAttributes');
    }

    /**
     * Normalisasi atribut bebas: kunci di luar himpunan sah tipe
     * DITOLAK (bukan dibuang diam-diam).
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
