<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Peran (S-03): tenant_id NULL = templat platform/instalasi yang
 * dibagikan; tenant_id terisi = peran milik tenant itu. Templat
 * hanya dapat diubah aktor platform (atau pemilik instalasi
 * mandiri); aktor tenant hanya mengubah perannya sendiri.
 */
class Role extends Model
{
    protected $fillable = ['name', 'label', 'tenant_id'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Peran yang boleh DILIHAT aktor: platform & instalasi mandiri
     * melihat semuanya; aktor tenant melihat templat + milik
     * tenantnya; aktor tanpa konteks sah hanya templat.
     */
    public function scopeVisibleTo(Builder $query, User $actor): Builder
    {
        $mode = TenantContext::for($actor)->mode();

        return match ($mode) {
            TenantContext::MODE_PLATFORM, TenantContext::MODE_STANDALONE => $query,
            TenantContext::MODE_TENANT => $query->where(
                fn (Builder $q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $actor->tenant_id),
            ),
            default => $query->whereNull('tenant_id'),
        };
    }

    /** Apakah aktor boleh MENGUBAH peran ini (izinnya/labelnya)? */
    public function isEditableBy(User $actor): bool
    {
        $mode = TenantContext::for($actor)->mode();

        return match ($mode) {
            TenantContext::MODE_PLATFORM => true,
            TenantContext::MODE_STANDALONE => $this->tenant_id === null,
            TenantContext::MODE_TENANT => $this->tenant_id !== null && $this->tenant_id === $actor->tenant_id,
            default => false,
        };
    }
}
