<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Akun bagan (ISO-2): setiap akun milik SATU ember — tenant_id
 * terisi = milik tenant itu; NULL = templat platform sekaligus
 * bagan instalasi mandiri. Kode unik per ember, bukan global.
 */
class Account extends Model
{
    public const GROUPS = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    protected $fillable = ['code', 'name', 'group', 'parent_id', 'branch_id', 'is_active', 'tenant_id'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Batasi kueri ke satu ember (null = ember platform/mandiri). */
    public function scopeInBucket(Builder $query, ?int $tenantId): Builder
    {
        return $tenantId === null
            ? $query->whereNull('tenant_id')
            : $query->where('tenant_id', $tenantId);
    }

    /**
     * Ember akun seorang aktor: aktor tenant → tenantnya; platform
     * dan instalasi mandiri → ember NULL. Konteks lain (tanpa
     * tenant sah / tamu) tidak memiliki ember akun — ditolak.
     */
    public static function bucketOf(User $actor): ?int
    {
        $context = TenantContext::for($actor);

        return match (true) {
            $context->isValid() => $context->tenantId(),
            $context->isPlatform(), $context->isStandalone() => null,
            default => throw new AuthorizationException('Aktor tanpa konteks sah tidak memiliki bagan akun.'),
        };
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Akun ringkasan = memiliki anak; jurnal tidak boleh memposting ke akun ringkasan (Blueprint D.9). */
    public function isSummary(): bool
    {
        return $this->children()->exists();
    }
}
