<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use App\Support\PermissionRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Pengguna Lentera (Blueprint B.1 Identity + D.1).
 * is_platform = aktor teknis platform; BUKAN pelaku bisnis — izin bisnis
 * selalu ditolak untuknya di hasPermission() (matriks keamanan G.1.2 kasus d).
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'locale',
        'is_active',
        'is_platform',
        'tenant_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /** Tenant SaaS pemilik pengguna bisnis ini (M3); null untuk aktor platform & instalasi lama. */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Ruang lingkup pengelolaan pengguna dari sisi tenant: aktor platform
     * TIDAK PERNAH tampil di pengelolaan pengguna tenant, dan aktor yang
     * terikat tenant hanya melihat pengguna tenantnya sendiri. Aktor tanpa
     * tenant (instalasi mandiri) melihat seluruh pengguna bisnis.
     */
    public function scopeVisibleInTenantManagement(Builder $query, User $actor): Builder
    {
        return $query->where('is_platform', false)
            ->when($actor->tenant_id !== null, fn (Builder $q) => $q->where('tenant_id', $actor->tenant_id));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /** Cabang yang dikelola pengguna (user_branch). */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branch');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    /**
     * Pemeriksaan izin kanonis. String di luar registry = bug (lempar).
     * Aktor platform ditolak untuk semua izin di luar kelompok 'platform'.
     * Pengguna nonaktif tidak memiliki izin apa pun.
     */
    public function hasPermission(string $permission): bool
    {
        PermissionRegistry::assertExists($permission);

        if (! $this->is_active) {
            return false;
        }

        if ($this->is_platform && PermissionRegistry::groupOf($permission) !== 'platform') {
            return false;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
            ->exists();
    }

    /** @return string[] seluruh string izin efektif pengguna */
    public function permissionNames(): array
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }

    public function isPlatformUser(): bool
    {
        return (bool) $this->is_platform;
    }

    /**
     * Akses global lintas cabang: peran Owner (Blueprint B.1).
     * Didelegasikan ke BranchContext — sumber kebenaran tunggal (F0.3).
     */
    public function hasGlobalAccess(): bool
    {
        return BranchContext::for($this)->hasGlobalAccess();
    }

    /**
     * ID cabang yang boleh diakses pengguna (delegasi BranchContext).
     *
     * @return int[]
     */
    public function managedBranchIds(): array
    {
        return BranchContext::for($this)->managedBranchIds();
    }
}
