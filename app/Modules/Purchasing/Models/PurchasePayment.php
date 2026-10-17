<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePayment extends Model
{
    public const METHODS = ['transfer', 'cash', 'giro', 'other'];

    protected $fillable = [
        'number', 'supplier_id', 'method', 'amount',
        'payment_date', 'reference', 'paid_by', 'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }
}
