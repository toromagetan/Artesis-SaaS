<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris fitur/batas pada sebuah paket (M3). Fitur boolean memakai
 * kolom enabled; batas angka memakai limit_value (null = tanpa batas).
 */
class PlanFeature extends Model
{
    protected $fillable = ['plan_id', 'feature_key', 'enabled', 'limit_value'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'limit_value' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
