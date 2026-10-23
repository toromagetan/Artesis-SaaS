<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjaga kontrak UI di CI (audit 23 Okt 2026 D6). Berbeda dari
 * UiPatternTest yang menguji halaman ter-render, kelas ini
 * memindai berkas view secara statis agar pelanggaran kontrak
 * gagal di CI sebelum sempat tampil:
 *  - komponen dropdown perintah tidak boleh kembali (ADR-018) —
 *    x-dropdown hanya sah di layouts (menu pengguna & bahasa);
 *  - judul tepat satu kali: maksimal satu <h1> per berkas view;
 *  - warna hex hanya dari token desain yang terdaftar — hex baru
 *    di luar daftar ini berarti warna di luar token (Blueprint
 *    F.2.0) dan harus ditolak/didaftarkan secara sadar;
 *  - setiap daftar index memiliki keadaan kosong (pengecualian
 *    terdokumentasi: halaman Asisten adalah percakapan, bukan
 *    daftar);
 *  - pencarian global tetap tertanam di layout aplikasi.
 */
class UiContractGuardTest extends TestCase
{
    /** Hex token desain yang sah (emerald/emas/netral Lentera). */
    private const ALLOWED_HEX = [
        '#022c22', '#064e3b', '#065f46', '#047857', '#059669', '#10b981',
        '#34d399', '#6ee7b7', '#a7f3d0', '#d1fae5', '#ecfdf5',
        '#c9a227', '#e7c65a', '#f7f8f7',
        '#111827', '#1f2937', '#374151', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb', '#f3f4f6', '#f9fafb',
        '#ffffff', '#000000', '#fef2f2', '#fee2e2', '#dc2626', '#fffbeb', '#fef3c7', '#d97706',
    ];

    /** @return array<int, string> */
    private function bladeFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    public function test_no_dropdown_components_outside_layouts(): void
    {
        foreach ($this->bladeFiles() as $path) {
            if (str_contains($path, '/layouts/')) {
                continue;
            }
            $this->assertStringNotContainsString('<x-dropdown', file_get_contents($path), $path);
        }
    }

    public function test_at_most_one_h1_per_blade_file(): void
    {
        foreach ($this->bladeFiles() as $path) {
            $count = substr_count((string) file_get_contents($path), '<h1');
            $this->assertLessThanOrEqual(1, $count, $path." memuat {$count} <h1>");
        }
    }

    public function test_no_hex_colors_outside_token_allowlist(): void
    {
        foreach ($this->bladeFiles() as $path) {
            preg_match_all('/#[0-9a-fA-F]{6}\b/', (string) file_get_contents($path), $m);
            foreach ($m[0] as $hex) {
                $this->assertContains(strtolower($hex), self::ALLOWED_HEX, $path." memakai hex di luar token: {$hex}");
            }
        }
    }

    public function test_index_lists_have_empty_state(): void
    {
        $exceptions = ['assistant/index.blade.php']; // percakapan, bukan daftar
        foreach ($this->bladeFiles() as $path) {
            if (! str_ends_with($path, 'index.blade.php')) {
                continue;
            }
            foreach ($exceptions as $exception) {
                if (str_ends_with($path, $exception)) {
                    continue 2;
                }
            }
            $content = (string) file_get_contents($path);
            $this->assertTrue(
                str_contains($content, 'empty-state') || str_contains($content, '@forelse'),
                $path.' tidak memiliki keadaan kosong'
            );
        }
    }

    public function test_app_layout_keeps_global_search(): void
    {
        $layout = (string) file_get_contents(resource_path('views/layouts/app.blade.php'));
        $this->assertStringContainsString('<x-lentera.global-search />', $layout);
    }
}
