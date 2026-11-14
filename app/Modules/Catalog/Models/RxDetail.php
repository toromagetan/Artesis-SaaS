<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Concerns\IsProductDetail;
use Database\Factories\RxDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Detail lensa RX (A1 — persis Artesis rx_details). */
class RxDetail extends Model
{
    use HasFactory, IsProductDetail;

    public const DETAIL_TYPE = 'rx';

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode', 'is_active',
        'cost_price', 'sale_price', 'image_path',
        'type', 'index', 'variant',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'variant' => 'array',
        ];
    }

    protected static function newFactory()
    {
        return RxDetailFactory::new();
    }
}
