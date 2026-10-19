<?php

namespace App\Modules\Sales\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerapan voucher kredit pelanggan ke satu penjualan
 * (paritas Artesis CustomerCreditApplication). Sepasang
 * (kredit, penjualan) hanya boleh memiliki satu penerapan —
 * ditegakkan unique key + pemeriksaan di CustomerCreditService.
 */
class CustomerCreditApplication extends Model
{
    protected $fillable = [
        'customer_credit_id', 'sale_id', 'amount', 'applied_at', 'applied_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'applied_at' => 'datetime',
        ];
    }

    public function credit(): BelongsTo
    {
        return $this->belongsTo(CustomerCredit::class, 'customer_credit_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }
}
