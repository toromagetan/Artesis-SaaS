<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransferItem extends Model
{
    protected $fillable = ['stock_transfer_id', 'detail_id', 'detail_type', 'qty'];

    protected function casts(): array
    {
        return ['qty' => 'integer'];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function detail(): MorphTo
    {
        return $this->morphTo();
    }
}
