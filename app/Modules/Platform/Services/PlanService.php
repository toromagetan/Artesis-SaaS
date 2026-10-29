<?php

namespace App\Modules\Platform\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantFeatureOverride;
use App\Modules\Platform\Models\TenantSubscription;
use App\Support\PlanFeatureRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Layanan paket SaaS (M3): penanaman definisi paket, penetapan paket
 * ke tenant, pengecualian fitur per tenant, dan perhitungan fitur
 * efektif (paket + override yang masih berlaku).
 *
 * Kelas ini adalah lapisan DATA — penegakan di alur bisnis (gerbang
 * server per fitur/batas) adalah modul M4.
 */
class PlanService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Tanam/perbarui paket bawaan dari registry (idempoten). */
    public function seedDefaultPlans(): void
    {
        DB::transaction(function () {
            foreach (PlanFeatureRegistry::defaultPlans() as $key => $definition) {
                $plan = Plan::updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $definition['name'],
                        'price_monthly' => $definition['price_monthly'],
                        'extra_branch_price' => $definition['extra_branch_price'],
                        'sort_order' => $definition['sort_order'],
                        'is_active' => true,
                    ],
                );

                foreach ($definition['features'] as $featureKey => $value) {
                    $kind = PlanFeatureRegistry::kind($featureKey);
                    $plan->features()->updateOrCreate(
                        ['feature_key' => $featureKey],
                        $kind === 'boolean'
                            ? ['enabled' => (bool) $value, 'limit_value' => null]
                            : ['enabled' => null, 'limit_value' => $value],
                    );
                }
            }
        });
    }

    /**
     * Fitur efektif tenant: peta paket saat ini digabung override yang
     * masih berlaku (override menang). Tenant tanpa langganan aktif
     * diperlakukan sebagai paket Free (bawaan registry, bukan baris DB)
     * agar perhitungan selalu terdefinisi.
     *
     * @return array<string, array{enabled: ?bool, limit: ?int}>
     */
    public function effectiveFeatures(Tenant $tenant): array
    {
        $plan = $tenant->currentPlan();
        $map = $plan !== null
            ? $plan->featuresMap()
            : $this->defaultFeatureMap('free');

        foreach ($tenant->overrides()->currentlyValid()->get() as $override) {
            $kind = PlanFeatureRegistry::kind($override->feature_key);
            $map[$override->feature_key] = $kind === 'boolean'
                ? ['enabled' => $override->enabled, 'limit' => null]
                : ['enabled' => null, 'limit' => $override->limit_value];
        }

        return $map;
    }

    /** Apakah fitur boolean efektif menyala untuk tenant. */
    public function featureEnabled(Tenant $tenant, string $featureKey): bool
    {
        PlanFeatureRegistry::assertExists($featureKey);
        $features = $this->effectiveFeatures($tenant);

        return (bool) ($features[$featureKey]['enabled'] ?? false);
    }

    /** Batas angka efektif (null = tanpa batas). */
    public function featureLimit(Tenant $tenant, string $featureKey): ?int
    {
        PlanFeatureRegistry::assertExists($featureKey);
        $features = $this->effectiveFeatures($tenant);

        return $features[$featureKey]['limit'] ?? null;
    }

    /**
     * Tetapkan paket ke tenant: langganan aktif lama ditutup (expired),
     * langganan baru dibuat. Diaudit.
     */
    public function assignPlan(Tenant $tenant, Plan $plan, ?Carbon $expiresAt, User $actor): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $plan, $expiresAt, $actor) {
            $previous = $tenant->currentSubscription();
            if ($previous !== null) {
                $previous->update(['status' => TenantSubscription::STATUS_EXPIRED]);
            }

            $subscription = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'starts_at' => today(),
                'expires_at' => $expiresAt?->toDateString(),
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                'tenant.plan_assigned',
                Tenant::class,
                $tenant->id,
                ['plan' => $previous?->plan?->key],
                ['plan' => $plan->key, 'expires_at' => $expiresAt?->toDateString()],
                null,
                $actor,
            );

            return $subscription;
        });
    }

    /**
     * Simpan pengecualian fitur per tenant (wajib alasan; boleh
     * berbatas waktu). Diaudit.
     *
     * @param  array{enabled?: ?bool, limit_value?: ?int}  $value
     */
    public function setOverride(Tenant $tenant, string $featureKey, array $value, ?Carbon $expiresAt, string $reason, User $actor): TenantFeatureOverride
    {
        PlanFeatureRegistry::assertExists($featureKey);
        $kind = PlanFeatureRegistry::kind($featureKey);

        $override = TenantFeatureOverride::create([
            'tenant_id' => $tenant->id,
            'feature_key' => $featureKey,
            'enabled' => $kind === 'boolean' ? ($value['enabled'] ?? null) : null,
            'limit_value' => $kind === 'limit' ? ($value['limit_value'] ?? null) : null,
            'expires_at' => $expiresAt,
            'reason' => $reason,
            'created_by' => $actor->id,
        ]);

        $this->audit->log(
            'tenant.feature_override',
            Tenant::class,
            $tenant->id,
            null,
            [
                'feature' => $featureKey,
                'enabled' => $override->enabled,
                'limit_value' => $override->limit_value,
                'expires_at' => $expiresAt?->toDateTimeString(),
                'reason' => $reason,
            ],
            null,
            $actor,
        );

        return $override;
    }

    /** @return array<string, array{enabled: ?bool, limit: ?int}> */
    private function defaultFeatureMap(string $planKey): array
    {
        $map = [];
        foreach (PlanFeatureRegistry::defaultPlans()[$planKey]['features'] as $featureKey => $value) {
            $map[$featureKey] = PlanFeatureRegistry::kind($featureKey) === 'boolean'
                ? ['enabled' => (bool) $value, 'limit' => null]
                : ['enabled' => null, 'limit' => $value];
        }

        return $map;
    }
}
