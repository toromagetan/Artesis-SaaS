<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot insentif per baris SaleItem per penerima (paritas
 * Artesis): penjual (peran Cabang/Admin) dan kepala optik
 * (Admin cabang) masing-masing mendapat barisnya sendiri.
 * status: eligible | cancelled (dengan alasan).
 */
class IncentiveItem extends Model
{
    public const STATUS_ELIGIBLE = 'eligible';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'incentive_period_id', 'sale_id', 'sale_item_id', 'product_id', 'product_name',
        'recipient_user_id', 'recipient_role', 'incentive_rule_id', 'calculation_type',
        'rule_value', 'quantity', 'item_subtotal', 'item_final_amount',
        'transaction_amount', 'incentive_amount', 'status', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'rule_value' => 'decimal:4',
            'quantity' => 'integer',
            'item_subtotal' => 'decimal:2',
            'item_final_amount' => 'decimal:2',
            'transaction_amount' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IncentivePeriod::class, 'incentive_period_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IncentiveRule::class, 'incentive_rule_id');
    }
}
