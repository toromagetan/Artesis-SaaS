<?php

namespace App\Modules\CRM\Models;

use App\Modules\Clinical\Models\FittingMeasurement;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Membership\Models\MembershipLevel;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pelanggan (Blueprint B.2 + D.4 + ADR-012). MASTER GLOBAL:
 * pelanggan berlaku di semua cabang — terlihat dan dapat dilayani
 * di cabang mana pun (pagarnya izin customers.*, bukan cabang).
 * Kolom branch_id adalah atribut asal (cabang pendaftaran), bukan
 * pagar. Tidak pernah dihapus — hanya dinonaktifkan.
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'birth_date',
        'branch_id', 'insurance_id', 'credit_limit', 'membership_level_id', 'is_active', 'wa_opt_out',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
            'wa_opt_out' => 'boolean',
        ];
    }

    protected static function newFactory()
    {
        return CustomerFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Level membership berjalan (F3.6); null = level aktif terendah. */
    public function level(): BelongsTo
    {
        return $this->belongsTo(MembershipLevel::class, 'membership_level_id');
    }

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderByDesc('examined_at')->orderByDesc('id');
    }

    public function fittingMeasurements(): HasMany
    {
        return $this->hasMany(FittingMeasurement::class);
    }
}
