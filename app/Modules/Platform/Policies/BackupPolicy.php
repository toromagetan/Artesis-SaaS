<?php

namespace App\Modules\Platform\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;

/**
 * Otorisasi backup & restore DATABASE GLOBAL (R-1, menutup temuan
 * audit P0-1). Backup aplikasi ini adalah snapshot SELURUH database
 * satu instalasi — pada instansi SaaS bersama, snapshot itu memuat
 * data semua tenant, sehingga aksesnya BUKAN hak tenant.
 *
 * Aturan mode (dievaluasi sesudah gerbang izin `backups.manage`
 * pada rute; policy ini adalah lapis mode di atas lapis izin):
 *
 * 1. Aktor PLATFORM (`is_platform`) — diizinkan: backup tingkat
 *    instalasi memang operasi platform.
 * 2. Aktor TENANT (`tenant_id` terisi) — DITOLAK selalu, tanpa
 *    memandang peran/izin yang dipegangnya (termasuk Owner tenant
 *    yang memegang seluruh izin bisnis).
 * 3. Aktor INDIVIDUAL (`tenant_id` NULL, bukan platform) — diizinkan
 *    HANYA bila instalasi benar-benar individual, dibuktikan dengan
 *    tidak adanya satu pun baris tenant. Begitu sebuah instansi
 *    melayani tenant, akun tanpa tenant yang bukan platform TIDAK
 *    lagi dianggap operator instalasi — kondisi NULL tidak boleh
 *    menjadi jalan masuk bagi akun yang seharusnya akun tenant.
 *
 * Restore lebih ketat: selain lolos aturan mode di atas, aktor
 * individual wajib berperan Owner (perilaku lama); aktor platform
 * lolos lewat identitas platformnya.
 */
class BackupPolicy
{
    /** Akses umum: daftar, buat, unduh, hapus, sakelar offsite. */
    public function access(User $user): bool
    {
        if ($user->tenant_id !== null) {
            return false;
        }

        if ($user->is_platform) {
            return true;
        }

        return Tenant::count() === 0;
    }

    /** Restore database global — operasi paling berisiko. */
    public function restore(User $user): bool
    {
        if (! $this->access($user)) {
            return false;
        }

        return $user->is_platform || $user->hasRole('Owner');
    }
}
