<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi Asisten AI (F5.1). API key disimpan TERENKRIPSI
 * lewat cast `encrypted:array`; UI hanya menampilkan masker
 * 4 karakter terakhir. Baris tunggal — gunakan ::current().
 */
class AiSetting extends Model
{
    protected $fillable = [
        'base_url', 'model', 'credentials_encrypted', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return self::firstOrCreate([], ['base_url' => 'https://api.openai.com/v1', 'model' => 'gpt-4o-mini']);
    }

    public function apiKey(): string
    {
        return (string) (($this->credentials_encrypted ?? [])['api_key'] ?? '');
    }

    public function maskedKey(): string
    {
        $key = $this->apiKey();

        return $key === '' ? '' : '••••'.substr($key, -4);
    }

    public function isConfigured(): bool
    {
        return $this->is_active && $this->apiKey() !== '' && $this->base_url !== '' && $this->model !== '';
    }
}
