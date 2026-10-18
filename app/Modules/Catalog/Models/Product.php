<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produk katalog (Blueprint B.2). Lima tipe terkunci:
 * frame, lens, rx, softlens, accessory.
 */
class Product extends Model
{
    use HasFactory;

    public const TYPES = ['frame', 'lens', 'rx', 'softlens', 'accessory'];

    protected $fillable = [
        'sku', 'barcode', 'type', 'name', 'brand', 'color',
        'cost_price', 'sale_price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
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
