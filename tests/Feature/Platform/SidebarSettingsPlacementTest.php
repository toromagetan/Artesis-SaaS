<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penempatan menu pengaturan (koreksi owner 2026-10-22, diselaraskan
 * ke cermin menu Artesis A6 — keputusan owner 2026-11-14 §190/§191):
 * Gateway WA, Asisten AI, Backup, Token API & Perangkat Offline
 * berada di grup "Pengaturan"; WhatsApp operasional (Inbox dsb.)
 * berada di grup "Transaksi" — pengaturan BUKAN lagi terselip di
 * submenu WhatsApp. Log Aktivitas berdiri sendiri di antara Stok
 * dan Laporan, persis posisi Artesis.
 */
class SidebarSettingsPlacementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$branch->id]);
    }

    public function test_settings_links_live_under_settings_group_not_whatsapp(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent();

        $trxStart = strpos($html, __('ui.nav.group_transactions'));
        $settingsStart = strpos($html, htmlspecialchars(__('ui.nav.settings'), ENT_QUOTES, 'UTF-8'));
        $reportsStart = strpos($html, htmlspecialchars(__('ui.nav.reports'), ENT_QUOTES, 'UTF-8'));
        $this->assertNotFalse($trxStart, 'Grup Transaksi harus ada.');
        $this->assertNotFalse($settingsStart, 'Grup Pengaturan harus ada.');
        $this->assertNotFalse($reportsStart, 'Grup Laporan harus ada.');
        $this->assertLessThan($settingsStart, $trxStart);

        $trxSection = substr($html, $trxStart, $settingsStart - $trxStart);
        $settingsSection = substr($html, $settingsStart);

        foreach ([route('wa-gateway.edit'), route('ai-setting.edit'), route('backups.index')] as $url) {
            $this->assertStringNotContainsString($url, $trxSection, "URL pengaturan {$url} tidak boleh di grup Transaksi (submenu WhatsApp).");
            $this->assertStringContainsString($url, $settingsSection, "URL pengaturan {$url} harus ada di grup Pengaturan.");
        }

        // Log Aktivitas berdiri sendiri SEBELUM grup Laporan (posisi Artesis),
        // bukan di dalam grup Transaksi maupun Pengaturan.
        $auditPos = strpos($html, route('audit-logs.index'));
        $this->assertNotFalse($auditPos, 'Menu Log Aktivitas harus ada.');
        $this->assertLessThan($reportsStart, $auditPos);
        $this->assertStringNotContainsString(route('audit-logs.index'), $settingsSection);

        // Menu WhatsApp operasional tinggal di grup Transaksi.
        $this->assertStringContainsString(route('wa-inbox.index'), $trxSection);
    }

    public function test_settings_links_hidden_without_settings_permission(): void
    {
        $html = $this->actingAs($this->kasir)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('wa-gateway.edit'), $html);
        $this->assertStringNotContainsString(route('ai-setting.edit'), $html);
        $this->assertStringNotContainsString(route('backups.index'), $html);
    }
}
