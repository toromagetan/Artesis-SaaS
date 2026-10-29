<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengecualian fitur per tenant (M3): owner platform dapat membuka/
 * mengunci satu fitur atau mengubah satu batas angka untuk tenant
 * tertentu, berbatas waktu, dengan alasan tertulis (diaudit).
 * Override yang sudah kedaluwarsa tidak berlaku.
 */
class TenantFeatureOverride extends Model
{
    protected $fillable = [
        'tenant_id', 'feature_key', 'enabled', 'limit_value', 'expires_at', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Hanya override yang masih berlaku (tanpa batas / belum lewat). */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
