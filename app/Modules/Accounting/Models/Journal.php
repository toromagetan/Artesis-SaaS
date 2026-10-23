<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Identity\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    protected $fillable = [
        'number', 'journal_date', 'source_type', 'source_id', 'idempotency_key', 'description',
        'period_locked', 'reversal_of_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'period_locked' => 'boolean',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'reversal_of_id');
    }

    public function totalDebit(): Money
    {
        $sum = Money::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->add(Money::fromDecimal((string) $line->debit));
        }

        return $sum;
    }

    public function isReversed(): bool
    {
        return Journal::where('reversal_of_id', $this->id)->exists();
    }
}
