<?php

namespace App\Modules\CRM\Models;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
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
        'branch_id', 'insurance_id', 'membership_level_id', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
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

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class)->orderByDesc('examined_at')->orderByDesc('id');
    }
}
