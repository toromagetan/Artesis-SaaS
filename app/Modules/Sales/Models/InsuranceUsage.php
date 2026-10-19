<?php

namespace App\Modules\Sales\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Histori pemakaian asuransi pada penjualan (paritas Artesis).
 * Bukan sumber kebenaran finansial — kebenaran tetap di
 * sales/payments/akuntansi; amount adalah snapshot saat transaksi.
 */
class InsuranceUsage extends Model
{
    protected $fillable = [
        'sale_id', 'insurance_id', 'customer_id', 'branch_id',
        'used_at', 'transaction_amount', 'insurance_claim_amount', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'transaction_amount' => 'decimal:2',
            'insurance_claim_amount' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(InsuranceClaimReceipt::class, 'insurance_usage_id');
    }

    /** Klaim yang sudah diterima dari perusahaan asuransi. */
    public function settledAmount(): string
    {
        return (string) $this->receipts()->sum('amount');
    }

    /** Sisa klaim yang masih ditagihkan ke perusahaan asuransi. */
    public function remainingAmount(): string
    {
        $remaining = Money::fromDecimal((string) $this->insurance_claim_amount)
            ->sub(Money::fromDecimal($this->settledAmount()));

        return $remaining->isNegative() ? '0.00' : $remaining->toDecimalString();
    }
}
