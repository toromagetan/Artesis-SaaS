<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'qty_ordered',
        'qty_received', 'unit_cost', 'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty_ordered' => 'integer',
            'qty_received' => 'integer',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function remainingQty(): int
    {
        return $this->qty_ordered - $this->qty_received;
    }
}
