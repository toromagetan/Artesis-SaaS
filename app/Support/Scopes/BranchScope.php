<?php

namespace App\Support\Scopes;

use App\Modules\Identity\Services\BranchContext;
use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Pagar cabang di level query (ADR-007). Membatasi hasil ke cabang yang
 * dikelola aktor saat ini. Tanpa aktor, tiga jalur dibedakan
 * persis seperti TenantScope (audit lanjutan temuan 8 + koreksi
 * audit PR #33): request HTTP tamu (penanda
 * TenantContext::isGuestHttp dari middleware MarkGuestRequest)
 * gagal-tertutup dengan hasil kosong; konteks sistem eksplisit
 * (TenantContext::asSystem — konsol terjadwal, antrean, seeder)
 * dan pemanggilan in-process berparameter aktor tidak dibatasi
 * di scope ini. Akses global (Owner) tidak dibatasi.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
            if (TenantContext::isGuestHttp()) {
                // Tamu HTTP: gagal-tertutup, selaras TenantScope.
                $builder->whereIn($model->getTable().'.branch_id', [0]);
            }

            return;
        }

        $context = BranchContext::current();

        if ($context->hasGlobalAccess()) {
            return;
        }

        $ids = $context->managedBranchIds();
        // Tanpa cabang terkelola: hasil harus kosong, bukan semua.
        $builder->whereIn($model->getTable().'.branch_id', $ids === [] ? [0] : $ids);
    }
}
