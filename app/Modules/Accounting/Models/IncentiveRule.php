<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Support\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Aturan insentif berkala (paritas Artesis): peran penerima,
 * produk opsional, persen dari basis penjualan atau flat per
 * unit/transaksi, masa berlaku.
 */
class IncentiveRule extends Model
{
    use TenantScoped;

    protected $fillable = [
        'name', 'recipient_role', 'detail_id', 'detail_type', 'calculation_type',
        'value', 'effective_from', 'effective_to', 'is_active', 'created_by',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function detail(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
