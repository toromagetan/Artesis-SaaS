<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Atribut per tipe produk (Blueprint B.2) — pasangan key/value;
 * himpunan key yang sah per tipe dikunci di ProductService.
 */
class ProductAttribute extends Model
{
    protected $fillable = ['product_id', 'key', 'value'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
