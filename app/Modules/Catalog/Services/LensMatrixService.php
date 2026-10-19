<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Collection;

/**
 * Matriks stok lensa SPH × CYL — alat bantu di dalam alur PO dan
 * opname (BUKAN menu tersendiri; keputusan owner 2026-10-19).
 * Membangun grid dari varian berkekuatan sebuah induk pada satu
 * cabang: baris SPH, kolom CYL, sel berisi varian + stok berjalan.
 */
class LensMatrixService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /** Produk layak matriks: lensa/RX bervarian induk dan ber-SPH. */
    public function isMatrixProduct(Product $product): bool
    {
        return in_array($product->type, ['lens', 'rx'], true)
            && $product->parent_id !== null
            && $product->sph !== null;
    }

    /** Induk yang memiliki minimal satu varian berkekuatan. */
    public function matrixParents(): Collection
    {
        return ProductParent::where('is_active', true)
            ->whereIn('type', ['lens', 'rx'])
            ->whereHas('variants', fn ($q) => $q->whereNotNull('sph')->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * Grid satu induk pada satu cabang.
     *
     * @return array{sphs: list<float>, cyls: list<float>, cells: array<string, list<array{product: Product, stock: int}>>}
     */
    public function gridForParent(ProductParent $parent, int $branchId): array
    {
        $variants = $parent->variants()
            ->where('is_active', true)
            ->whereNotNull('sph')
            ->orderBy('sku')
            ->get();
        $balances = $this->inventory->balancesForBranch($branchId);

        $sphs = $variants->map(fn (Product $p) => $p->sph + 0)->unique()->sortDesc()->values()->all();
        $cyls = $variants->map(fn (Product $p) => ($p->cyl ?? 0) + 0)->unique()->sortDesc()->values()->all();

        $cells = [];
        foreach ($variants as $variant) {
            $key = $this->key($variant->sph + 0, ($variant->cyl ?? 0) + 0);
            $cells[$key][] = ['product' => $variant, 'stock' => (int) ($balances[$variant->id] ?? 0)];
        }

        return ['sphs' => $sphs, 'cyls' => $cyls, 'cells' => $cells];
    }

    /** Kunci sel grid dari pasangan SPH/CYL. */
    public function key(float $sph, float $cyl): string
    {
        return number_format($sph, 2, '.', '').'|'.number_format($cyl, 2, '.', '');
    }

    /** Label daya bertanda dua desimal, mis. -1.00 / +0.50. */
    public function display(float $value): string
    {
        return sprintf('%+.2f', $value);
    }
}
