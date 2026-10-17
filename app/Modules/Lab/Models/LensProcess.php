<?php

namespace App\Modules\Lab\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proses lensa (Blueprint B.4). Cabang diwarisi dari penjualan induk —
 * tabel ini sengaja tanpa branch_id; pagar cabang ditegakkan lewat
 * relasi sale (Sale memakai BranchScope).
 */
class LensProcess extends Model
{
    public const STATUSES = ['waiting', 'edging', 'qc', 'ready', 'done'];

    protected $fillable = [
        'sale_id', 'status', 'technician_id', 'estimated_days', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'estimated_days' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    /**
     * Durasi proses dalam hari kalender penuh (Blueprint B.4):
     * (completed_at ?? now) − tanggal transaksi. Sesudah done, nilai
     * MEMBEKU karena completed_at tidak pernah ditulis ulang.
     */
    public function durationDays(): int
    {
        $end = ($this->completed_at ?? now())->copy()->startOfDay();
        $start = $this->sale->sale_date->copy()->startOfDay();

        return (int) (($end->timestamp - $start->timestamp) / 86400);
    }
}
