<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Master\Models\Branch;
use App\Modules\Purchasing\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lapisan harga FIFO (ADR-014, F4.2): satu baris per penerimaan
 * (atau pembuka/retur/transfer masuk) per produk-cabang. Urutan
 * konsumsi = id menaik di cabang yang sama. qty_remaining hanya
 * berubah lewat FifoService di dalam transaksi dokumen sumber.
 */
class StockLayer extends Model
{
    protected $fillable = [
        'product_id', 'branch_id', 'receipt_item_id', 'source_movement_id',
        'qty_received', 'qty_remaining', 'unit_cost', 'is_opening',
    ];

    protected function casts(): array
    {
        return [
            'qty_received' => 'integer',
            'qty_remaining' => 'integer',
            'unit_cost' => 'decimal:2',
            'is_opening' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(ReceiptItem::class);
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'source_movement_id');
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(StockLayerConsumption::class, 'layer_id');
    }
}
