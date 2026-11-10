<?php

namespace App\Modules\Master\Models;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Sales\Models\Sale;
use App\Support\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokter perujuk adalah master milik tenant (Modul C — pola
 * S-02): tiap optik merawat daftar dokternya sendiri; tarif
 * komisi dokter satu tenant tidak boleh terlihat/diubah tenant
 * lain. Baris tenant_id NULL = warisan instalasi mandiri.
 */
class Doctor extends Model
{
    use TenantScoped;

    protected $fillable = ['name', 'clinic', 'phone', 'commission_rate', 'is_active', 'tenant_id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2', // persen
        ];
    }

    /** Penjualan yang merujuk dokter ini (rincian di halaman master). */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'doctor_id');
    }

    /** Baris komisi yang terakru untuk dokter ini. */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'beneficiary_doctor_id');
    }
}
