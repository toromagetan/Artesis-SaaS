<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    protected $fillable = [
        'employee_user_id', 'period', 'base_amount', 'allowance_amount',
        'deduction_amount', 'net_amount', 'journal_id', 'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_user_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function isPosted(): bool
    {
        return $this->journal_id !== null;
    }

    public function net(): Money
    {
        return Money::fromDecimal((string) $this->net_amount);
    }
}
