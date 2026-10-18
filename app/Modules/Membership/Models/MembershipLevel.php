<?php

namespace App\Modules\Membership\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Level membership (F3.6): ambang akumulasi belanja + pengali poin.
 * Pelanggan naik ke level tertinggi yang ambangnya terlampaui oleh
 * akumulasi belanja bersihnya (dievaluasi PointsService).
 */
class MembershipLevel extends Model
{
    protected $fillable = [
        'name', 'min_accumulation', 'point_multiplier', 'benefits', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
