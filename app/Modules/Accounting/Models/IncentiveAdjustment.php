<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penyesuaian manual ber-alasan atas periode insentif (hanya draft). */
class IncentiveAdjustment extends Model
{
    protected $fillable = [
        'incentive_period_id', 'amount', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IncentivePeriod::class, 'incentive_period_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
