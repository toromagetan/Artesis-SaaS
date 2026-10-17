<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baris item penjualan — nama & harga adalah SNAPSHOT saat transaksi
 * (Blueprint B.3): perubahan master sesudahnya tidak mengubah baris ini.
 */
class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'product_name_snapshot', 'type', 'qty',
        'unit_price', 'discount_amount', 'line_total', 'prescription_ref',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function returnedQty(): int
    {
        return (int) $this->returnItems()->sum('qty');
    }
}
