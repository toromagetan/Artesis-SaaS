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

    /** Akses baca kanonis satu nilai pengaturan. */
    public static function value(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    /** Akses tulis kanonis satu nilai pengaturan. */
    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
