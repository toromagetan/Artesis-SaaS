<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Barang lama garansi yang tiba di gudang menunggu inspeksi.
 * Hasil baik → kembali ke stok layak jual (gerakan
 * warranty_return); rusak → menunggu penyesuaian write-off
 * ber-alasan (kuantitas rusak tidak pernah masuk stok jual).
 */
class BrokenStock extends Model
{
    protected $fillable = [
        'branch_id', 'product_id', 'warranty_case_id', 'quantity',
        'status', 'resolution', 'reason', 'note',
        'created_by', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warrantyCase(): BelongsTo
    {
        return $this->belongsTo(WarrantyCase::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
