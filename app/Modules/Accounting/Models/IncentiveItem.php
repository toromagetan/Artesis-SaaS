<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Rincian satu aturan di dalam periode insentif. */
class IncentiveItem extends Model
{
    protected $fillable = [
        'incentive_period_id', 'incentive_rule_id', 'product_id',
        'basis_amount', 'basis_qty', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'basis_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IncentivePeriod::class, 'incentive_period_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IncentiveRule::class, 'incentive_rule_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
