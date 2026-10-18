<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
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
        'softlens' => ['power', 'wear_period', 'expiry_tracked'],
        'accessory' => [],
    ];

    /**
     * @param  array<string, mixed>  $data  sku, type, name, brand, color, cost_price, sale_price
     * @param  array<string, string>  $attributes
     */
    public function create(User $actor, array $data, array $attributes = []): Product
    {
        $attributes = $this->normalizeAttributes($data['type'], $attributes);

        return DB::transaction(function () use ($actor, $data, $attributes) {
            $product = Product::create([
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'type' => $data['type'],
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'cost_price' => Money::fromDecimal((string) $data['cost_price'])->toDecimalString(),
                'sale_price' => Money::fromDecimal((string) $data['sale_price'])->toDecimalString(),
                'is_active' => true,
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
                'sku' => $data['sku'] ?? $product->sku,
                'barcode' => $data['barcode'] ?? null,
                'type' => $newType,
                'name' => $data['name'] ?? $product->name,
                'brand' => $data['brand'] ?? null,
                'color' => $data['color'] ?? null,
                'cost_price' => Money::fromDecimal((string) ($data['cost_price'] ?? $product->cost_price))->toDecimalString(),
                'sale_price' => Money::fromDecimal((string) ($data['sale_price'] ?? $product->sale_price))->toDecimalString(),
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
