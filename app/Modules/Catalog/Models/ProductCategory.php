<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori produk (paritas Artesis, lanjutan ADR-015).
 * Setiap kategori terpetakan ke tepat satu tipe sistem
 * (frame/lens/rx/softlens/accessory) yang menjadi kunci perilaku.
 */
class ProductCategory extends Model
{
    protected $fillable = ['name', 'description', 'type', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function parents(): HasMany
    {
        return $this->hasMany(ProductParent::class, 'category_id');
    }

    public function typeLabel(): string
    {
        return __('ui.products.types.'.$this->type);
    }
}
