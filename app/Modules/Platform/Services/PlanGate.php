<?php

namespace App\Modules\Platform\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Validation\ValidationException;

/**
 * Gerbang penegakan paket di server (M4). Semua pemeriksaan berpangkal
 * pada klasifikasi TenantContext aktor (S-04), BUKAN sekadar
 * users.tenant_id mentah:
 *
 * - MODE_TENANT (tenant nyata) → penegakan paket tenant itu.
 * - MODE_STANDALONE (instalasi individual, belum ada tenant terdaftar)
 *   → perilaku lama: semua fitur menyala, semua batas tak terbatas.
 * - Mode lain (UNASSIGNED di instalasi bertenant, tenant_id
 *   menggantung, aktor PLATFORM pada jalur bisnis) → GAGAL-TUTUP:
 *   fitur mati, batas 0. tenant_id NULL tidak lagi otomatis berarti
 *   "operator instalasi".
 *
 * Fitur boolean digerbang middleware 'plan.feature' / assertEnabled;
 * batas angka digerbang di layanan pembuat entitas lewat
 * assertWithinLimit (kesalahan validasi, bukan 403, agar form dapat
 * menampilkan pesan).
 */
class PlanGate
{
    public function __construct(private readonly PlanService $plans) {}

    public function tenantFor(User $user): ?Tenant
    {
        $context = TenantContext::for($user);

        return $context->mode() === TenantContext::MODE_TENANT ? $context->tenant() : null;
    }

    private function isStandalone(User $user): bool
    {
        return TenantContext::for($user)->mode() === TenantContext::MODE_STANDALONE;
    }

    public function enabled(User $user, string $featureKey): bool
    {
        $tenant = $this->tenantFor($user);
        if ($tenant !== null) {
            return $this->plans->featureEnabled($tenant, $featureKey);
        }

        return $this->isStandalone($user);
    }

    public function limit(User $user, string $featureKey): ?int
    {
        $tenant = $this->tenantFor($user);
        if ($tenant !== null) {
            return $this->plans->featureLimit($tenant, $featureKey);
        }

        return $this->isStandalone($user) ? null : 0;
    }

    /** Lempar 403 berbahasa pengguna bila fitur terkunci untuk tenant aktor. */
    public function assertEnabled(User $user, string $featureKey): void
    {
        if (! $this->enabled($user, $featureKey)) {
            abort(403, __('ui.plans.locked'));
        }
    }

    /** Pemakaian saat ini untuk batas angka (produk/pengguna/cabang tenant). */
    public function usage(User $user, string $featureKey): int
    {
        $tenant = $this->tenantFor($user);
        if ($tenant === null) {
            return 0;
        }

        return match ($featureKey) {
            // A1: batas produk dihitung per DETAIL (unit SKU pada
            // lima tabel detail), bukan per master.
            'products_max' => collect(Product::DETAIL_MODELS)
                ->sum(fn (string $model) => $model::where('tenant_id', $tenant->id)->count()),
            'users_max' => User::where('tenant_id', $tenant->id)->where('is_platform', false)->count(),
            'branches_max' => Branch::where('tenant_id', $tenant->id)->count(),
            default => 0,
        };
    }

    /**
     * Tolak pembuatan entitas baru bila batas paket sudah tercapai.
     *
     * @throws ValidationException
     */
    public function assertWithinLimit(User $user, string $featureKey): void
    {
        $limit = $this->limit($user, $featureKey);
        if ($limit === null) {
            return;
        }

        if ($this->usage($user, $featureKey) >= $limit) {
            throw ValidationException::withMessages([
                'plan' => __('ui.plans.limit_reached', ['limit' => $limit]),
            ]);
        }
    }
}
