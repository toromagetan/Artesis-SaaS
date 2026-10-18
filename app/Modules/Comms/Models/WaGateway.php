<?php

namespace App\Modules\Comms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi gateway WhatsApp (F3.4). credentials adalah array
 * (phone_number_id, waba_id, access_token, verify_token, api_version)
 * yang disimpan TERENKRIPSI lewat cast `encrypted:array` — tidak
 * pernah tampil penuh di UI/log (UI hanya menunjukkan 4 digit akhir
 * access token).
 */
class WaGateway extends Model
{
    protected $fillable = [
        'provider', 'sender_number', 'credentials_encrypted', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    /** @return array<string, string> */
    public function credentials(): array
    {
        return $this->credentials_encrypted ?? [];
    }

    public function credential(string $key, string $default = ''): string
    {
        return (string) ($this->credentials()[$key] ?? $default);
    }

    /** Masker aman untuk UI: •••• + 4 karakter terakhir token. */
    public function maskedToken(): string
    {
        $token = $this->credential('access_token');

        return $token === '' ? '' : '••••'.substr($token, -4);
    }
}
