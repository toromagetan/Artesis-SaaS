<?php

namespace App\Modules\Membership\Models;

use App\Support\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * Level membership (F3.6): ambang akumulasi belanja + pengali poin.
 * Pelanggan naik ke level tertinggi yang ambangnya terlampaui oleh
 * akumulasi belanja bersihnya (dievaluasi PointsService).
 * Level adalah program milik tenant (Modul C — pola S-02):
 * ambang & pengali satu tenant tidak mengubah program tenant lain.
 */
class MembershipLevel extends Model
{
    use TenantScoped;

    protected $fillable = [
        'name', 'min_accumulation', 'point_multiplier', 'benefits', 'is_active', 'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
