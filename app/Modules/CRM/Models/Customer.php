<?php

namespace App\Modules\CRM\Models;

use App\Modules\Clinical\Models\FittingMeasurement;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Sales\Models\CustomerCredit;
use App\Support\Concerns\TenantScoped;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pelanggan (Blueprint B.2 + D.4 + ADR-012). Master berlaku di
 * semua cabang DALAM SATU TENANT — terlihat dan dapat dilayani
 * di cabang mana pun milik tenant yang sama (pagarnya izin
 * customers.*, bukan cabang). Antar-tenant terisolasi penuh
 * lewat TenantScope (S-02/keputusan OD-2). Kolom branch_id
 * adalah atribut asal (cabang pendaftaran), bukan pagar.
 * Tidak pernah dihapus — hanya dinonaktifkan.
 */
class Customer extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'name', 'phone', 'email', 'address', 'birth_date',
        'branch_id', 'tenant_id', 'insurance_id', 'membership_level_id', 'is_active', 'wa_opt_out',
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

    /** Voucher kredit toko milik pelanggan (paritas Artesis), terbaru dulu. */
    public function credits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class)->latest('issued_at');
    }
}
