<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Models\Concerns\IsProductDetail;
use Database\Factories\LensDetailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Detail lensa (A1 — persis Artesis lens_details). Kolom type
 * (SV/Progresif/Bifocal), index, variant, eye_side, sph/cyl/add
 * adalah bahan matriks stok lensa (modul A3).
 */
class LensDetail extends Model
{
    use HasFactory, IsProductDetail;

    public const DETAIL_TYPE = 'lens';

    public const KINDS = ['SV', 'Progresif', 'Bifocal'];

    protected $fillable = [
        'tenant_id', 'product_id', 'sku', 'barcode', 'is_active',
        'cost_price', 'sale_price', 'image_path',
        'type', 'index', 'variant', 'eye_side', 'sph', 'cyl', 'add',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_active' => 'boolean',
            'variant' => 'array',
            'sph' => 'decimal:2',
            'cyl' => 'decimal:2',
            'add' => 'decimal:2',
        ];
    }

    protected static function newFactory()
    {
        return LensDetailFactory::new();
    }
}
