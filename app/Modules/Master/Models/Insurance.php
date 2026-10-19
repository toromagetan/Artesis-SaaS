<?php

namespace App\Modules\Master\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Master asuransi (paritas Artesis): nama + claim_value (nilai
 * klaim yang ditanggung per asuransi, menjadi bawaan isian klaim
 * di penjualan) + status aktif. Tidak pernah dihapus — hanya
 * dinonaktifkan.
 */
class Insurance extends Model
{
    protected $fillable = ['name', 'claim_value', 'is_active'];

    protected function casts(): array
    {
        return [
            'claim_value' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
