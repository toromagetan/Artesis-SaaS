<?php

namespace App\Modules\Comms\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris log pesan WhatsApp (F3.4) — SETIAP pesan keluar tercatat
 * di sini dengan statusnya (gerbang Fase 3: log 100%). Arah 'in'
 * ditulis webhook (inbox F3.8 membacanya).
 */
class WaMessage extends Model
{
    protected $fillable = [
        'customer_id', 'phone', 'template_key', 'body', 'direction', 'status',
        'trigger', 'campaign_id', 'sent_by', 'provider_message_id', 'error', 'sent_at', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
