<?php

namespace App\Modules\Platform\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant SaaS (M3): satu bisnis optik pelanggan Artesis. Cabang dan
 * pengguna bisnis terikat ke tenant lewat kolom tenant_id (nullable —
 * instalasi lama satu bisnis tetap sah tanpa tenant).
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'email', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class)->latest('id');
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(TenantFeatureOverride::class)->latest('id');
    }

    /** Langganan aktif saat ini (status active dan belum kedaluwarsa). */
    public function currentSubscription(): ?TenantSubscription
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))
            ->first();
    }

    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan;
    }
}
