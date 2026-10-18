<?php

namespace App\Modules\Lab\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Titik QC faset yang dapat dikonfigurasi (F2.1, Blueprint B.6).
 * Titik nonaktif tidak ikut diperiksa dan tidak menggerbangi Complete.
 */
class QcPointTemplate extends Model
{
    protected $fillable = [
        'name',
        'is_required',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Titik aktif terurut untuk daftar periksa. */
    public function scopeActiveOrdered(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
