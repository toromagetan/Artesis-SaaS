<?php

namespace App\Modules\Platform\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;

/**
 * Otorisasi ekspor data per tenant (B-03). Berbeda dari
 * BackupPolicy: snapshot seluruh instalasi adalah hak platform,
 * sedangkan salinan data SATU tenant adalah hak tenant itu
 * sendiri — lewat pemiliknya — dan platform atas namanya.
 *
 * Aturan (sesudah koreksi audit independen putaran 1):
 * 0. Akun NONAKTIF selalu ditolak, aktor apa pun — konsisten
 *    dengan User::hasPermission() yang menolak pengguna nonaktif.
 * 1. Aktor PLATFORM — diizinkan untuk tenant mana pun HANYA bila
 *    memegang izin platform `backups.manage` (izin yang sama
 *    menggerbangi backup tingkat instalasi; ekspor adalah operasi
 *    penjaga data, bukan sekadar status is_platform).
 * 2. Aktor TENANT VALID — diizinkan HANYA untuk tenantnya sendiri
 *    dan HANYA bila berperan Owner. Peran lain, tenant lain, akun
 *    menggantung, unassigned, dan standalone selalu ditolak.
 */
class TenantExportPolicy
{
    /**
     * Halaman pemilih tenant untuk aktor platform (belum ada
     * tenant sasaran, jadi export() tidak dapat dipakai): hanya
     * platform aktif berizin backups.manage yang boleh melihat
     * daftar seluruh tenant.
     */
    public function listTenants(User $user): bool
    {
        return $user->is_active && $user->is_platform && $user->hasPermission('backups.manage');
    }

    public function export(User $user, Tenant $tenant): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->is_platform) {
            return $user->hasPermission('backups.manage');
        }

        $context = TenantContext::for($user);
        if (! $context->isValid() || $context->tenantId() !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRole('Owner');
    }
}
