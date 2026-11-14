<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Concerns\IsProductDetail;
use Database\Factories\FrameDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Detail frame (A1 — persis Artesis frame_details). */
class FrameDetail extends Model
{
    use HasFactory, IsProductDetail;

    public const DETAIL_TYPE = 'frame';

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode', 'is_active',
        'cost_price', 'sale_price', 'image_path', 'color',
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
        return FrameDetailFactory::new();
    }
}
