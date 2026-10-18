<?php

namespace App\Modules\Lab\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Order lab per penjualan (F2.3, Blueprint B.6). Mesin status linear
 * waiting → ordered → received — ditegakkan LabOrderService.
 */
class LabOrder extends Model
{
    public const STATUSES = ['waiting', 'ordered', 'received'];

    protected $fillable = [
        'number',
        'sale_id',
        'lab_name',
        'status',
        'sent_at',
        'received_at',
        'lab_cost',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
            'lab_cost' => 'decimal:2',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function labCost(): Money
    {
        return Money::fromDecimal((string) $this->lab_cost);
    }
}
