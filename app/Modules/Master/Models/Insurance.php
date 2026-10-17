<?php

namespace App\Modules\Master\Models;

use Illuminate\Database\Eloquent\Model;

class Insurance extends Model
{
    protected $fillable = ['name', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
