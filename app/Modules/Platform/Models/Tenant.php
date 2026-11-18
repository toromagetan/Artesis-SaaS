<?php

namespace App\Modules\Platform\Models;

use App\Modules\Accounting\Services\ChartService;
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

    protected static function booted(): void
    {
        // ISO-2: setiap tenant baru langsung menerima salinan bagan
        // akunnya sendiri (ember tenant_id = tenant ini) dari templat
        // kanonis ChartService — berlaku untuk semua jalur pembuatan
        // tenant (pendaftaran publik, panel platform, pabrik uji).
        static::created(function (Tenant $tenant): void {
            app(ChartService::class)->seedForTenant($tenant->id);
        });
    }

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

    public function aiCreditRequests(): HasMany
    {
        return $this->hasMany(AiCreditRequest::class);
    }

    public function aiCreditLedger(): HasMany
    {
        return $this->hasMany(AiCreditLedger::class);
    }

    public function paygCreditRequests(): HasMany
    {
        return $this->hasMany(PaygCreditRequest::class);
    }

    public function paygCreditLedger(): HasMany
    {
        return $this->hasMany(PaygCreditLedger::class);
    }

    public function billingRequests(): HasMany
    {
        return $this->hasMany(TenantBillingRequest::class);
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
            // Terbaru menang bila data warisan menyisakan lebih dari
            // satu langganan aktif (audit 17 Nov 2026 F-5).
            ->orderByDesc('id')
            ->first();
    }

    public function currentPlan(): ?Plan
    {
        return $this->currentSubscription()?->plan;
    }
}
