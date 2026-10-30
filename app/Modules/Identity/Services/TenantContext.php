<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Konteks tenant aktor (fondasi R-3; pasangan BranchContext).
 *
 * SATU-SATUNYA sumber kebenaran untuk "tenant siapa pengguna ini"
 * pada lapisan fondasi. Konteks SELALU diturunkan dari pengguna
 * terautentikasi dan relasi tenant_id miliknya yang tervalidasi ke
 * baris tenant nyata — TIDAK PERNAH dari input request (URL, query,
 * header, payload, sesi): kelas ini bahkan tidak membaca request.
 *
 * Empat mode eksplisit (akun tanpa tenant_id BUKAN otomatis
 * platform — keduanya dibedakan tegas):
 * - tenant     : pengguna bisnis dengan tenant_id yang sah.
 * - platform   : aktor platform (is_platform) — BUKAN identitas
 *                tenant; tidak pernah memperoleh konteks tenant.
 * - standalone : pengguna tanpa tenant pada instalasi individual.
 * - guest      : tidak ada pengguna (konsol/tamu).
 *
 * Resolusi identitas dipisahkan dari otorisasi: tenant yang
 * dinonaktifkan tetap teridentifikasi (isSuspended), tetapi
 * requireTenant() — primitif "tenant yang boleh dipakai berbisnis"
 * — menolaknya, sama seperti menolak tenant yang tidak ditemukan,
 * platform, standalone, dan tamu. Penegakan suspensi pada alur
 * request adalah pekerjaan tahap lifecycle (bukan fondasi ini).
 *
 * Request-scoped seperti BranchContext: disimpan pada container
 * aplikasi (bukan statik), terikat id pengguna, dibangun ulang
 * bila pengguna berganti — tidak bocor antar-request/antar-test.
 */
class TenantContext
{
    public const MODE_TENANT = 'tenant';

    public const MODE_PLATFORM = 'platform';

    public const MODE_STANDALONE = 'standalone';

    public const MODE_GUEST = 'guest';

    private const CONTAINER_KEY = 'lentera.tenant-context';

    private readonly ?int $userId;

    private function __construct(
        private readonly ?User $user,
        private readonly string $mode,
        private readonly ?Tenant $tenant,
    ) {
        $this->userId = $user?->id;
    }

    public static function for(?User $user): self
    {
        if ($user === null) {
            return new self(null, self::MODE_GUEST, null);
        }

        // Aktor platform bukan pelaku bisnis tenant (lihat juga
        // BranchContext): identitas platform tidak pernah diterjemahkan
        // menjadi konteks tenant mana pun.
        if ($user->is_platform) {
            return new self($user, self::MODE_PLATFORM, null);
        }

        if ($user->tenant_id !== null) {
            // Validasi ke baris tenant nyata: tenant_id yang menggantung
            // (baris tenant hilang) menghasilkan konteks tenant TANPA
            // model — isValid() false dan semua penjaga menolak.
            return new self($user, self::MODE_TENANT, Tenant::query()->find($user->tenant_id));
        }

        return new self($user, self::MODE_STANDALONE, null);
    }

    /**
     * Konteks untuk pengguna yang sedang login pada request ini.
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

    public function mode(): string
    {
        return $this->mode;
    }

    public function isTenant(): bool
    {
        return $this->mode === self::MODE_TENANT;
    }

    public function isPlatform(): bool
    {
        return $this->mode === self::MODE_PLATFORM;
    }

    public function isStandalone(): bool
    {
        return $this->mode === self::MODE_STANDALONE;
    }

    public function isGuest(): bool
    {
        return $this->mode === self::MODE_GUEST;
    }

    /**
     * Baris tenant yang tervalidasi, bila ada. Null untuk platform,
     * standalone, tamu, dan tenant_id yang menggantung.
     */
    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    /** Id tenant tervalidasi (dari baris tenant nyata), bukan klaim mentah. */
    public function tenantId(): ?int
    {
        return $this->tenant?->id;
    }

    /** tenant_id mentah pada pengguna (diagnostik; BUKAN untuk otorisasi). */
    public function claimedTenantId(): ?int
    {
        return $this->user?->tenant_id;
    }

    /** Mode tenant DAN baris tenantnya benar-benar ada. */
    public function isValid(): bool
    {
        return $this->isTenant() && $this->tenant !== null;
    }

    /** Tenant teridentifikasi tetapi sedang dinonaktifkan platform. */
    public function isSuspended(): bool
    {
        return $this->isValid() && ! $this->tenant->is_active;
    }

    /** Tenant teridentifikasi dan aktif. */
    public function isActive(): bool
    {
        return $this->isValid() && $this->tenant->is_active;
    }

    /**
     * Tenant yang boleh dipakai berbisnis oleh aktor ini.
     *
     * @throws AuthorizationException untuk platform, standalone, tamu,
     *                                tenant tidak ditemukan, dan tenant nonaktif.
     */
    public function requireTenant(): Tenant
    {
        if (! $this->isActive()) {
            throw new AuthorizationException('Konteks tenant tidak tersedia atau tidak aktif untuk pengguna ini.');
        }

        return $this->tenant;
    }

    /**
     * Buktikan bahwa tenant sasaran adalah tenant konteks ini.
     * Dipakai konsumen tahap berikutnya sebelum menyentuh data
     * bertenant — penolakan adalah perilaku baku untuk SEMUA mode
     * selain tenant aktif yang cocok.
     *
     * @throws AuthorizationException
     */
    public function assertTenantAccess(?int $tenantId): void
    {
        if (! $this->isActive() || $tenantId === null || $tenantId !== $this->tenant->id) {
            throw new AuthorizationException('Tenant di luar konteks pengguna.');
        }
    }
}
