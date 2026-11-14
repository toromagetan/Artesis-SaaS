<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Baris item penjualan (A1: menunjuk DETAIL produk secara morph) —
 * nama & harga adalah SNAPSHOT saat transaksi (Blueprint B.3):
 * perubahan master sesudahnya tidak mengubah baris ini.
 */
class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'detail_id', 'detail_type', 'product_name_snapshot', 'type', 'qty',
        'unit_price', 'discount_amount', 'line_total', 'prescription_ref',
        'rx_parameters',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'rx_parameters' => 'array',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function detail(): MorphTo
    {
        return $this->morphTo();
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
