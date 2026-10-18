<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Opname terjadwal per cabang (Blueprint B.4, F4.1).
 * Alur: scheduled → counting → review → approved;
 * counting → cancelled hanya oleh Owner.
 */
class StockOpname extends Model
{
    use BranchScoped;

    public const STATUSES = ['scheduled', 'counting', 'review', 'approved', 'cancelled'];

    /** Status aktif yang mengunci mutasi stok cabang (PRD 7.4). */
    public const LOCKING_STATUSES = ['counting', 'review'];

    protected $fillable = [
        'branch_id', 'scheduled_date', 'status', 'created_by',
        'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    public function isLocking(): bool
    {
        return in_array($this->status, self::LOCKING_STATUSES, true);
    }
}
