<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku besar saldo pertanyaan AI — APPEND-ONLY. Saldo tenant =
 * balance_after baris terakhirnya. Baris ditulis AiCreditService
 * di dalam transaksi berkunci baris tenant.
 */
class AiCreditLedger extends Model
{
    protected $table = 'ai_credit_ledger';

    protected $fillable = [
        'tenant_id', 'kind', 'questions_delta', 'balance_after',
        'unit_price', 'amount', 'request_id', 'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'questions_delta' => 'integer',
            'balance_after' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
