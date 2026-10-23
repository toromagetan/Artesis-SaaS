<?php

namespace Tests\Concurrency;

use PDO;
use RuntimeException;

/**
 * Isolasi database untuk test konkurensi (LNT-007).
 *
 * Dulu kedua kelas test konkurensi memakai nama tetap
 * "lentera_conc" dan meng-DROP-nya tanpa syarat di setUp —
 * satu run dapat menghancurkan database milik run lain (atau
 * milik developer) yang kebetulan memakai nama sama. Sekarang:
 *
 * - Nama dibuat UNIK per proses: lentera_conc_<pid>_<acak>.
 * - assertSafeName() menolak nama apa pun di luar pola itu
 *   (termasuk "lentera_conc" polos, "lentera", "lentera_test").
 * - create() membuat database BARU tanpa DROP lebih dulu —
 *   nama yang sudah ada = gagal keras, bukan ditimpa.
 * - Kepemilikan dibuktikan tabel penanda __conc_owner berisi
 *   token acak milik proses pembuat; dropOwned() hanya
 *   menghapus database yang tokennya cocok.
 *
 * Sisa database dari proses yang terbunuh (tearDown tak
 * sempat jalan) tetap bernama unik + berpenanda, jadi mudah
 * dikenali dan aman dihapus manual.
 */
final class ConcurrencyDatabase
{
    private const PATTERN = '/^lentera_conc_[a-z0-9_]+$/';

    public static function uniqueName(): string
    {
        $name = 'lentera_conc_'.getmypid().'_'.bin2hex(random_bytes(4));
        self::assertSafeName($name);

        return $name;
    }

    /** @throws RuntimeException bila nama di luar pola test konkurensi */
    public static function assertSafeName(string $name): void
    {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new RuntimeException("Nama database test konkurensi tidak aman: {$name}");
        }
    }

    /**
     * Buat database baru + beri akses pengguna aplikasi + tanam
     * penanda kepemilikan. Mengembalikan token kepemilikan.
     *
     * @throws RuntimeException bila nama tidak aman / sudah ada
     */
    public static function create(PDO $admin, string $name, ?string $appUser): string
    {
        self::assertSafeName($name);
        // TANPA "IF NOT EXISTS": nama unik yang bertabrakan harus
        // gagal keras di sini, tidak pernah menimpa yang sudah ada.
        $admin->exec('CREATE DATABASE '.$name);
        if ($appUser !== null && $appUser !== 'root' && $appUser !== '') {
            foreach (['127.0.0.1', 'localhost', '%'] as $host) {
                try {
                    $admin->exec("GRANT ALL ON {$name}.* TO '{$appUser}'@'{$host}'");
                } catch (\Throwable) {
                    // akun host itu tidak ada — lewati
                }
            }
        }
        $token = bin2hex(random_bytes(16));
        $admin->exec("CREATE TABLE {$name}.__conc_owner (token VARCHAR(64) NOT NULL)");
        $admin->exec("INSERT INTO {$name}.__conc_owner (token) VALUES ('{$token}')");

        return $token;
    }

    /**
     * Hapus HANYA database yang penanda kepemilikannya cocok
     * dengan token proses ini.
     *
     * @throws RuntimeException bila nama tidak aman / penanda tak cocok
     */
    public static function dropOwned(PDO $admin, string $name, string $token): void
    {
        self::assertSafeName($name);
        $stmt = $admin->query("SELECT token FROM {$name}.__conc_owner LIMIT 1");
        $row = $stmt === false ? false : $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || ! hash_equals($token, (string) $row['token'])) {
            throw new RuntimeException("Menolak menghapus {$name}: penanda kepemilikan tidak cocok.");
        }
        $admin->exec('DROP DATABASE '.$name);
    }
}
