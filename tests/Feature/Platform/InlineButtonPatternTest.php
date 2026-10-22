<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Supplier;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga ADR-018 (keputusan owner 2026-10-22): perintah TIDAK lagi
 * disembunyikan di dropdown Aksi/Cetak — seluruh perintah dirender
 * sebagai tombol tersendiri yang selalu terlihat dalam kelompok sejajar
 * AksiBar (kepala layar) dan AksiBaris (per baris tabel). Komponen
 * dropdown lama (DropdownAksi/DropdownCetak/RowActions) tidak boleh
 * kembali dalam bentuk apa pun.
 */
class InlineButtonPatternTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_list_page_renders_inline_button_groups(): void
    {
        Supplier::create(['name' => 'PT Sejajar Uji', 'is_active' => true]);

        $html = $this->actingAs($this->owner)->get(route('suppliers.index'))
            ->assertOk()
            ->getContent();

        // Kelompok sejajar kepala layar + per baris sama-sama hadir;
        // tombol "Buat Supplier" terlihat langsung sebagai tombol primer.
        $this->assertStringContainsString('data-lentera="aksi-bar"', $html);
        $this->assertStringContainsString('data-lentera="aksi-baris"', $html);
        $this->assertStringContainsString('Buat Supplier', $html);
        // Tidak ada lagi dropdown perintah / panel melayang.
        $this->assertStringNotContainsString('data-lentera="dropdown-aksi"', $html);
        $this->assertStringNotContainsString('data-lentera="dropdown-cetak"', $html);
        $this->assertStringNotContainsString('data-lentera="row-actions"', $html);
        $this->assertStringNotContainsString('panelStyle', $html);
    }

    public function test_detail_page_renders_inline_buttons(): void
    {
        $supplier = Supplier::create(['name' => 'PT Sejajar Detail', 'is_active' => true]);

        $html = $this->actingAs($this->owner)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-lentera="aksi-bar"', $html);
        $this->assertStringNotContainsString('data-lentera="dropdown-aksi"', $html);
        $this->assertStringNotContainsString('data-lentera="row-actions"', $html);
    }

    public function test_old_dropdown_components_do_not_exist(): void
    {
        foreach (['dropdown-aksi', 'dropdown-cetak', 'row-actions'] as $component) {
            $this->assertFileDoesNotExist(
                resource_path("views/components/lentera/{$component}.blade.php"),
                "Komponen dropdown {$component} tidak boleh kembali (ADR-018)."
            );
        }

        // Tidak ada view yang masih memanggil komponen dropdown perintah.
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );
        foreach ($views as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $content = file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('x-lentera.dropdown-aksi', $content, $file->getPathname());
            $this->assertStringNotContainsString('x-lentera.dropdown-cetak', $content, $file->getPathname());
            $this->assertStringNotContainsString('x-lentera.row-actions', $content, $file->getPathname());
        }
    }
}
