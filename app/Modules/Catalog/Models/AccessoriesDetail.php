<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Concerns\IsProductDetail;
use Database\Factories\AccessoriesDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Detail aksesoris (A1 — persis Artesis accessories_details +
 * is_process_item khas SaaS dan penanda kedaluwarsa §193 untuk
 * cairan softlens).
 */
class AccessoriesDetail extends Model
{
    use HasFactory, IsProductDetail;

    public const DETAIL_TYPE = 'accessory';

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode', 'is_active',
        'cost_price', 'sale_price', 'image_path',
        'variant', 'is_process_item', 'expiry_tracked',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'is_process_item' => 'boolean',
            'expiry_tracked' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return AccessoriesDetailFactory::new();
    }
}
