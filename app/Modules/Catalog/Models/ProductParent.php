<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Induk produk (ADR-015) — tingkat atas katalog dua tingkat,
 * menyamai Product induk pada Artesis. Baris products adalah
 * varian di bawah induk ini.
 */
class ProductParent extends Model
{
    protected $fillable = ['name', 'brand', 'category_id', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    public function typeLabel(): string
    {
        return __('ui.products.types.'.$this->type);
    }
}
