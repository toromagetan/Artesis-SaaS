<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Supplier;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penjaga regresi audit tombol Aksi 2026-10-22: panel ketiga komponen
 * dropdown bersama (Aksi/Cetak/aksi-baris) WAJIB dirender sebagai panel
 * fixed berpenyangga posisi (panelStyle) — bukan lagi absolute di dalam
 * kartu overflow-hidden yang memotong bagian bawah panel pada baris
 * terakhir setiap tabel.
 */
class DropdownPanelPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_list_page_panels_use_fixed_positioning(): void
    {
        Supplier::create(['name' => 'PT Panel Uji', 'is_active' => true]);

        $html = $this->actingAs($this->owner)->get(route('suppliers.index'))
            ->assertOk()
            ->getContent();

        // Dropdown Aksi level halaman + aksi per baris sama-sama panel fixed.
        $this->assertStringContainsString('data-lentera="dropdown-aksi"', $html);
        $this->assertStringContainsString('data-lentera="row-actions"', $html);
        $this->assertStringContainsString('fixed z-50', $html);
        $this->assertStringContainsString('panelStyle', $html);
        // Pola lama yang terpotong tidak boleh kembali.
        $this->assertStringNotContainsString('absolute right-0 z-20', $html);
    }

    public function test_detail_page_panel_uses_fixed_positioning(): void
    {
        $supplier = Supplier::create(['name' => 'PT Cetak Uji', 'is_active' => true]);

        $html = $this->actingAs($this->owner)->get(route('suppliers.show', $supplier))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-lentera="dropdown-aksi"', $html);
        $this->assertStringContainsString('fixed z-50', $html);
        $this->assertStringNotContainsString('absolute right-0 z-20', $html);
    }
}
