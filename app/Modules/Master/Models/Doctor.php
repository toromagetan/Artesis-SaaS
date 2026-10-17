<?php

namespace App\Modules\Master\Models;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $fillable = ['name', 'clinic', 'phone', 'commission_rate', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2', // persen
        ];
    }
}
