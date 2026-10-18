<?php

namespace App\Modules\Comms\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kampanye WA (F3.8). Alur status: draft -> approved -> sending ->
 * done (atau cancelled dari draft/approved). Pengiriman menolak
 * kampanye tanpa approved_by dan template tanpa approved_by.
 */
class WaCampaign extends Model
{
    protected $fillable = [
        'name', 'segment', 'template_id', 'recipient_count', 'approved_by', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'segment' => 'array',
            'recipient_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WaTemplate::class, 'template_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'campaign_id');
    }
}
