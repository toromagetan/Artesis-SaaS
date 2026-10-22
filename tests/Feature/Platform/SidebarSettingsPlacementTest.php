<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penempatan menu pengaturan (koreksi owner 2026-10-22): Gateway WA,
 * Asisten AI, Log Aktivitas, Backup, Token API & Perangkat Offline
 * berada di grup "Master & Pengaturan" di bawah induk "Pengaturan" —
 * BUKAN lagi terselip di submenu WhatsApp grup Komunikasi.
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

    public function test_settings_links_live_under_master_settings_group_not_whatsapp(): void
    {
        $html = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent();

        $commStart = strpos($html, __('ui.nav.communication'));
        $masterStart = strpos($html, htmlspecialchars(__('ui.nav.group_master_settings'), ENT_QUOTES, 'UTF-8'));
        $this->assertNotFalse($commStart, 'Grup Komunikasi harus tetap ada.');
        $this->assertNotFalse($masterStart, 'Grup Master & Pengaturan harus ada.');
        $this->assertLessThan($masterStart, $commStart);

        $commSection = substr($html, $commStart, $masterStart - $commStart);
        $masterSection = substr($html, $masterStart);

        foreach ([route('wa-gateway.edit'), route('ai-setting.edit'), route('audit-logs.index'), route('backups.index')] as $url) {
            $this->assertStringNotContainsString($url, $commSection, "URL pengaturan {$url} tidak boleh lagi di grup Komunikasi.");
            $this->assertStringContainsString($url, $masterSection, "URL pengaturan {$url} harus ada di grup Master & Pengaturan.");
        }

        // Induk "Pengaturan" tampil di grup Master & Pengaturan.
        $this->assertStringContainsString(__('ui.nav.settings'), $masterSection);
        // Menu WhatsApp murni operasional tetap di grup Komunikasi.
        $this->assertStringContainsString(route('wa-inbox.index'), $commSection);
    }

    public function test_settings_links_hidden_without_settings_permission(): void
    {
        $html = $this->actingAs($this->kasir)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(route('wa-gateway.edit'), $html);
        $this->assertStringNotContainsString(route('ai-setting.edit'), $html);
        $this->assertStringNotContainsString(route('backups.index'), $html);
    }
}
