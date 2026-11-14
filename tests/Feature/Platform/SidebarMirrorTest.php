<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A6 — sidebar cermin Artesis (keputusan owner 2026-11-14
 * §190/§191): urutan kelompok Master → Transaksi → Stok → Log
 * Aktivitas → Laporan → Akuntansi → Pengaturan; Accounting menu
 * utama sendiri; tidak ada tautan lama yang hilang.
 */
class SidebarMirrorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_group_order_mirrors_artesis(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent();

        $positions = [];
        foreach (['group_master', 'group_transactions', 'reports', 'accounting', 'settings'] as $key) {
            $pos = strpos($html, __('ui.nav.'.$key));
            $this->assertNotFalse($pos, "Kelompok {$key} harus tampil.");
            $positions[$key] = $pos;
        }
        $this->assertLessThan($positions['group_transactions'], $positions['group_master']);
        $this->assertLessThan($positions['reports'], $positions['group_transactions']);
        $this->assertLessThan($positions['accounting'], $positions['reports']);
        $this->assertLessThan($positions['settings'], $positions['accounting']);

        // POS Kasir & Produk tampil dengan label cermin.
        $this->assertStringContainsString(__('ui.nav.pos_cashier'), $html);
        $this->assertStringContainsString(route('sales.create'), $html);
        $this->assertStringContainsString(route('products.index', 'lens'), $html);
        $this->assertStringContainsString(route('stock.lens-matrix'), $html);
        $this->assertStringContainsString(route('profile.edit'), $html);
    }

    public function test_no_legacy_group_labels_remain(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent();
        $start = strpos($html, '<aside');
        $end = strpos($html, '</aside>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $sidebar = substr($html, $start, $end - $start);
        // Pencocokan judul kelompok persis (label lama "Operasional"
        // adalah substring sah dari "Laporan Operasional").
        foreach (['group_operations', 'group_master_settings', 'group_purchasing_inventory', 'communication'] as $key) {
            $this->assertStringNotContainsString('>'.__('ui.nav.'.$key).'</p>', $sidebar, "Judul kelompok lama {$key} tidak boleh tampil.");
        }
    }
}
