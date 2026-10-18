<?php

namespace App\Modules\Sales\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfflineDevice extends Model
{
    protected $fillable = [
        'name', 'branch_id', 'user_id', 'token_hash',
        'last_sync_at', 'revoked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_sync_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(OfflineException::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
