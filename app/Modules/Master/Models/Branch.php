<?php

namespace App\Modules\Master\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cabang (Blueprint B.1 Master). SENGAJA tanpa kolom kode —
 * keputusan terkunci; rujukan cabang selalu id / nama.
 */
class Branch extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'address', 'phone', 'is_active', 'is_main_warehouse', 'tenant_id'];

    protected static function newFactory()
    {
        return BranchFactory::new();
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_main_warehouse' => 'boolean'];
    }

    /** Tenant SaaS pemilik cabang ini (M3); null pada instalasi lama satu bisnis. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branch');
    }
}
