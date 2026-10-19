<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Collection;

/**
 * Matriks stok lensa — model mengikuti Artesis, penempatan tetap
 * alat bantu di dalam alur PO dan opname (BUKAN menu tersendiri;
 * keputusan owner 2026-10-19).
 *
 * Model Artesis: sumbu adalah RENTANG LENGKAP langkah 0,25
 * sehingga sel tanpa varian tetap tampil (stok 0). Tiga bentuk
 * menurut jenis lensa (atribut lens_kind varian):
 *  - SV        : SPH -15,00..+6,00 × CYL -4,00..0,00
 *  - Progresif : SPH -2,00..+2,00 × ADD 0,75..3,00, baris
 *                terpisah per sisi mata (R dan L)
 *  - Bifokal   : SPH -2,00..+2,00 × ADD 0,75..3,00
 */
class LensMatrixService
{
    public const KIND_SV = 'sv';

    public const KIND_PROGRESSIVE = 'progressive';

    public const KIND_BIFOCAL = 'bifocal';

    public function __construct(private readonly InventoryService $inventory) {}

    /** Produk layak matriks: lensa/RX bervarian induk dan ber-SPH. */
    public function isMatrixProduct(Product $product): bool
    {
        return in_array($product->type, ['lens', 'rx'], true)
            && $product->parent_id !== null
            && $product->sph !== null;
    }

