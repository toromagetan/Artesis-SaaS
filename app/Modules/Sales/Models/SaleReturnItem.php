<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris retur penjualan: qty, keadaan barang (resale/damaged),
 * nilai prorata dari baris penjualan asal.
 */
class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id', 'sale_item_id', 'qty', 'condition', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
