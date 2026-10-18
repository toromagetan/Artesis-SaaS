<?php

namespace App\Modules\Comms\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template pesan WhatsApp (F3.4). Kunci tetap Blueprint B.7:
 * invoice, pickup_reminder, after_service, birthday, campaign.
 * Badan memakai variabel {nama}, {nomor}, {total}, {cabang}, {isi}.
 */
class WaTemplate extends Model
{
    public const KEYS = ['invoice', 'pickup_reminder', 'after_service', 'birthday', 'campaign'];

    protected $fillable = [
        'key', 'body', 'approved_by',
    ];

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Render variabel {kunci} dari konteks; variabel tak dikenal dibiarkan. */
    public function render(array $context): string
    {
        $map = [];
        foreach ($context as $key => $value) {
            $map['{'.$key.'}'] = (string) $value;
        }

        return strtr($this->body, $map);
    }
}
