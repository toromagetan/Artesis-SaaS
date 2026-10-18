<?php

namespace App\Modules\Membership\Models;

use App\Modules\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris buku besar poin (F3.6) — append-only, tanpa updated_at.
 * points bertanda: earn/positif-reversal menambah, redeem/expire/
 * negatif-reversal mengurangi. Saldo pelanggan = Σ seluruh barisnya.
 */
class PointsLedger extends Model
{
    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = null;

    protected $table = 'points_ledger';

    protected $fillable = [
        'customer_id', 'type', 'points', 'reference_type', 'reference_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'expires_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
