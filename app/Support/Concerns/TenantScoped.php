<?php

namespace App\Support\Concerns;

use App\Modules\Identity\Services\TenantContext;
use App\Support\Scopes\TenantScope;

/**
 * Tandai model sebagai milik tenant (S-02). Memasang TenantScope
 * sebagai global scope dan mencap tenant_id otomatis dari aktor
 * tenant yang sedang berjalan saat pembuatan, bila belum
 * ditetapkan secara eksplisit oleh service.
 */
trait TenantScoped
{
    protected static function bootTenantScoped(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! empty($model->tenant_id) || ! auth()->check()) {
                return;
            }

            $context = TenantContext::for(auth()->user());
            if ($context->mode() === TenantContext::MODE_TENANT) {
                $model->tenant_id = $context->tenant()?->id;
            }
        });
    }
}
