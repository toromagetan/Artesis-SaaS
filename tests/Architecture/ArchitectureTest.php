<?php

namespace Tests\Architecture;

use App\Support\PermissionRegistry;
use Tests\TestCase;

/**
 * Uji arsitektur Lentera (Blueprint G.2 job 2 + A.3).
 * Memindai pohon kode aplikasi — pelanggaran = suite merah.
 * F0.3: bcmath terlarang, string izin harus terdaftar, branch_id tidak
 * boleh bersumber dari request pada controller modul.
 */
class ArchitectureTest extends TestCase
{
    /** @return string[] */
    private function phpFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            $path = base_path($dir);
            if (! is_dir($path)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($it as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function test_no_bcmath_function_calls_in_application_code(): void
    {
        $pattern = '/\b(bcadd|bcsub|bcmul|bcdiv|bccomp|bcmod|bcpow|bcsqrt|bcscale|bcround|bcdivmod)\s*\(/';
        $violations = [];

        foreach ($this->phpFiles(['app', 'routes', 'config', 'database']) as $file) {
            if (preg_match($pattern, file_get_contents($file))) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations, 'bcmath DILARANG (Blueprint A.1): '.implode(', ', $violations));
    }

    public function test_permission_strings_used_in_code_exist_in_registry(): void
    {
        $patterns = [
            '/permission:([a-z][a-z0-9_.]*)/',
            "/hasPermission\\(\\s*'([a-z][a-z0-9_.]*)'/",
            "/->middleware\\(\\s*'permission:([a-z][a-z0-9_.]*)'/",
        ];
        $used = [];

        foreach ($this->phpFiles(['app', 'routes']) as $file) {
            $code = file_get_contents($file);
            foreach ($patterns as $pattern) {
                if (preg_match_all($pattern, $code, $m)) {
                    foreach ($m[1] as $string) {
                        $used[$string] = $file;
                    }
                }
            }
        }

        foreach ($used as $string => $file) {
            $this->assertTrue(
                PermissionRegistry::exists($string),
                "String izin tidak terdaftar di registry: {$string} (dipakai di {$file})",
            );
        }
    }

    public function test_module_controllers_never_take_branch_id_from_request(): void
    {
        $violations = [];
        $controllers = glob(base_path('app/Modules/*/Http/Controllers/*.php')) ?: [];

        foreach ($controllers as $file) {
            foreach (file($file) as $i => $line) {
                if (str_contains($line, '$request') && str_contains($line, 'branch_id')) {
                    $violations[] = $file.':'.($i + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            'branch_id WAJIB dari BranchContext, bukan request (A.3.5): '.implode(', ', $violations),
        );
    }

    public function test_registry_has_expected_size(): void
    {
        // Bagian C.1 mengunci jumlah string izin; perubahan harus
        // disengaja (revisi Blueprint), bukan selipan diam-diam.
        // 75 → 78 pada audit 23 Okt 2026 (A3): + lab.templates.manage,
        // pr.cancel, sales.exceptions — izin khusus untuk mutasi berat
        // yang sebelumnya berpagar izin level-lihat/buat.
        $this->assertCount(75, PermissionRegistry::all());
    }

    public function test_no_float_casts_in_application_code(): void
    {
        $violations = [];
        foreach ($this->phpFiles(['app', 'routes', 'config', 'database']) as $file) {
            if (preg_match('/\((float|double)\)/', file_get_contents($file))) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations, 'Cast float dilarang (Blueprint A.5): '.implode(', ', $violations));
    }

    public function test_blade_views_contain_no_business_logic(): void
    {
        // A.3 butir 6: tidak ada logika bisnis di Blade — tidak ada akses
        // DB langsung, tidak ada pembuatan/pembaruan model dari view.
        $violations = [];
        $views = [];
        $path = resource_path('views');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[] = $file->getPathname();
            }
        }

        foreach ($views as $file) {
            $code = file_get_contents($file);
            if (preg_match('/\bDB::|::create\(|::query\(|->update\(|->delete\(|->save\(/', $code)) {
                $violations[] = $file;
            }
        }

        $this->assertSame([], $violations, 'Logika bisnis terlarang di Blade: '.implode(', ', $violations));
    }

    public function test_locale_files_have_matching_keys(): void
    {
        // ADR-011 + M2 (empat bahasa id/en/ms/zh): kunci semua locale yang
        // didukung harus selalu berpasangan dengan id, termasuk paket JSON.
        $locales = array_diff(config('app.supported_locales', ['id', 'en']), ['id']);
        foreach (['ui', 'auth', 'validation', 'pagination', 'passwords'] as $file) {
            $id = $this->flattenKeys(require lang_path("id/{$file}.php"));
            sort($id);
            foreach ($locales as $locale) {
                $other = $this->flattenKeys(require lang_path("{$locale}/{$file}.php"));
                sort($other);
                $this->assertSame($id, $other, "Kunci lang/{$file} tidak berpasangan antara id dan {$locale}.");
            }
        }

        $idJson = array_keys(json_decode(file_get_contents(lang_path('id.json')), true));
        sort($idJson);
        foreach ($locales as $locale) {
            $otherJson = array_keys(json_decode(file_get_contents(lang_path("{$locale}.json")), true));
            sort($otherJson);
            $this->assertSame($idJson, $otherJson, "Kunci lang JSON tidak berpasangan antara id dan {$locale}.");
        }
    }

    public function test_foundation_translation_keys_resolve_to_real_strings(): void
    {
        // Menjaga tabrakan kunci (skalar vs array dengan nama sama) yang
        // membuat __() mengembalikan nama kunci — paritas id/en saja tidak
        // menangkapnya karena tabrakan terjadi identik di kedua berkas.
        foreach (['ui.search_page.title', 'ui.print_doc.user_list', 'ui.print_doc.user_profile', 'ui.users.last_login', 'ui.users.detail', 'ui.branches.detail'] as $key) {
            $this->assertNotSame($key, __($key), "Kunci {$key} tidak menghasilkan string terjemahan.");
            $this->assertNotSame('', trim((string) __($key)));
        }
        $this->assertIsString(__('ui.search'));
        $this->assertIsString(__('ui.print'));
    }

    /** @return string[] */
    private function flattenKeys(array $values, string $prefix = ''): array
    {
        $keys = [];
        foreach ($values as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }
}
