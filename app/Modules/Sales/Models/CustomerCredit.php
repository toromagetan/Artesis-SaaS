<?php

namespace App\Modules\Sales\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Voucher kredit pelanggan (paritas Artesis CustomerCredit):
 * terbit dari retur penjualan bermetode kredit toko, bernomor
 * CR-YYYYMM-NNNN, memiliki sisa saldo dan status active/exhausted.
 * Pemakaian hanya lewat CustomerCreditService::apply (terkontrol).
 */
class CustomerCredit extends Model
{
    use BranchScoped;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXHAUSTED = 'exhausted';

    protected $fillable = [
        'credit_number', 'customer_id', 'sale_id', 'sale_return_id', 'branch_id',
        'amount', 'remaining_amount', 'status', 'reason', 'issued_at',
        'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CustomerCreditApplication::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
