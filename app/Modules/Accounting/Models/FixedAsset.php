<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aset tetap (F1.4): nilai buku = harga perolehan − akumulasi. */
class FixedAsset extends Model
{
    protected $fillable = [
        'name', 'acquired_at', 'acquisition_cost', 'useful_life_months',
        'method', 'accumulated_amount', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'acquired_at' => 'date',
            'acquisition_cost' => 'decimal:2',
            'accumulated_amount' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cost(): Money
    {
        return Money::fromDecimal((string) $this->acquisition_cost);
    }

    public function accumulated(): Money
    {
        return Money::fromDecimal((string) $this->accumulated_amount);
    }

    public function bookValue(): Money
    {
        return $this->cost()->sub($this->accumulated());
    }

    public function isFullyDepreciated(): bool
    {
        return $this->accumulated()->cmp($this->cost()) >= 0;
    }
}
