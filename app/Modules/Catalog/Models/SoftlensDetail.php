<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Concerns\IsProductDetail;
use Database\Factories\SoftlensDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Detail softlens (A1 — persis Artesis softlens_details +
 * penanda kedaluwarsa §193; tanggalnya per batch di
 * receipt_items.expiry_date, dibaca lapisan FIFO).
 */
class SoftlensDetail extends Model
{
    use HasFactory, IsProductDetail;

    public const DETAIL_TYPE = 'softlens';

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode', 'is_active',
        'cost_price', 'sale_price', 'image_path',
        'color', 'power', 'expiry_tracked',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'power' => 'decimal:2',
            'expiry_tracked' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return SoftlensDetailFactory::new();
    }
}
