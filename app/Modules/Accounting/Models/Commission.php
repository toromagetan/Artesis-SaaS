<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Doctor;
use App\Modules\Sales\Models\Sale;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'type', 'beneficiary_doctor_id', 'beneficiary_user_id', 'source_sale_id',
        'corrects_id', 'base_amount', 'rate', 'amount', 'period', 'status',
        'journal_id', 'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'beneficiary_doctor_id');
    }

    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'source_sale_id');
    }

    public function corrects(): BelongsTo
    {
        return $this->belongsTo(Commission::class, 'corrects_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function amountMoney(): Money
    {
        return Money::fromDecimal((string) $this->amount);
    }
}
