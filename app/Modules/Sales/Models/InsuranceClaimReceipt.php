<?php

namespace App\Modules\Sales\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penerimaan pembayaran klaim dari perusahaan asuransi —
 * melunasi Piutang Asuransi (jurnal Dr tender / Cr 1-1700
 * diposting InsuranceClaimService).
 */
class InsuranceClaimReceipt extends Model
{
    protected $fillable = [
        'insurance_usage_id', 'amount', 'method', 'received_at', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function usage(): BelongsTo
    {
        return $this->belongsTo(InsuranceUsage::class, 'insurance_usage_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
