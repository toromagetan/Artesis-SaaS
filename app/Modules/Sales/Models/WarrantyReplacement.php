<?php

namespace App\Modules\Sales\Models;

use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Rencana pengganti satu kasus garansi: sumber stok gudang atau
 * PO khusus; status pending → waiting_po/ready → shipped →
 * received. customer_charge = biaya yang dibebankan ke pelanggan.
 */
class WarrantyReplacement extends Model
{
    protected $fillable = [
        'warranty_case_id', 'original_sale_item_id', 'replacement_detail_id', 'replacement_detail_type',
        'source', 'quantity', 'original_value', 'replacement_value',
        'customer_charge', 'shipped_movement_id', 'purchase_order_id',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_value' => 'decimal:2', 'replacement_value' => 'decimal:2',
            'customer_charge' => 'decimal:2',
        ];
    }

    public function warrantyCase(): BelongsTo
    {
        return $this->belongsTo(WarrantyCase::class);
    }

    public function replacementDetail(): MorphTo
    {
        return $this->morphTo('replacementDetail', 'replacement_detail_type', 'replacement_detail_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
