<?php

namespace App\Modules\Sales\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Insurance;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Support\Concerns\BranchScoped;
use App\Support\Money;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Transaksi penjualan (Blueprint B.3). Tidak pernah dihapus —
 * pembatalan hanya lewat void ber-alasan dengan pembalikan penuh.
 * paid_amount adalah denormalisasi yang DIPELIHARA SalesService
 * di dalam transaksi yang sama dengan baris pembayarannya.
 */
class Sale extends Model
{
    use BranchScoped, HasFactory;

    protected $fillable = [
        'number', 'client_uuid', 'customer_id', 'prescription_id', 'prescription_snapshot',
        'branch_id', 'cashier_id', 'doctor_id', 'insurance_id', 'sale_date',
        'subtotal_amount', 'discount_amount', 'tax_amount', 'tax_mode',
        'total_amount', 'paid_amount', 'status', 'void_reason', 'voided_by',
        'voided_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'voided_at' => 'datetime',
            'prescription_snapshot' => 'array',
            'subtotal_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    protected static function newFactory()
    {
        return SaleFactory::new();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    /** Proses lensa penjualan ini (M.5) — satu penjualan maks. satu proses. */
    /** PR yang tertaut ke penjualan ini (PR otomatis kekurangan lensa). */
    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function lensProcess(): HasOne
    {
        return $this->hasOne(LensProcess::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class)->orderBy('paid_at')->orderBy('id');
    }

    /** Jadwal cicilan piutang (F3.2), terurut per sequence. */
    public function installments(): HasMany
    {
        return $this->hasMany(SaleInstallment::class)->orderBy('sequence');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function isVoid(): bool
    {
        return $this->status === 'void';
    }

    /** Status bayar terhitung dari paid vs total (B.3/D.6). */
    public function paymentStatus(): string
    {
        if ($this->isVoid()) {
            return 'void';
        }
        $paid = Money::fromDecimal((string) $this->paid_amount);
        $total = Money::fromDecimal((string) $this->total_amount);
        if ($paid->cmp($total) >= 0) {
            return 'paid';
        }

        return $paid->isPositive() ? 'partial' : 'unpaid';
    }

    public function remaining(): Money
    {
        return Money::fromDecimal((string) $this->total_amount)
            ->sub(Money::fromDecimal((string) $this->paid_amount));
    }
}
