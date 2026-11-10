<?php

namespace App\Modules\Master\Models;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Pasangan kunci-nilai Pengaturan Umum (Blueprint B.1).
 * Nilai rahasia (kredensial gateway) TIDAK disimpan di sini.
 *
 * Kepemilikan (S-03): baris tenant_id NULL = nilai global
 * (instalasi/platform); baris bertenant = penimpaan milik tenant
 * itu. Resolusi mengikuti aktor yang sedang berjalan: aktor
 * tenant membaca penimpaannya dulu lalu jatuh ke nilai global;
 * tulisan aktor tenant menjadi baris milik tenantnya — satu
 * tenant tidak lagi mengubah pengaturan tenant lain. Aktor
 * platform, instalasi mandiri, dan konteks tanpa pengguna
 * (seeder/CLI/antrean) selalu memakai baris global.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'tenant_id'];

    /** Tenant milik aktor saat ini; null di luar konteks tenant. */
    private static function actorTenantId(): ?int
    {
        $user = auth()->user();
        if ($user === null) {
            return null;
        }

        $context = TenantContext::for($user);

        return $context->mode() === TenantContext::MODE_TENANT ? $context->tenant()?->id : null;
    }

    /** Akses baca kanonis satu nilai pengaturan. */
    public static function value(string $key, ?string $default = null): ?string
    {
        $tenantId = static::actorTenantId();
        if ($tenantId !== null) {
            $own = static::query()->where('key', $key)->where('tenant_id', $tenantId)->value('value');
            if ($own !== null) {
                return $own;
            }
        }

        return static::query()->where('key', $key)->whereNull('tenant_id')->value('value') ?? $default;
    }

    /** Akses tulis kanonis satu nilai pengaturan. */
    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key, 'tenant_id' => static::actorTenantId()],
            ['value' => $value],
        );
    }
}
