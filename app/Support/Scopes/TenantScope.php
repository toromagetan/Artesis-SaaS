<?php

namespace App\Support\Scopes;

use App\Modules\Identity\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Pagar tenant di level query (S-02, pasangan BranchScope).
 * Klasifikasi aktor memakai TenantContext:
 * - tanpa aktor (konsol, seeder, antrean): tidak membatasi —
 *   proses tingkat instalasi memang bekerja lintas tenant;
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
        if (! auth()->check()) {
            return;
        }

        $context = TenantContext::for(auth()->user());
        $table = $model->getTable();

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
