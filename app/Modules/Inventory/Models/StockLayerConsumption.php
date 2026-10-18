<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rincian konsumsi lapisan oleh satu gerakan keluar (ADR-014).
 * APPEND-ONLY: tidak ada updated_at. Satu-satunya pengecualian
 * penghapusan adalah pemulihan void penjualan penuh (FifoService
 * ::restoreConsumptions) — penjualannya sendiri dibatalkan total
 * dan jurnalnya di-reverse, sehingga konsumsi ikut dipulihkan ke
 * lapisan asalnya agar urutan FIFO tidak bergeser.
 */
class StockLayerConsumption extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['movement_id', 'layer_id', 'qty', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class, 'layer_id');
    }
}
