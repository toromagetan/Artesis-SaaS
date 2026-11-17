<?php

namespace App\Modules\Comms\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kampanye WA (F3.8). Alur status: draft -> approved -> sending ->
 * done (atau cancelled dari draft/approved). Pengiriman menolak
 * kampanye tanpa approved_by dan template tanpa approved_by.
 *
 * Kepemilikan per tenant (audit W-01 W1-1): tenant_id = tenant
 * pembuat; NULL = kampanye platform / instalasi mandiri yang
 * berjangkauan se-instalasi secara desain. Resolusi penerima
 * diemberkan dari kolom ini, bukan dari konteks ambien.
 */
class WaCampaign extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'segment', 'template_id', 'recipient_count', 'approved_by', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'segment' => 'array',
            'recipient_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
