<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Support\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use BranchScoped;

    public const STATUSES = ['draft', 'sent', 'partial', 'complete', 'void'];

    protected $fillable = [
        'number', 'supplier_id', 'branch_id', 'order_date',
        'total_amount', 'status', 'void_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }
}
