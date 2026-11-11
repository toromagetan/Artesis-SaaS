<?php

namespace App\Support\Scopes;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Pagar tenant di level query (S-02, pasangan BranchScope).
 * Klasifikasi aktor memakai TenantContext:
 * - request HTTP tamu (penanda TenantContext::isGuestHttp dari
 *   middleware MarkGuestRequest): hasil kosong — gagal-tutup.
 *   Guest HTTP tidak boleh membaca data tenant hanya karena tidak
 *   ada yang login (audit lanjutan temuan 8);
 * - tanpa aktor di luar request HTTP: tidak membatasi — layanan
 *   aplikasi menerima aktor secara eksplisit sebagai parameter dan
 *   memvalidasi embernya sendiri, dan proses tingkat instalasi
 *   (perintah konsol terjadwal, job antrean, seeder) menyatakan
 *   konteks sistemnya secara eksplisit lewat
 *   TenantContext::asSystem;
 * - platform: tidak dibatasi (aktor pengelola platform);
 * - standalone (instalasi tanpa tenant): hanya baris bertenant
 *   NULL, yaitu data milik instalasi itu sendiri;
 * - tenant: hanya baris milik tenant aktor;
 * - tanpa konteks sah (unassigned/menggantung): hasil kosong —
 *   gagal-tutup, bukan semua baris.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        if (! auth()->check()) {
            if (TenantContext::isGuestHttp()) {
                $builder->whereIn($table.'.tenant_id', [0]);
            }

            return;
        }

        $context = TenantContext::for(auth()->user());

        match ($context->mode()) {
            TenantContext::MODE_PLATFORM => null,
            TenantContext::MODE_STANDALONE => $builder->whereNull($table.'.tenant_id'),
            TenantContext::MODE_TENANT => $builder->where(
                $table.'.tenant_id',
                $context->tenant()?->id ?? 0,
            ),
            default => $builder->whereIn($table.'.tenant_id', [0]),
        };
    }
}
