<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan naik paket berbayar (M5): dibuat admin tenant, dibayar
 * transfer manual, diputuskan aktor platform (konfirmasi → paket
 * ditetapkan lewat PlanService; tolak → paket tidak berubah).
 */
class TenantBillingRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'plan_id', 'extra_branches', 'months', 'amount',
        'status', 'note', 'decided_by', 'decided_at', 'subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'extra_branches' => 'integer',
            'months' => 'integer',
            'amount' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class, 'subscription_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
