<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penyesuaian insentif (paritas Artesis): clawback retur
 * pelanggan — baris negatif yang dibuat OTOMATIS saat retur
 * menyentuh item yang sudah eligible di periode approved/paid,
 * dan mendarat di periode BERIKUTNYA. Item historis tidak
 * pernah diubah.
 */
class IncentiveAdjustment extends Model
{
    public const TYPE_CUSTOMER_RETURN = 'customer_return';

    public const REASON_CUSTOMER_RETURN = 'Penyesuaian insentif karena retur pelanggan.';

    protected $fillable = [
        'incentive_period_id', 'original_incentive_item_id', 'sale_return_id',
        'sale_id', 'sale_item_id', 'product_id', 'product_name',
        'recipient_user_id', 'recipient_role', 'returned_quantity',
        'adjustment_type', 'amount', 'reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'returned_quantity' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(IncentivePeriod::class, 'incentive_period_id');
    }

    public function originalItem(): BelongsTo
    {
        return $this->belongsTo(IncentiveItem::class, 'original_incentive_item_id');
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class, 'sale_return_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
