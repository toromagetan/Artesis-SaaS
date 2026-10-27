<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token API read-only (F4.6). token_hash = sha256 token mentah;
 * token mentah hanya tampil SATU KALI saat penerbitan.
 */
class ApiToken extends Model
{
    /**
     * Cakupan sah — seluruhnya baca-saja, tak ada cakupan tulis.
     * LNT-004: data sensitif punya cakupan sendiri — products.cost
     * (harga modal) dan customers.contact (nomor HP) TIDAK ikut
     * cakupan baca umum.
     */
    public const SCOPES = ['products.read', 'products.cost', 'customers.read', 'customers.contact', 'sales.read', 'stock.read', 'reports.read'];

    protected $fillable = ['name', 'token_hash', 'scopes', 'last_used_at', 'revoked_at', 'expires_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && ! $this->isExpired();
    }

    public function allows(string $scope): bool
    {
        return $this->isUsable() && in_array($scope, $this->scopes ?? [], true);
    }
}
