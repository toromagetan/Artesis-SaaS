<?php

namespace App\Modules\Master\Models;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
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
 * platform dan instalasi mandiri memakai baris global (milik
 * mereka sendiri). Aktor unassigned dan request tamu ditolak
 * menulis; tulisan tanpa pengguna hanya sah dalam konteks
 * sistem eksplisit TenantContext::asSystem (audit lanjutan
 * temuan 9 + koreksi audit PR #33).
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

    /** Akses baca kanonis satu nilai pengaturan (mengikuti aktor). */
    public static function value(string $key, ?string $default = null): ?string
    {
        return static::valueForTenant($key, static::actorTenantId(), $default);
    }

    /**
     * Akses baca untuk ember tenant yang dinyatakan EKSPLISIT —
     * untuk jalur tanpa sesi aktor (antrean/konsol) yang mengetahui
     * tenant sumber datanya, misalnya tarif komisi dari penjualan
     * sumber (audit lanjutan temuan 9). Resolusi: penimpaan milik
     * tenant itu dulu; bila belum ada, jatuh ke nilai global yang
     * berperan sebagai default sistem (hanya dapat diubah platform
     * atau konteks sistem — lihat set()).
     */
    public static function valueForTenant(string $key, ?int $tenantId, ?string $default = null): ?string
    {
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
            ['key' => $key, 'tenant_id' => static::actorBucketForWrite()],
            ['value' => $value],
        );
    }

    /**
     * Ember tujuan tulisan, ditegakkan gagal-tertutup (audit lanjutan
     * temuan 9): aktor tenant menulis baris milik tenantnya sendiri;
     * platform & standalone menulis baris global (milik instalasi/
     * platform itu sendiri). Unassigned, tamu, dan tenant menggantung
     * DITOLAK — sebelumnya mereka jatuh ke actorTenantId() NULL dan
     * diam-diam mengubah pengaturan global lewat jalur HTTP yang
     * sah. Tanpa aktor: tulisan global HANYA sah dalam konteks
     * sistem eksplisit (TenantContext::asSystem — konsol terjadwal,
     * antrean, seeder); request tamu dan pemanggilan tanpa aktor
     * lain ditolak (koreksi audit PR #33: penanda sistem harus
     * benar-benar menjadi gerbang, bukan sekadar tersedia). Seluruh
     * pemanggil produksi berada di balik autentikasi atau proses
     * sistem yang sudah dibungkus asSystem, jadi tidak ada alur
     * sah yang terputus.
     */
    private static function actorBucketForWrite(): ?int
    {
        $user = auth()->user();
        if ($user === null) {
            if (TenantContext::isSystemContext() && ! TenantContext::isGuestHttp()) {
                return null;
            }

            throw new AuthorizationException('Pengaturan global hanya dapat diubah oleh platform atau dalam konteks sistem.');
        }

        $context = TenantContext::for($user);

        return match ($context->mode()) {
            TenantContext::MODE_TENANT => $context->tenant()?->id
                ?? throw new AuthorizationException('Tenant aktor tidak valid.'),
            TenantContext::MODE_PLATFORM, TenantContext::MODE_STANDALONE => null,
            default => throw new AuthorizationException('Aktor tidak berhak mengubah pengaturan.'),
        };
    }
}
