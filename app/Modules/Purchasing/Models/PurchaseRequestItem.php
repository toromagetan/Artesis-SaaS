<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Master\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'product_id', 'qty',
        'estimated_price', 'supplier_id', 'purchase_order_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'estimated_price' => 'decimal:2',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
