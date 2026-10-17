<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alokasi pembayaran ke receipt (selalu ke receipt, bukan PO — B.4).
 * Σ alokasi per pembayaran WAJIB = purchase_payments.amount
 * (ditegakkan service + uji rekonsiliasi G.1.3).
 */
class PurchasePaymentAllocation extends Model
{
    protected $fillable = ['purchase_payment_id', 'receipt_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'purchase_payment_id');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }
}
