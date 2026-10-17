<?php

namespace App\Modules\Master\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cabang (Blueprint B.1 Master). SENGAJA tanpa kolom kode —
 * keputusan terkunci; rujukan cabang selalu id / nama.
 */
class Branch extends Model
{
    protected $fillable = ['name', 'address', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch');
    }
}
