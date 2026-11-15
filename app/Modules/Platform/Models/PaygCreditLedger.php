<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buku besar saldo transaksi PAYG — APPEND-ONLY. Saldo tenant =
 * balance_after baris terakhirnya (boleh negatif = tunggakan).
 * Baris ditulis PaygCreditService di dalam transaksi berkunci
 * baris tenant; potongan (usage) dan pembalikan (reversal)
 * terikat ke penjualan lewat sale_id (unik per jenis).
 */
class PaygCreditLedger extends Model
{
    protected $table = 'payg_credit_ledger';

    protected $fillable = [
        'tenant_id', 'kind', 'credits_delta', 'balance_after',
        'unit_price', 'amount', 'request_id', 'sale_id', 'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'credits_delta' => 'integer',
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
