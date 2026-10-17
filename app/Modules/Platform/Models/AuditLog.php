<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak audit (Blueprint B. Platform). Hanya INSERT dari aplikasi;
 * tidak ada updated_at. Penulisan kanonis lewat AuditLogger (F0.4).
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'branch_id',
        'old_values',
        'new_values',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
