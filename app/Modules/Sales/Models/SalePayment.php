<?php

namespace App\Modules\Sales\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pembayaran penjualan — APPEND-ONLY (Blueprint B.3). Koreksi tidak
 * pernah mengubah baris lama: dibuat baris pembalik bertanda negatif
 * dengan reversal_of_id menunjuk baris asal.
 */
class SalePayment extends Model
{
    public const METHODS = ['cash', 'debit', 'qris', 'transfer', 'card', 'points'];

    protected $fillable = [
        'sale_id', 'method', 'amount', 'paid_at', 'reference',
        'received_by', 'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'reversal_of_id');
    }

    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }
}
