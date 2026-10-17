<?php

namespace App\Support\Scopes;

use App\Modules\Identity\Services\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Pagar cabang di level query (ADR-007). Membatasi hasil ke cabang yang
 * dikelola aktor saat ini. Tanpa aktor (konsol, seeder, antrean) scope
 * tidak membatasi — pagar konsol adalah tanggung jawab pemanggil.
 * Akses global (Owner) tidak dibatasi.
 */
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! auth()->check()) {
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
