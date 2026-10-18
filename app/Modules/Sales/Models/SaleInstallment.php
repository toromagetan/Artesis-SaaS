<?php

namespace App\Modules\Sales\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris jadwal cicilan (F3.2). Baris jadwal adalah RENCANA —
 * catatan keuangannya adalah SalePayment; paid_amount di sini adalah
 * alokasi pembayaran ke baris ini (FIFO per sequence).
 * Status 'overdue' terhitung: jatuh tempo lampau & belum lunas.
 */
class SaleInstallment extends Model
{
    protected $fillable = [
        'sale_id', 'sequence', 'due_date', 'amount', 'paid_amount', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'sequence' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function remaining(): Money
    {
        return Money::fromDecimal((string) $this->amount)
            ->sub(Money::fromDecimal((string) $this->paid_amount));
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'paid' && $this->due_date->lt(today());
    }

    /** Status efektif termasuk 'overdue' terhitung (Blueprint B.3). */
    public function effectiveStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }
}
