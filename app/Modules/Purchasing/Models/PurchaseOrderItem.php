<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'detail_id', 'detail_type', 'qty_ordered',
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

    public function detail(): MorphTo
    {
        return $this->morphTo();
    }

    public function remainingQty(): int
    {
        return $this->qty_ordered - $this->qty_received;
    }
}
