<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Services\BackupService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Backup otomatis terjadwal (audit 23 Okt 2026): perintah harian
 * membuat backup berawalan 'auto' sebagai aktor sistem dan
 * retensi hanya menghapus berkas otomatis tertua — backup manual
 * tidak pernah disentuh.
 */
class ScheduledBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
    }

    protected function tearDown(): void
    {
        $service = app(BackupService::class);
        foreach ($service->entries() as $entry) {
            @unlink($service->directory().'/'.$entry['name']);
        }
        parent::tearDown();
    }

    public function test_scheduled_command_creates_system_backup(): void
    {
        $this->assertSame(0, Artisan::call('lentera:backup-otomatis'));

        $service = app(BackupService::class);
        $autos = array_filter($service->entries(), fn ($e) => str_starts_with($e['name'], 'auto-'));
        $this->assertCount(1, $autos);
        $this->assertTrue(AuditLog::where('action', 'backup.auto_created')->exists());
    }

    public function test_retention_prunes_only_old_auto_backups(): void
    {
        $service = app(BackupService::class);
        $dir = $service->directory();
        // 16 backup otomatis lama (mtime menaik) + 1 manual.
        for ($i = 1; $i <= 16; $i++) {
            $file = $dir.'/auto-20261001-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT).'.zip';
            file_put_contents($file, 'x');
            touch($file, now()->subDays(30)->addMinutes($i)->timestamp);
        }
        file_put_contents($dir.'/backup-manual.zip', 'x');

        $this->assertSame(2, $service->pruneAuto(14));
        $names = array_column($service->entries(), 'name');
        $this->assertCount(15, $names); // 14 auto tersimpan + 1 manual
        $this->assertContains('backup-manual.zip', $names);
    }
}
