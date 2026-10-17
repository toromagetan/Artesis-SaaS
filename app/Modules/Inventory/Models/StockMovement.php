<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Support\Concerns\BranchScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gerakan stok (Blueprint B.4). APPEND-ONLY secara struktural:
 * tidak ada updated_at, tidak ada service/rute untuk mengubahnya.
 * Satu-satunya penulis adalah InventoryService.
 */
class StockMovement extends Model
{
    use BranchScoped;

    public const UPDATED_AT = null;

    public const REASONS = [
        'initial_stock', 'sale', 'sale_void', 'receipt', 'purchase_return',
        'transfer_out', 'transfer_in', 'sale_return', 'opname', 'correction',
    ];

    /** Tipe produk yang saldonya TIDAK BOLEH negatif (D.6/D.8). */
    public const STRICT_TYPES = ['frame', 'accessory'];

    protected $fillable = [
        'product_id', 'branch_id', 'reason', 'qty',
        'reference_type', 'reference_id', 'balance_after', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
