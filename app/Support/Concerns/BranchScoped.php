<?php

namespace App\Support\Concerns;

use App\Modules\Identity\Services\BranchContext;
use App\Support\Scopes\BranchScope;

/**
 * Tandai model sebagai milik cabang. Memasang BranchScope sebagai
 * global scope dan mengisi branch_id otomatis dari konteks aktor
 * saat pembuatan (bila belum ditetapkan secara eksplisit oleh service).
 */
trait BranchScoped
{
    protected static function bootBranchScoped(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $model->branch_id = BranchContext::current()->currentBranchId();
            }
        });
    }
}
