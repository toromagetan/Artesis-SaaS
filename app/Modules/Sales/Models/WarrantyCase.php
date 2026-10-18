<?php

namespace App\Modules\Sales\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kasus garansi (paritas alur after-sales Artesis) — satu kasus
 * per item penjualan yang masih aktif; status mengalir:
 * open → waiting_warehouse → old_item_received →
 * replacement_preparing → replacement_shipped →
 * replacement_received → completed.
 */
class WarrantyCase extends Model
{
    public const ACTIVE_STATUSES = [
        'open', 'waiting_warehouse', 'old_item_received',
        'replacement_preparing', 'replacement_shipped', 'replacement_received',
    ];

    protected $fillable = [
        'number', 'sale_id', 'sale_item_id', 'branch_id', 'status',
        'reason', 'description', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function replacement(): HasOne
    {
        return $this->hasOne(WarrantyReplacement::class);
    }

    public function brokenStocks(): HasMany
    {
        return $this->hasMany(BrokenStock::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(WarrantyPayment::class);
    }

    public function customer(): ?Customer
    {
        return $this->sale?->customer;
    }

    /** Total biaya pelanggan yang sudah dibayar. */
    public function paidAmount(): string
    {
        return (string) $this->payments()->sum('amount');
    }
}
