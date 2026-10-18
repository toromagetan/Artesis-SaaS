<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReceiptItem extends Model
{
    protected $fillable = ['receipt_id', 'purchase_order_item_id', 'qty', 'unit_cost_actual', 'expiry_date'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost_actual' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function returnItems(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function returnedQty(): int
    {
        return (int) $this->returnItems()->sum('qty');
    }
}
