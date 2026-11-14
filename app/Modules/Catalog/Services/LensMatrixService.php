<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Services\InventoryService;
use Illuminate\Support\Collection;

/**
 * Matriks stok lensa — model mengikuti Artesis, penempatan tetap
 * alat bantu di dalam alur PO dan opname (BUKAN menu tersendiri;
 * keputusan owner 2026-10-19). Menu matriks tersendiri adalah
 * modul A3 program adopsi.
 *
 * A1: matriks bekerja di atas LensDetail (kolom type/index/
 * eye_side/sph/cyl/add persis Artesis) di bawah master Product.
 * Sumbu adalah RENTANG LENGKAP langkah 0,25 sehingga sel tanpa
 * detail tetap tampil (stok 0). Tiga bentuk menurut jenis lensa
 * (kolom type detail):
 *  - SV        : SPH -15,00..+6,00 × CYL -4,00..0,00
 *  - Progresif : SPH -2,00..+2,00 × ADD 0,75..3,00, baris
 *                terpisah per sisi mata (R dan L)
 *  - Bifokal   : SPH -2,00..+2,00 × ADD 0,75..3,00
 *
 * RX tidak lagi bermatriks: RxDetail persis Artesis tidak
 * membawa sph/cyl (rentang RX hidup sebagai atribut).
 */
class LensMatrixService
{
    public const KIND_SV = 'sv';

    public const KIND_PROGRESSIVE = 'progressive';

    public const KIND_BIFOCAL = 'bifocal';

    public function __construct(private readonly InventoryService $inventory) {}

    /** Detail layak matriks: lensa ber-SPH. */
    public function isMatrixProduct(LensDetail $detail): bool
    {
        return $detail->sph !== null;
    }

