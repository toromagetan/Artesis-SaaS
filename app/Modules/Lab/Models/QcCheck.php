<?php

namespace App\Modules\Lab\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu percobaan pemeriksaan QC (F2.2). Append-only secara kontrak:
 * hasil baru selalu baris baru dengan attempt berikutnya.
 */
class QcCheck extends Model
{
    protected $fillable = [
        'lens_process_id',
        'qc_point_template_id',
        'attempt',
        'result',
        'notes',
        'checked_by',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(LensProcess::class, 'lens_process_id');
    }

    public function point(): BelongsTo
    {
        return $this->belongsTo(QcPointTemplate::class, 'qc_point_template_id');
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
