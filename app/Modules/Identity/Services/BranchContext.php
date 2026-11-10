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
 *
 * PAGAR TENANT (P0): konteks TIDAK PERNAH memuat cabang milik tenant
 * lain. Owner bertenant berwilayah seluruh cabang tenantnya sendiri —
 * dinyatakan lewat daftar cabang terkelola, bukan akses global, agar
 * BranchScope dan seluruh filter cabang ikut terpagar. Akses global
 * hanya tersisa pada instalasi mandiri yang belum memiliki tenant
 * sama sekali; akun tanpa tenant pada instalasi yang sudah bertenant
 * gagal-tutup (sejalan klasifikasi TenantContext: unassigned).
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
        private readonly bool $tenantWide = false,
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

        // Identitas tenant SELALU dari klasifikasi TenantContext —
        // bukan dari tenant_id mentah dan bukan dari tebakan
        // "tenant_id null = standalone". isValid() dipakai (mode
        // tenant + baris tenant nyata ada); status aktif tenant
        // SENGAJA tidak diperiksa di sini — penegakan suspensi
        // adalah pekerjaan modul lifecycle, bukan konteks cabang.
        $tenantContext = TenantContext::for($user);

        if ($user->hasRole('Owner')) {
            // Owner bertenant sah: seluruh cabang tenant miliknya
            // sendiri, tidak pernah cabang tenant lain.
            if ($tenantContext->isValid()) {
                return new self(
                    $user,
                    Branch::query()->where('tenant_id', $tenantContext->tenantId())->pluck('id')->all(),
                    false,
                    true,
                );
            }

            // Instalasi mandiri (klasifikasi standalone): perilaku
            // historis Blueprint B.1 — akses global.
            if ($tenantContext->isStandalone()) {
                return new self($user, Branch::query()->pluck('id')->all(), true, true);
            }

            // Unassigned atau tenant_id menggantung: gagal-tutup,
            // tanpa akses cabang bisnis apa pun.
            return new self($user, [], false);
        }

        // Aktor biasa bertenant sah: cabang yang melekat padanya,
        // dipotong ke cabang tenantnya sendiri (lampiran lintas
        // tenant pada pivot tidak pernah memberi akses).
        if ($tenantContext->isValid()) {
            return new self(
                $user,
                $user->branches()
                    ->where('branches.tenant_id', $tenantContext->tenantId())
                    ->pluck('branches.id')
                    ->all(),
                false,
            );
        }

        // Aktor biasa standalone: cabang sesuai pivot, seperti
        // perilaku historis instalasi mandiri.
        if ($tenantContext->isStandalone()) {
            return new self(
                $user,
                $user->branches()->pluck('branches.id')->all(),
                false,
            );
        }

        // Unassigned atau tenant_id menggantung: pivot TIDAK memberi
        // akses apa pun — gagal-tutup.
        return new self($user, [], false);
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

    /**
     * Aktor berwilayah seluruh perusahaannya: Owner pada instalasi
     * mandiri, atau Owner di dalam tenant miliknya. Dipakai untuk
     * tampilan tingkat perusahaan (mis. posisi kas dashboard) yang
     * tetap WAJIB terpagar ke cabang wilayah aktor.
     */
    public function isTenantWide(): bool
    {
        return $this->tenantWide;
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
