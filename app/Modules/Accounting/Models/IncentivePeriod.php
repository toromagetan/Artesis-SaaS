<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Periode insentif per penerima per cabang: draft (dihasilkan
 * dari aturan) → approved (jurnal Dr Beban Insentif / Cr Utang
 * Insentif) → paid (Dr Utang Insentif / Cr Kas/Bank).
 */
class IncentivePeriod extends Model
{
    protected $fillable = [
        'period_start', 'period_end', 'branch_id', 'recipient_user_id',
        'status', 'transaction_total', 'incentive_total', 'generated_by',
        'approved_by', 'approved_at', 'paid_by', 'paid_at', 'pay_method',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'transaction_total' => 'decimal:2',
            'incentive_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IncentiveItem::class, 'incentive_period_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(IncentiveAdjustment::class, 'incentive_period_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
