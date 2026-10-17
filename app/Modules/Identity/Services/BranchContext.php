<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Konteks cabang aktor (Blueprint D.1 + A.3 butir 5 + ADR-007).
 *
 * SATU-SATUNYA sumber kebenaran untuk "cabang mana yang boleh diakses
 * pengguna ini". branch_id pada query bisnis WAJIB berasal dari sini —
 * tidak pernah dari input request mentah.
 */
class BranchContext
{
    private const CONTAINER_KEY = 'lentera.branch-context';

    private readonly ?int $userId;

    /**
     * @param  int[]  $managedBranchIds
     */
    private function __construct(
        private readonly ?User $user,
        private readonly array $managedBranchIds,
        private readonly bool $globalAccess,
    ) {
        $this->userId = $user?->id;
    }

    public static function for(?User $user): self
    {
        if ($user === null) {
            return new self(null, [], false);
        }

        // Aktor platform bukan pelaku bisnis: tidak ada akses cabang bisnis.
        if ($user->is_platform) {
            return new self($user, [], false);
        }

        // Peran Owner = akses global ke semua cabang (Blueprint B.1).
        if ($user->hasRole('Owner')) {
            return new self($user, Branch::query()->pluck('id')->all(), true);
        }

        return new self(
            $user,
            $user->branches()->pluck('branches.id')->all(),
            false,
        );
    }

    /**
     * Konteks untuk pengguna yang sedang login pada request ini.
     * Disimpan pada container aplikasi (bukan statik) agar tidak bocor
     * antar-request/antar-test; dibangun ulang bila pengguna berganti.
     */
    public static function current(): self
    {
        $app = app();
        $userId = auth()->id();

        if ($app->bound(self::CONTAINER_KEY)) {
            $bound = $app->make(self::CONTAINER_KEY);
            if ($bound instanceof self && $bound->userId === $userId) {
                return $bound;
            }
        }

        $context = self::for(auth()->user());
        $app->instance(self::CONTAINER_KEY, $context);

        return $context;
    }

    public static function forgetCurrent(): void
    {
        if (app()->bound(self::CONTAINER_KEY)) {
            app()->forgetInstance(self::CONTAINER_KEY);
        }
    }

    public function user(): ?User
    {
        return $this->user;
    }

    public function hasGlobalAccess(): bool
    {
        return $this->globalAccess;
    }

    /** @return int[] */
    public function managedBranchIds(): array
    {
        return $this->managedBranchIds;
    }

    public function allowsBranch(?int $branchId): bool
    {
        if ($this->globalAccess) {
            return true;
        }

        return $branchId !== null && in_array($branchId, $this->managedBranchIds, true);
    }

    /**
     * Cabang aktif untuk penulisan data baru: pilihan sesi bila sah,
     * selain itu cabang terkelola pertama. Null bila tidak ada konteks
     * (konsol) atau pengguna tanpa cabang — pemanggil WAJIB menetapkan
     * cabang secara eksplisit dalam kasus itu.
     */
    public function currentBranchId(): ?int
    {
        if ($this->user === null) {
            return null;
        }

        $sessionBranch = session('current_branch_id');
        if (is_numeric($sessionBranch) && $this->allowsBranch((int) $sessionBranch)) {
            return (int) $sessionBranch;
        }

        return $this->managedBranchIds[0] ?? null;
    }

    /**
     * @throws AuthorizationException
     */
    public function assertCanAccess(int $branchId): void
    {
        if (! $this->allowsBranch($branchId)) {
            throw new AuthorizationException('Cabang di luar wilayah kelola pengguna.');
        }
    }
}
