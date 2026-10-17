<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Identity\Models\User;

/**
 * Policy impor stok awal (Blueprint D.8 + PRD 7.5): gerbangnya
 * POLICY eksplisit — hanya peran tertinggi (Owner) dan BUKAN aktor
 * platform. String izin `stock.import_initial` saja TIDAK cukup:
 * pengguna yang diberi izin itu secara manual tetap ditolak bila
 * bukan Owner. Inilah pagar yang meniru keputusan produksi Artesis
 * (impor stok awal perdana = Superadmin/Owner saja).
 */
class InitialStockImportPolicy
{
    public function import(User $user): bool
    {
        return ! $user->is_platform
            && $user->is_active
            && $user->hasRole('Owner');
    }
}
