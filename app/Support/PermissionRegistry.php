<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Registry izin kanonis (Blueprint A.4 & C.1).
 * Membaca config/permissions.php. String izin di luar registry = bug:
 * kode yang memeriksa/menanam izin WAJIB lewat kelas ini.
 */
class PermissionRegistry
{
    /** @return array<string, array{label: string, permissions: array<string, string>}> */
    public static function groups(): array
    {
        return config('permissions', []);
    }

    /** @return string[] seluruh string izin kanonis */
    public static function all(): array
    {
        $all = [];
        foreach (self::groups() as $group) {
            $all = array_merge($all, array_keys($group['permissions']));
        }

        return $all;
    }

    public static function exists(string $permission): bool
    {
        return in_array($permission, self::all(), true);
    }

    /**
     * Lempar bila string tidak terdaftar — dipakai di titik-titik kritis
     * (seeder, middleware, policy) agar string liar gagal keras, bukan diam.
     */
    public static function assertExists(string $permission): void
    {
        if (! self::exists($permission)) {
            throw new InvalidArgumentException("Izin tidak terdaftar di registry: {$permission}");
        }
    }

    public static function label(string $permission): ?string
    {
        foreach (self::groups() as $group) {
            if (isset($group['permissions'][$permission])) {
                return $group['permissions'][$permission];
            }
        }

        return null;
    }

    public static function groupOf(string $permission): ?string
    {
        foreach (self::groups() as $key => $group) {
            if (isset($group['permissions'][$permission])) {
                return $key;
            }
        }

        return null;
    }
}
