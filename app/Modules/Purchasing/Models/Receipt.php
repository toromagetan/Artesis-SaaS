<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Identity\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Penerimaan barang (dokumen final saat disimpan — koreksi hanya lewat
 * retur pembelian). Sisa tagihan SELALU terhitung (B.4): total receipt
 * − Σ alokasi pembayaran − Σ retur berstatus reduces_payable.
 * Cabang diwarisi dari PO induk.
 */
class Receipt extends Model
{
    protected $fillable = [
        'number', 'purchase_order_id', 'received_date', 'delivery_note_no', 'received_by',
    ];

    protected function casts(): array
    {
        return ['received_date' => 'date'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReceiptItem::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(PurchaseReturn::class);
    }

    /** Total nilai penerimaan pada harga aktual. */
    public function totalAmount(): Money
    {
        $total = Money::zero();
        foreach ($this->items as $item) {
            $total = $total->add(Money::fromDecimal((string) $item->unit_cost_actual)->mul($item->qty));
        }

        return $total;
    }

    public function allocatedAmount(): Money
    {
        $sum = Money::zero();
        foreach ($this->allocations as $allocation) {
            $sum = $sum->add(Money::fromDecimal((string) $allocation->amount));
        }

        return $sum;
    }

    /** Retur yang mengurangi tagihan (bukan kredit supplier). */
    public function reducingReturnsAmount(): Money
    {
        $sum = Money::zero();
        foreach ($this->returns->where('credit_status', 'reduces_payable') as $retur) {
            $sum = $sum->add(Money::fromDecimal((string) $retur->total_amount));
        }

        return $sum;
    }

    public function remainingAmount(): Money
    {
        $remaining = $this->totalAmount()
            ->sub($this->allocatedAmount())
            ->sub($this->reducingReturnsAmount());

        return $remaining->isNegative() ? Money::zero() : $remaining;
    }

    public function paymentStatus(): string
    {
        if ($this->remainingAmount()->isZero()) {
            return 'paid';
        }

        return $this->allocatedAmount()->isZero() && $this->reducingReturnsAmount()->isZero()
            ? 'unpaid'
            : 'partial';
    }
}
