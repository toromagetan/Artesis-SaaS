<?php

namespace App\Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pembayaran biaya garansi oleh pelanggan (per kasus). */
class WarrantyPayment extends Model
{
    protected $fillable = ['warranty_case_id', 'method', 'amount', 'payment_date', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date'];
    }

    public function warrantyCase(): BelongsTo
    {
        return $this->belongsTo(WarrantyCase::class);
    }
}
