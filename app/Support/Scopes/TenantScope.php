<?php

namespace App\Support\Scopes;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Pagar tenant di level query (S-02, pasangan BranchScope).
 * Klasifikasi memakai TenantContext — tiga jenis jalur tanpa
 * aktor dibedakan secara eksplisit (audit lanjutan temuan 8 +
 * koreksi audit PR #33):
 * - request HTTP tamu (penanda TenantContext::isGuestHttp dari
 *   middleware MarkGuestRequest): hasil kosong — gagal-tutup.
 *   Guest HTTP tidak boleh membaca data tenant hanya karena tidak
 *   ada yang login;
 * - proses sistem tingkat instalasi (perintah konsol terjadwal,
 *   job antrean, seeder): wajib menyatakan konteksnya lewat
 *   TenantContext::asSystem — penanda itulah yang membuka akses
 *   lintas tenant untuk operasi sistem, dan operasi TANPA aktor
 *   yang berdampak lintas tenant (mis. penulisan pengaturan
 *   global di Setting) menolak jalur yang tidak berpenanda;
 * - pemanggilan in-process dari layanan aplikasi yang menerima
 *   aktor secara eksplisit sebagai parameter: tidak dibatasi di
 *   scope ini — validasi ember adalah tanggung jawab layanan itu
 *   sendiri (kontrak yang dipertahankan atas instruksi audit;
 *   layanan TIDAK dipaksa bergantung pada sesi auth());
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
                // Jalur 1 — tamu HTTP: gagal-tertutup.
                $builder->whereIn($table.'.tenant_id', [0]);
            }

            // Jalur 2 — konteks sistem eksplisit (asSystem): terbuka
            // lintas tenant secara terpercaya.
            // Jalur 3 — in-process berparameter aktor: terbuka di
            // scope; layanan memvalidasi embernya sendiri.
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
