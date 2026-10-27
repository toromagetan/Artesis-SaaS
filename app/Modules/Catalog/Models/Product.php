<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Produk katalog (Blueprint B.2). Lima tipe terkunci:
 * frame, lens, rx, softlens, accessory.
 */
class Product extends Model
{
    use HasFactory;

    public const TYPES = ['frame', 'lens', 'rx', 'softlens', 'accessory'];

    protected $fillable = [
        'parent_id', 'sku', 'barcode', 'type', 'name', 'brand', 'color',
        'sph', 'cyl', 'add', 'power', 'eye_side', 'image_path',
        'cost_price', 'sale_price', 'is_active', 'is_process_item',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'sph' => 'decimal:2',
            'cyl' => 'decimal:2',
            'add' => 'decimal:2',
            'power' => 'decimal:2',
            'is_active' => 'boolean',
            'is_process_item' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProductParent::class, 'parent_id');
    }

    /**
     * Deskriptor detail varian untuk daftar & pemilih transaksi
     * (ADR-015): kekuatan lensa/softlens agar varian tak tertukar.
     */
    public function detailLabel(): string
    {
        $parts = [];
        if ($this->type === 'lens') {
            if ($this->sph !== null) {
                $parts[] = 'SPH '.$this->formatPower((string) $this->sph);
            }
            if ($this->cyl !== null) {
                $parts[] = 'CYL '.$this->formatPower((string) $this->cyl);
            }
            if ($this->add !== null) {
                $parts[] = 'ADD '.$this->formatPower((string) $this->add);
            }
            if ($this->eye_side !== null && $this->eye_side !== '') {
                $parts[] = $this->eye_side;
            }
        }
        if ($this->type === 'softlens' && $this->power !== null) {
            $parts[] = 'Power '.$this->formatPower((string) $this->power);
        }
        if ($this->color !== null && $this->color !== '') {
            $parts[] = $this->color;
        }

        return implode(' · ', $parts);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path !== null && $this->image_path !== ''
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }

    private function formatPower(string $value): string
    {
        $number = $value + 0;

        return ($number > 0 ? '+' : '').number_format($number, 2, '.', '');
    }

    protected static function newFactory()
    {
        return ProductFactory::new();
    }

    public function productAttributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class);
    }

    public function attributeValue(string $key): ?string
    {
        return $this->productAttributes->firstWhere('key', $key)?->value;
    }

    public function typeLabel(): string
    {
        return __('ui.products.types.'.$this->type);
    }
}
