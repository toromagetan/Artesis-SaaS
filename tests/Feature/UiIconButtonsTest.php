<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tombol aksi per baris ikon-saja (perintah owner 2026-11-14,
 * semua modul): tombol tetap hadir & terlihat sebagai tombol,
 * berikon SVG, dengan tooltip (title) dan label sr-only agar
 * maknanya tidak hilang (semangat ADR-018 dipertahankan).
 */
class UiIconButtonsTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_row_actions_are_icon_only_with_labels(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->actingAs($owner)->post('/katalog/frame', [
            'sku' => 'FRM-IKON', 'name' => 'Frame Ikon', 'brand' => 'Acme',
            'cost_price' => 1000, 'sale_price' => 2000,
        ])->assertRedirect();

        $html = $this->actingAs($owner)->get(route('products.index', 'frame'))->assertOk()->getContent();

        // Judul kolom aksi tetap ada; tombol detail memakai ikon mata + title.
        $this->assertStringContainsString('title="'.__('ui.products.detail').'"', $html);
        $this->assertStringContainsString('<span class="sr-only">'.__('ui.products.detail').'</span>', $html);
        $this->assertStringContainsString('title="'.__('ui.edit').'"', $html);
    }

    public function test_users_row_actions_are_icon_only_with_labels(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();

        $html = $this->actingAs($owner)->get(route('users.index'))->assertOk()->getContent();

        $this->assertStringContainsString('title="'.__('ui.users.detail').'"', $html);
        $this->assertStringContainsString('title="'.__('ui.deactivate').'"', $html);
        $this->assertStringContainsString('<span class="sr-only">'.__('ui.deactivate').'</span>', $html);
    }
}
