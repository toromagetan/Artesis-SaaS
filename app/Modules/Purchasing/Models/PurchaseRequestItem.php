<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Master\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'detail_id', 'detail_type', 'qty',
        'estimated_price', 'supplier_id', 'purchase_order_id',
        'rx_parameters',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'estimated_price' => 'decimal:2',
            'rx_parameters' => 'array',
        ];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class);
    }

    public function detail(): MorphTo
    {
        return $this->morphTo();
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
