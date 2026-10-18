<?php

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token API read-only (F4.6). token_hash = sha256 token mentah;
 * token mentah hanya tampil SATU KALI saat penerbitan.
 */
class ApiToken extends Model
{
    /** Cakupan sah — seluruhnya baca-saja, tak ada cakupan tulis. */
    public const SCOPES = ['products.read', 'customers.read', 'sales.read', 'stock.read', 'reports.read'];

    protected $fillable = ['name', 'token_hash', 'scopes', 'last_used_at', 'revoked_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null;
    }

    public function allows(string $scope): bool
    {
        return $this->isUsable() && in_array($scope, $this->scopes ?? [], true);
    }
}
