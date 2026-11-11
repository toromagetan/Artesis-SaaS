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
 * Aturan:
 * 1. Aktor PLATFORM — diizinkan untuk tenant mana pun (atas nama).
 * 2. Aktor TENANT VALID — diizinkan HANYA untuk tenantnya sendiri
 *    dan HANYA bila berperan Owner. Peran lain (Admin/Kasir/dsb),
 *    tenant lain, akun menggantung, unassigned, dan standalone
 *    selalu ditolak.
 */
class TenantExportPolicy
{
    public function export(User $user, Tenant $tenant): bool
    {
        if ($user->is_platform) {
            return true;
        }

        $context = TenantContext::for($user);
        if (! $context->isValid() || $context->tenantId() !== (int) $tenant->id) {
            return false;
        }

        return $user->hasRole('Owner');
    }
}
