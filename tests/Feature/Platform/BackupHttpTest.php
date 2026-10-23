<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\BackupService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bukti HTTP untuk aksi backup yang sebelumnya hanya teruji di
 * level layanan: buat manual, unduh, hapus, dan sakelar offsite
 * (satu-satunya penulis backup.offsite_enabled).
 */
class BackupHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->service = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->service->entries() as $entry) {
            @unlink($this->service->directory().'/'.$entry['name']);
        }
        parent::tearDown();
    }

    public function test_store_download_destroy_over_http(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backups.store'))
            ->assertRedirect()
            ->assertSessionHas('status');

        $entries = $this->service->entries();
        $this->assertCount(1, $entries);
        $name = $entries[0]['name'];

        $this->actingAs($this->owner)
            ->get(route('backups.download', ['berkas' => $name]))
            ->assertOk();

        $this->actingAs($this->owner)
            ->post(route('backups.destroy'), ['berkas' => $name])
            ->assertRedirect();
        $this->assertCount(0, $this->service->entries());
    }

    public function test_offsite_toggle_persists_setting_and_audits(): void
    {
        $this->actingAs($this->owner)
            ->post(route('backups.offsite'), ['aktif' => '1'])
            ->assertRedirect();

        $this->assertSame('1', Setting::value('backup.offsite_enabled'));
        $this->assertTrue(AuditLog::where('action', 'backup.offsite_toggle')->exists());

        $this->actingAs($this->owner)->post(route('backups.offsite'), []);
        $this->assertSame('0', Setting::value('backup.offsite_enabled'));
    }

    public function test_user_without_settings_permission_is_forbidden(): void
    {
        $kasir = User::factory()->create();
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());

        $this->actingAs($kasir)->post(route('backups.store'))->assertForbidden();
        $this->actingAs($kasir)->post(route('backups.offsite'), ['aktif' => '1'])->assertForbidden();
    }
}
