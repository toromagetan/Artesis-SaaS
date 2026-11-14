<?php

namespace App\Modules\Comms\Models;

use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Konfigurasi gateway WhatsApp (F3.4). credentials adalah array
 * (phone_number_id, waba_id, access_token, verify_token, app_secret,
 * api_version)
 * yang disimpan TERENKRIPSI lewat cast `encrypted:array` — tidak
 * pernah tampil penuh di UI/log (UI hanya menunjukkan 4 digit akhir
 * access token).
 *
 * Kepemilikan per tenant (keputusan owner 2026-11-14, menutup OD-4):
 * baris dengan tenant_id = gateway milik tenant itu; tenant_id NULL
 * = gateway global instalasi (mode mandiri / milik platform).
 */
class WaGateway extends Model
{
    protected $fillable = [
        'tenant_id', 'provider', 'sender_number', 'credentials_encrypted', 'is_active',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Gateway aktif untuk satu ember kepemilikan: tenant hanya
     * memakai gateway miliknya (tanpa jatuh ke gateway tenant lain
     * atau gateway global); ember global (null) memakai baris
     * tenant_id NULL.
     */
    public static function activeForTenant(?int $tenantId): ?self
    {
        return self::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    /** Baris yang diatur satu ember (aktif atau tidak), untuk UI pengaturan. */
    public static function forTenant(?int $tenantId): ?self
    {
        return self::where('tenant_id', $tenantId)->orderBy('id')->first();
    }

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