    /** Induk lensa yang memiliki minimal satu varian berkekuatan. */
    public function matrixParents(): Collection
    {
        return ProductParent::where('is_active', true)
            ->where('type', 'lens')
            ->whereHas('variants', fn ($q) => $q->whereNotNull('sph')->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /** Jenis matriks dari atribut lens_kind (teks bebas → dinormalkan). */
    public function kindOfVariant(Product $product): string
    {
        $raw = strtolower((string) ($product->attributeValue('lens_kind') ?? ''));

        return match (true) {
            str_contains($raw, 'prog') => self::KIND_PROGRESSIVE,
            str_contains($raw, 'bif') => self::KIND_BIFOCAL,
            default => self::KIND_SV,
        };
    }

    /** Jenis matriks sebuah induk: dari varian pertamanya yang berkekuatan. */
    public function kindForParent(ProductParent $parent): string
    {
        $variant = $parent->variants()->whereNotNull('sph')->where('is_active', true)->orderBy('sku')->first();

        return $variant !== null ? $this->kindOfVariant($variant) : self::KIND_SV;
    }

    /**
     * Nilai saringan index/coating yang tersedia pada varian induk.
     *
     * @return array{indexes: list<string>, coatings: list<string>}
     */
    public function filterOptions(ProductParent $parent): array
    {
        $variants = $parent->variants()->where('is_active', true)->get();

        return [
            'indexes' => $variants->map(fn (Product $p) => $p->attributeValue('index'))->filter()->unique()->sort()->values()->all(),
            'coatings' => $variants->map(fn (Product $p) => $p->attributeValue('coating'))->filter()->unique()->sort()->values()->all(),
        ];
    }

    /**
     * Grid rentang lengkap satu induk pada satu cabang.
     *
     * @return array{kind: string, cols: list<float>, col_label: string, rows: list<array{label: string, sph: float, eye: ?string, cells: array<string, ?array{product: Product, stock: int}>}>}
     */
    public function gridForParent(ProductParent $parent, int $branchId, ?string $index = null, ?string $coating = null): array
    {
        $kind = $this->kindForParent($parent);
        $balances = $this->inventory->balancesForBranch($branchId);

        $variants = $parent->variants()
            ->where('is_active', true)
            ->whereNotNull('sph')
            ->orderBy('sku')
            ->get()
            ->filter(function (Product $p) use ($index, $coating) {
                if ($index !== null && $index !== '' && $p->attributeValue('index') !== $index) {
                    return false;
                }
                if ($coating !== null && $coating !== '' && $p->attributeValue('coating') !== $coating) {
                    return false;
                }

                return true;
            });

        $cellOf = function (Product $p) use ($balances) {
            return ['product' => $p, 'stock' => (int) ($balances[$p->id] ?? 0)];
        };

        if ($kind === self::KIND_PROGRESSIVE) {
            $cols = $this->range(0.75, 3.00);
            $byKey = [];
            foreach ($variants as $variant) {
                if ($variant->add === null || $variant->eye_side === null) {
                    continue;
                }
                $byKey[$this->key($variant->sph + 0, $variant->add + 0).'|'.$variant->eye_side] ??= $cellOf($variant);
            }
            $rows = [];
            foreach ($this->range(-2.00, 2.00, descending: true) as $sph) {
                foreach (['R', 'L'] as $eye) {
                    $cells = [];
                    foreach ($cols as $add) {
                        $cells[$this->colKey($add)] = $byKey[$this->key($sph, $add).'|'.$eye] ?? null;
                    }
                    $rows[] = ['label' => $this->display($sph).' · '.$eye, 'sph' => $sph, 'eye' => $eye, 'cells' => $cells];
                }
            }

            return ['kind' => $kind, 'cols' => $cols, 'col_label' => 'ADD', 'rows' => $rows];
        }

        if ($kind === self::KIND_BIFOCAL) {
            $cols = $this->range(0.75, 3.00);
            $byKey = [];
            foreach ($variants as $variant) {
                if ($variant->add === null) {
                    continue;
                }
                $byKey[$this->key($variant->sph + 0, $variant->add + 0)] ??= $cellOf($variant);
            }
            $rows = [];
            foreach ($this->range(-2.00, 2.00, descending: true) as $sph) {
                $cells = [];
                foreach ($cols as $add) {
                    $cells[$this->colKey($add)] = $byKey[$this->key($sph, $add)] ?? null;
                }
                $rows[] = ['label' => $this->display($sph), 'sph' => $sph, 'eye' => null, 'cells' => $cells];
            }

            return ['kind' => $kind, 'cols' => $cols, 'col_label' => 'ADD', 'rows' => $rows];
        }

        // SV: SPH menurun +6,00 → -15,00; CYL menurun 0,00 → -4,00.
        $cols = $this->range(-4.00, 0.00, descending: true);
        $byKey = [];
        foreach ($variants as $variant) {
            $byKey[$this->key($variant->sph + 0, ($variant->cyl ?? 0) + 0)] ??= $cellOf($variant);
        }
        $rows = [];
        foreach ($this->range(-15.00, 6.00, descending: true) as $sph) {
            $cells = [];
            foreach ($cols as $cyl) {
                $cells[$this->colKey($cyl)] = $byKey[$this->key($sph, $cyl)] ?? null;
            }
            $rows[] = ['label' => $this->display($sph), 'sph' => $sph, 'eye' => null, 'cells' => $cells];
        }

        return ['kind' => self::KIND_SV, 'cols' => $cols, 'col_label' => 'CYL', 'rows' => $rows];
    }

    /**
     * Kelompok matriks untuk item snapshot opname: mengikuti bentuk
     * per jenis untuk induk lensa; produk berkekuatan lain (RX)
     * memakai grid SPH×CYL dari item yang ada saja.
     *
     * @param  iterable<object>  $items  item opname berelasi product.parent
     * @return list<array{parent: ProductParent, col_label: string, cols: list<float>, rows: list<array{label: string, cells: array<string, list<object>}>}>}
     */
    public function snapshotGroups(iterable $items): array
    {
        $byParent = [];
        foreach ($items as $item) {
            $product = $item->product;
            if (! $this->isMatrixProduct($product)) {
                continue;
            }
            $byParent[$product->parent_id]['parent'] = $product->parent;
            $byParent[$product->parent_id]['items'][] = $item;
        }

        $groups = [];
        foreach ($byParent as $data) {
            $parent = $data['parent'];
            $list = collect($data['items']);
            $kind = $parent->type === 'lens' ? $this->kindForParent($parent) : self::KIND_SV;

            if ($kind === self::KIND_PROGRESSIVE) {
                $cols = $list->map(fn ($i) => ($i->product->add ?? 0) + 0)->unique()->sort()->values()->all();
                $rows = [];
                $rowKeys = $list->map(fn ($i) => [$i->product->sph + 0, $i->product->eye_side])->unique()->sortDesc();
                foreach ($rowKeys as [$sph, $eye]) {
                    $cells = [];
                    foreach ($cols as $add) {
                        $cells[$this->colKey($add)] = $list->filter(fn ($i) => $i->product->sph + 0 == $sph && ($i->product->add ?? 0) + 0 == $add && $i->product->eye_side === $eye)->values()->all();
                    }
                    $rows[] = ['label' => $this->display($sph).' · '.($eye ?? '—'), 'cells' => $cells];
                }
                $groups[] = ['parent' => $parent, 'col_label' => 'ADD', 'cols' => $cols, 'rows' => $rows];

                continue;
            }

            if ($kind === self::KIND_BIFOCAL) {
                $cols = $list->map(fn ($i) => ($i->product->add ?? 0) + 0)->unique()->sort()->values()->all();
                $rows = [];
                foreach ($list->map(fn ($i) => $i->product->sph + 0)->unique()->sortDesc() as $sph) {
                    $cells = [];
                    foreach ($cols as $add) {
                        $cells[$this->colKey($add)] = $list->filter(fn ($i) => $i->product->sph + 0 == $sph && ($i->product->add ?? 0) + 0 == $add)->values()->all();
                    }
                    $rows[] = ['label' => $this->display($sph), 'cells' => $cells];
                }
                $groups[] = ['parent' => $parent, 'col_label' => 'ADD', 'cols' => $cols, 'rows' => $rows];

                continue;
            }

            $cols = $list->map(fn ($i) => ($i->product->cyl ?? 0) + 0)->unique()->sortDesc()->values()->all();
            $rows = [];
            foreach ($list->map(fn ($i) => $i->product->sph + 0)->unique()->sortDesc() as $sph) {
                $cells = [];
                foreach ($cols as $cyl) {
                    $cells[$this->colKey($cyl)] = $list->filter(fn ($i) => $i->product->sph + 0 == $sph && ($i->product->cyl ?? 0) + 0 == $cyl)->values()->all();
                }
                $rows[] = ['label' => $this->display($sph), 'cells' => $cells];
            }
            $groups[] = ['parent' => $parent, 'col_label' => 'CYL', 'cols' => $cols, 'rows' => $rows];
        }

        return $groups;
    }

    /** Rentang daya langkah 0,25 memakai aritmetika perempat utuh. */
    public function range(float $min, float $max, bool $descending = false): array
    {
        $values = [];
        for ($q = (int) round($min * 4); $q <= (int) round($max * 4); $q++) {
            $values[] = $q / 4;
        }

        return $descending ? array_reverse($values) : $values;
    }

    /** Kunci sel dari pasangan daya (untuk pencocokan varian). */
    public function key(float $sph, float $second): string
    {
        return number_format($sph, 2, '.', '').'|'.number_format($second, 2, '.', '');
    }

    /** Kunci kolom grid dari satu nilai daya. */
    public function colKey(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** Label daya bertanda dua desimal, mis. -1.00 / +0.50. */
    public function display(float $value): string
    {
        return sprintf('%+.2f', $value);
    }
}