    /** Master lensa yang memiliki minimal satu detail berkekuatan. */
    public function matrixParents(): Collection
    {
        return Product::where('is_active', true)
            ->whereHas('category', fn ($q) => $q->where('type', 'lens'))
            ->whereHas('lensDetails', fn ($q) => $q->whereNotNull('sph')->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /** Jenis matriks dari kolom type detail (enum Artesis). */
    public function kindOfVariant(LensDetail $detail): string
    {
        return match ($detail->type) {
            'Progresif' => self::KIND_PROGRESSIVE,
            'Bifocal' => self::KIND_BIFOCAL,
            default => self::KIND_SV,
        };
    }

    /** Jenis matriks sebuah master: dari detail pertamanya yang berkekuatan. */
    public function kindForParent(Product $parent): string
    {
        $variant = $parent->lensDetails()->whereNotNull('sph')->where('is_active', true)->orderBy('sku')->first();

        return $variant !== null ? $this->kindOfVariant($variant) : self::KIND_SV;
    }

    /**
     * Nilai saringan index/coating yang tersedia pada detail master.
     *
     * @return array{indexes: list<string>, coatings: list<string>}
     */
    public function filterOptions(Product $parent): array
    {
        $variants = $parent->lensDetails()->where('is_active', true)->with('detailAttributes')->get();

        return [
            'indexes' => $variants->map(fn (LensDetail $d) => $d->index)->filter(fn ($v) => $v !== '' && $v !== null)->unique()->sort()->values()->all(),
            'coatings' => $variants->map(fn (LensDetail $d) => $d->attributeValue('coating'))->filter()->unique()->sort()->values()->all(),
        ];
    }

    /**
     * Grid rentang lengkap satu master pada satu cabang.
     *
     * @return array{kind: string, cols: list<float>, col_label: string, rows: list<array{label: string, sph: float, eye: ?string, cells: array<string, ?array{product: LensDetail, stock: int}>}>}
     */
    public function gridForParent(Product $parent, int $branchId, ?string $index = null, ?string $coating = null): array
    {
        $kind = $this->kindForParent($parent);
        $balances = $this->inventory->balancesForBranch($branchId);

        $variants = $parent->lensDetails()
            ->where('is_active', true)
            ->whereNotNull('sph')
            ->with('detailAttributes')
            ->orderBy('sku')
            ->get()
            ->filter(function (LensDetail $d) use ($index, $coating) {
                if ($index !== null && $index !== '' && $d->index !== $index) {
                    return false;
                }
                if ($coating !== null && $coating !== '' && $d->attributeValue('coating') !== $coating) {
                    return false;
                }

                return true;
            });

        $cellOf = function (LensDetail $d) use ($balances) {
            return ['product' => $d, 'stock' => (int) ($balances['lens:'.$d->id] ?? 0)];
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
     * Kelompok matriks untuk item snapshot opname: hanya item
     * berdetail LensDetail, dikelompokkan per master.
     *
     * @param  iterable<object>  $items  item opname berelasi detail (morph)
     * @return list<array{parent: Product, col_label: string, cols: list<float>, rows: list<array{label: string, cells: array<string, list<object>}>}>}
     */
    public function snapshotGroups(iterable $items): array
    {
        $byParent = [];
        foreach ($items as $item) {
            $detail = $item->detail ?? null;
            if (! $detail instanceof LensDetail || ! $this->isMatrixProduct($detail)) {
                continue;
            }
            $byParent[$detail->product_id]['parent'] = $detail->product;
            $byParent[$detail->product_id]['items'][] = $item;
        }

        $groups = [];
        foreach ($byParent as $data) {
            $parent = $data['parent'];
            $list = collect($data['items']);
            $kind = $this->kindForParent($parent);

            if ($kind === self::KIND_PROGRESSIVE) {
                $cols = $list->map(fn ($i) => ($i->detail->add ?? 0) + 0)->unique()->sort()->values()->all();
                $rows = [];
                $rowKeys = $list->map(fn ($i) => [$i->detail->sph + 0, $i->detail->eye_side])->unique()->sortDesc();
                foreach ($rowKeys as [$sph, $eye]) {
                    $cells = [];
                    foreach ($cols as $add) {
                        $cells[$this->colKey($add)] = $list->filter(fn ($i) => $i->detail->sph + 0 == $sph && ($i->detail->add ?? 0) + 0 == $add && $i->detail->eye_side === $eye)->values()->all();
                    }
                    $rows[] = ['label' => $this->display($sph).' · '.($eye ?? '—'), 'cells' => $cells];
                }
                $groups[] = ['parent' => $parent, 'col_label' => 'ADD', 'cols' => $cols, 'rows' => $rows];

                continue;
            }

            if ($kind === self::KIND_BIFOCAL) {
                $cols = $list->map(fn ($i) => ($i->detail->add ?? 0) + 0)->unique()->sort()->values()->all();
                $rows = [];
                foreach ($list->map(fn ($i) => $i->detail->sph + 0)->unique()->sortDesc() as $sph) {
                    $cells = [];
                    foreach ($cols as $add) {
                        $cells[$this->colKey($add)] = $list->filter(fn ($i) => $i->detail->sph + 0 == $sph && ($i->detail->add ?? 0) + 0 == $add)->values()->all();
                    }
                    $rows[] = ['label' => $this->display($sph), 'cells' => $cells];
                }
                $groups[] = ['parent' => $parent, 'col_label' => 'ADD', 'cols' => $cols, 'rows' => $rows];

                continue;
            }

            $cols = $list->map(fn ($i) => ($i->detail->cyl ?? 0) + 0)->unique()->sortDesc()->values()->all();
            $rows = [];
            foreach ($list->map(fn ($i) => $i->detail->sph + 0)->unique()->sortDesc() as $sph) {
                $cells = [];
                foreach ($cols as $cyl) {
                    $cells[$this->colKey($cyl)] = $list->filter(fn ($i) => $i->detail->sph + 0 == $sph && ($i->detail->cyl ?? 0) + 0 == $cyl)->values()->all();
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

    /** Kunci sel dari pasangan daya (untuk pencocokan detail). */
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
