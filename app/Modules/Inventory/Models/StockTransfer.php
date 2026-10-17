<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Transfer distribusi antar cabang (B.4). Tidak memakai BranchScoped:
 * dokumen menyentuh DUA cabang (asal & tujuan); pagar ditegakkan
 * eksplisit di controller/service terhadap kedua cabangnya.
 */
class StockTransfer extends Model
{
    public const STATUSES = ['sent', 'received', 'cancelled'];

    protected $fillable = [
        'number', 'from_branch_id', 'to_branch_id', 'source_type', 'source_id',
        'status', 'sent_by', 'received_by', 'received_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
    }

    public function fromBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'from_branch_id');
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'to_branch_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'source_id');
    }
}
