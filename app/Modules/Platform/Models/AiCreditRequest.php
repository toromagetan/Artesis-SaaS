<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan isi ulang saldo pertanyaan AI (bayar per penggunaan,
 * keputusan owner 2026-11-14). Harga per pertanyaan DIBEKUKAN di
 * baris ini saat pengajuan; konfirmasi platform menggerakkan buku
 * besar lewat AiCreditService.
 */
class AiCreditRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id', 'questions', 'unit_price', 'amount',
        'status', 'note', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'questions' => 'integer',
            'unit_price' => 'integer',
            'amount' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
