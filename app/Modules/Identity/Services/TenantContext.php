<?php

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Konteks tenant aktor (fondasi R-3; pasangan BranchContext).
 *
 * SATU-SATUNYA sumber kebenaran untuk "tenant siapa pengguna ini"
 * pada lapisan fondasi. Konteks SELALU diturunkan dari pengguna
 * terautentikasi dan relasi tenant_id miliknya yang tervalidasi ke
 * baris tenant nyata — TIDAK PERNAH dari input request (URL, query,
 * header, payload, sesi): kelas ini bahkan tidak membaca request.
 *
 * Lima mode eksplisit (akun tanpa tenant_id BUKAN otomatis
 * platform, dan BUKAN otomatis standalone — keduanya dibedakan
 * tegas):
 * - tenant     : pengguna bisnis dengan tenant_id yang sah.
 * - platform   : aktor platform (is_platform) — BUKAN identitas
 *                tenant; tidak pernah memperoleh konteks tenant.
 * - standalone : pengguna tanpa tenant pada instalasi yang BENAR-
 *                BENAR individual (belum ada satu pun tenant
 *                terdaftar — prinsip yang sama dengan penjaga
 *                BackupPolicy pada R-1).
 * - unassigned : pengguna tanpa tenant pada instalasi BERSAMA
 *                (tenant sudah terdaftar) — bukan operator
 *                instalasi, bukan anggota tenant; semua primitif
 *                bisnis menolaknya.
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

    public const MODE_UNASSIGNED = 'unassigned';

    public const MODE_GUEST = 'guest';

    private const CONTAINER_KEY = 'lentera.tenant-context';

    private const SYSTEM_CONTAINER_KEY = 'lentera.system-context';

    private const GUEST_HTTP_CONTAINER_KEY = 'lentera.guest-http';

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

        // Tanpa tenant_id: standalone HANYA bila instalasi benar-benar
        // individual (belum ada tenant terdaftar). Begitu instalasi
        // melayani tenant, akun tanpa tenant yang bukan platform
        // adalah UNASSIGNED — tenant_id NULL tidak boleh menjadi
        // kesimpulan "operator instalasi" (audit R-3, menyelaraskan
        // prinsip penjaga NULL pada BackupPolicy R-1).
        return Tenant::count() === 0
            ? new self($user, self::MODE_STANDALONE, null)
            : new self($user, self::MODE_UNASSIGNED, null);
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

    /**
     * Jalankan callback dalam konteks SISTEM eksplisit: proses
     * terpercaya tingkat instalasi (perintah konsol terjadwal, job
     * antrean, seeder) yang memang bekerja lintas tenant. Inilah
     * satu-satunya cara query model TenantScoped/BranchScoped tanpa
     * aktor memperoleh akses tak terbatas — ketiadaan auth() saja
     * TIDAK lagi cukup, supaya guest HTTP gagal-tertutup (audit
     * lanjutan temuan 8). Penanda disimpan pada container dan
     * dipulihkan sesudah callback, termasuk bila callback melempar.
     */
    public static function asSystem(callable $callback): mixed
    {
        $app = app();
        $previous = $app->bound(self::SYSTEM_CONTAINER_KEY)
            ? $app->make(self::SYSTEM_CONTAINER_KEY)
            : null;
        $app->instance(self::SYSTEM_CONTAINER_KEY, true);

        try {
            return $callback();
        } finally {
            if ($previous === null) {
                $app->forgetInstance(self::SYSTEM_CONTAINER_KEY);
            } else {
                $app->instance(self::SYSTEM_CONTAINER_KEY, $previous);
            }
        }
    }

    /** Apakah eksekusi saat ini berada dalam konteks sistem eksplisit. */
    public static function isSystemContext(): bool
    {
        return app()->bound(self::SYSTEM_CONTAINER_KEY)
            && app()->make(self::SYSTEM_CONTAINER_KEY) === true;
    }

    /**
     * Tandai request HTTP tamu (tanpa pengguna terautentikasi).
     * Dipasang middleware web/api pada SETIAP request anonim —
     * inilah pembeda "guest HTTP" dari pemanggilan in-process
     * terpercaya (layanan aplikasi menerima aktor secara eksplisit
     * dan tidak selalu memasang auth()). TenantScope/BranchScope
     * gagal-tertutup selama penanda ini aktif.
     */
    public static function markGuestHttp(): void
    {
        app()->instance(self::GUEST_HTTP_CONTAINER_KEY, true);
    }

    public static function forgetGuestHttp(): void
    {
        if (app()->bound(self::GUEST_HTTP_CONTAINER_KEY)) {
            app()->forgetInstance(self::GUEST_HTTP_CONTAINER_KEY);
        }
    }

    public static function isGuestHttp(): bool
    {
        return app()->bound(self::GUEST_HTTP_CONTAINER_KEY)
            && app()->make(self::GUEST_HTTP_CONTAINER_KEY) === true;
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

    public function isUnassigned(): bool
    {
        return $this->mode === self::MODE_UNASSIGNED;
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
     * Terapkan pagar tenant pada query berkolom tenant_id
     * (daftar pengguna, daftar kunci periode, daftar pilihan
     * sejenis) — SATU kebijakan untuk kelima mode, dipakai semua
     * modul agar tidak ada daftar yang menyaring hanya untuk
     * mode tenant/standalone lalu bocor pada mode lain
     * (putaran koreksi audit independen, temuan 3):
     * - tenant sah : hanya baris tenantnya;
     * - standalone : hanya baris tenant_id NULL (milik instalasi);
     * - platform   : tanpa penyaringan (pengawasan platform);
     * - unassigned / tamu / tenant menggantung: hasil KOSONG
     *   (gagal-tertutup — bukan seluruh baris).
     */
    public function constrainByTenant(EloquentBuilder|QueryBuilder $query, string $column = 'tenant_id'): EloquentBuilder|QueryBuilder
    {
        if ($this->isValid()) {
            $query->where($column, $this->tenantId());
        } elseif ($this->isStandalone()) {
            $query->whereNull($column);
        } elseif ($this->isPlatform()) {
            // Pengawasan platform: tanpa penyaringan tenant.
        } else {
            $query->whereIn($column, [0]);
        }

        return $query;
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
