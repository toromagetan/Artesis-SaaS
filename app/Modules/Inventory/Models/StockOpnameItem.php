<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris lembar hitung opname: system_qty adalah snapshot saldo resmi
 * saat hitung dimulai; difference = counted_qty − system_qty
 * (dihitung ulang setiap penyimpanan hitungan).
 */
class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id', 'product_id', 'system_qty',
        'counted_qty', 'difference', 'movement_id',
    ];

    protected function casts(): array
    {
        return [
            'system_qty' => 'integer',
            'counted_qty' => 'integer',
            'difference' => 'integer',
        ];
    }

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StockOpname::class, 'stock_opname_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }
}
