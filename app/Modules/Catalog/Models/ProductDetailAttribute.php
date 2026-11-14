<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Atribut bebas sebuah detail (A1): coating, diameter,
 * wear_period, rentang RX, ukuran frame — atribut yang di
 * Artesis bukan kolom detail. Berkunci morph (detail_id +
 * detail_type) mengikuti pola rujukan detail.
 */
class ProductDetailAttribute extends Model
{
    protected $fillable = ['detail_id', 'detail_type', 'key', 'value'];

    public function detail(): MorphTo
    {
        return $this->morphTo();
    }
}
