<?php

namespace App\Modules\Sales\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\OfflineDevice;
use Illuminate\Support\Str;

/**
 * Registri perangkat kasir offline (F5.3) — token perangkat hanya
 * disimpan sebagai hash sha256; token mentah tampil sekali saat
 * pendaftaran. Perangkat terikat satu cabang + satu akun kasir.
 */
class OfflineDeviceService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return array{device: OfflineDevice, plain_token: string} */
    public function register(User $actor, string $name, int $branchId, int $userId): array
    {
        $plain = Str::random(48);
        $device = OfflineDevice::create([
            'name' => $name,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'token_hash' => hash('sha256', $plain),
            'created_by' => $actor->id,
        ]);
        $this->audit->log(action: 'offline_device.register', subjectType: 'offline_device', subjectId: $device->id,
            newValues: ['name' => $name, 'branch_id' => $branchId, 'user_id' => $userId], actor: $actor);

        return ['device' => $device, 'plain_token' => $plain];
    }

    public function verify(string $plainToken): ?OfflineDevice
    {
        $device = OfflineDevice::where('token_hash', hash('sha256', $plainToken))->first();
        if ($device === null || ! $device->isActive()) {
            return null;
        }

        // LNT-006: token perangkat adalah kredensial atas nama akun
        // kasirnya — pengguna yang sudah dinonaktifkan tidak boleh
        // lagi bertindak lewat perangkat, sama seperti sesi web-nya
        // yang diputus EnsureUserIsActive. Fail-closed: akun hilang
        // atau nonaktif = perangkat tidak sah.
        $user = $device->user;
        if ($user === null || ! $user->is_active) {
            return null;
        }

        return $device;
    }

    /**
     * Cabut SEMUA perangkat aktif milik seorang pengguna (dipakai
     * saat pengguna dinonaktifkan — LNT-006 lapis kedua). Pencabutan
     * permanen: pengaktifan kembali pengguna tidak menghidupkan
     * token lama; perangkat harus didaftarkan ulang. Mengembalikan
     * jumlah perangkat yang dicabut.
     */
    public function revokeAllForUser(User $user, ?User $actor = null): int
    {
        $revoked = 0;
        OfflineDevice::where('user_id', $user->id)->whereNull('revoked_at')->get()
            ->each(function (OfflineDevice $device) use ($actor, &$revoked) {
                $device->update(['revoked_at' => now()]);
                $this->audit->log(action: 'offline_device.revoke', subjectType: 'offline_device', subjectId: $device->id,
                    newValues: ['reason' => 'user_deactivated'], actor: $actor);
                $revoked++;
            });

        return $revoked;
    }

    public function revoke(User $actor, OfflineDevice $device): void
    {
        if ($device->revoked_at === null) {
            $device->update(['revoked_at' => now()]);
            $this->audit->log(action: 'offline_device.revoke', subjectType: 'offline_device', subjectId: $device->id, actor: $actor);
        }
    }
}
