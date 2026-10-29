<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Paket langganan SaaS (M3). Definisi awal ditanam dari
 * PlanFeatureRegistry; panel platform adalah sumber operasional.
 */
class Plan extends Model
{
    protected $fillable = [
        'key', 'name', 'price_monthly', 'extra_branch_price', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'extra_branch_price' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * Peta fitur paket: kunci => ['enabled' => ?bool, 'limit' => ?int].
     *
     * @return array<string, array{enabled: ?bool, limit: ?int}>
     */
    public function featuresMap(): array
    {
        $map = [];
        foreach ($this->features as $feature) {
            $map[$feature->feature_key] = [
                'enabled' => $feature->enabled,
                'limit' => $feature->limit_value,
            ];
        }

        return $map;
    }
}
