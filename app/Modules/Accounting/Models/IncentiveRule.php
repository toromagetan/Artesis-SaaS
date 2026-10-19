<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aturan insentif berkala (paritas Artesis): peran penerima,
 * produk opsional, persen dari basis penjualan atau flat per
 * unit/transaksi, masa berlaku.
 */
class IncentiveRule extends Model
{
    protected $fillable = [
        'name', 'recipient_role', 'product_id', 'calculation_type',
        'value', 'effective_from', 'effective_to', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
