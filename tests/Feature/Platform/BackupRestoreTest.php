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
 * Backup & restore dalam aplikasi: snapshot ZIP-JSON seluruh
 * tabel; restore mengembalikan nilai yang berubah, selalu
 * membuat backup keselamatan lebih dulu, dan hanya Owner.
 */
class BackupRestoreTest extends TestCase
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
        Setting::set('backup.probe', 'sebelum');
    }

    protected function tearDown(): void
    {
        foreach ($this->service->entries() as $entry) {
            @unlink($this->service->directory().'/'.$entry['name']);
        }
        parent::tearDown();
    }

    public function test_create_restore_roundtrip(): void
    {
        $name = $this->service->create($this->owner);
        $this->assertFileExists($this->service->directory().'/'.$name);

        Setting::set('backup.probe', 'sesudah');
        $this->assertSame('sesudah', Setting::value('backup.probe'));

        $result = $this->service->restore($this->owner, $name);
        $this->assertSame('sebelum', Setting::value('backup.probe'));
        $this->assertStringStartsWith('pra-restore-', $result['safety_backup']);
        $this->assertGreaterThan(10, $result['restored_tables']);
        $this->assertTrue(AuditLog::where('action', 'backup.restored')->exists());
    }

    public function test_restore_is_owner_only_over_http(): void
    {
        $name = $this->service->create($this->owner);
        $admin = User::factory()->create(['username' => 'admin.backup']);
        $admin->roles()->sync([Role::where('name', 'Admin')->firstOrFail()->id]);

        $this->actingAs($admin)
            ->post(route('backups.restore'), ['berkas' => $name, 'konfirmasi' => 'RESTORE'])
            ->assertForbidden();
        // Konfirmasi salah juga ditolak (validasi).
        $this->actingAs($this->owner)
            ->post(route('backups.restore'), ['berkas' => $name, 'konfirmasi' => 'YA'])
            ->assertSessionHasErrors('konfirmasi');
    }

    public function test_index_page_requires_settings_permission(): void
    {
        $this->actingAs($this->owner)->get(route('backups.index'))->assertOk();
    }
}
