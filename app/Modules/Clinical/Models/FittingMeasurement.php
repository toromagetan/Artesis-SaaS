<?php

namespace App\Modules\Clinical\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengukuran fitting (F5.2) — angka saja, tanpa foto. Nilai
 * kamera bersifat pendamping; pd_reference menampung hasil
 * pupilometer pembanding bila staf mengambilnya.
 */
class FittingMeasurement extends Model
{
    protected $fillable = [
        'customer_id', 'prescription_id', 'branch_id', 'measured_by', 'method',
        'pd_binocular', 'pd_mono_od', 'pd_mono_os',
        'pupil_height_od', 'pupil_height_os', 'face_width',
        'pd_reference', 'frame_size_recommendation', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'pd_binocular' => 'decimal:1', 'pd_mono_od' => 'decimal:1', 'pd_mono_os' => 'decimal:1',
            'pupil_height_od' => 'decimal:1', 'pupil_height_os' => 'decimal:1',
            'face_width' => 'decimal:1', 'pd_reference' => 'decimal:1',
        ];
    }

    /** Kategori ukuran frame dari lebar wajah (mm): S/M/L. */
    public static function sizeForFaceWidth(float $faceWidth): string
    {
        return match (true) {
            $faceWidth <= 132 => 'S',
            $faceWidth <= 141 => 'M',
            default => 'L',
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function measurer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'measured_by');
    }
}
