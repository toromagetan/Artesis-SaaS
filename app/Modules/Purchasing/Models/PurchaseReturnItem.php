<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReturnItem extends Model
{
    protected $fillable = ['purchase_return_id', 'receipt_item_id', 'qty', 'amount'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class, 'purchase_return_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ReceiptItem::class);
    }
}
