<?php

namespace App\Modules\Master\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pasangan kunci-nilai Pengaturan Umum (Blueprint B.1).
 * Nilai rahasia (kredensial gateway) TIDAK disimpan di sini.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];
}
