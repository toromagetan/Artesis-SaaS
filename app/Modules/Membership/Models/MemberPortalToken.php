<?php

namespace App\Modules\Membership\Models;

use App\Modules\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Token akses portal member (F3.7) — hanya hash yang disimpan. */
class MemberPortalToken extends Model
{
    protected $fillable = [
        'customer_id', 'token_hash', 'last_used_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null;
    }
}
