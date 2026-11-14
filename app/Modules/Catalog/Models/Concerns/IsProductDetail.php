<?php

namespace App\Modules\Catalog\Models\Concerns;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductDetailAttribute;
use App\Support\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

/**
 * Perilaku bersama unit jual/stok katalog (A1, adopsi Artesis):
 * baris detail per tipe di bawah master Product. Setiap model
 * detail menetapkan konstanta DETAIL_TYPE (alias morph pendek:
 * frame, lens, rx, softlens, accessory) yang tersimpan pada
 * kolom detail_type rujukan transaksional.
 */
trait IsProductDetail
{
    use TenantScoped;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function detailAttributes(): MorphMany
    {
        return $this->morphMany(ProductDetailAttribute::class, 'detail');
    }

    public function attributeValue(string $key): ?string
    {
        return $this->detailAttributes->firstWhere('key', $key)?->value;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null && $this->image_path !== ''
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    public static function typeAlias(): string
    {
        return static::DETAIL_TYPE;
    }

    public function typeLabel(): string
    {
        return __('ui.products.types.'.static::DETAIL_TYPE);
    }

    /**
     * Deskriptor pembeda detail untuk daftar & pemilih transaksi:
     * kekuatan lensa/softlens dan warna agar detail tak tertukar.
     */
    public function detailLabel(): string
    {
        $parts = [];
        if (static::DETAIL_TYPE === 'lens') {
            if ($this->sph !== null) {
                $parts[] = 'SPH '.$this->formatPower((string) $this->sph);
            }
            if ($this->cyl !== null) {
                $parts[] = 'CYL '.$this->formatPower((string) $this->cyl);
            }
            if ($this->add !== null) {
                $parts[] = 'ADD '.$this->formatPower((string) $this->add);
            }
            if ($this->eye_side !== null && $this->eye_side !== '' && $this->eye_side !== 'Both') {
                $parts[] = $this->eye_side;
            }
        }
        if (static::DETAIL_TYPE === 'softlens' && $this->power !== null) {
            $parts[] = 'Power '.$this->formatPower((string) $this->power);
        }
        if (isset($this->color) && $this->color !== null && $this->color !== '') {
            $parts[] = $this->color;
        }

        return implode(' · ', $parts);
    }

    private function formatPower(string $value): string
    {
        $number = $value + 0;

        return ($number > 0 ? '+' : '').number_format($number, 2, '.', '');
    }
}
