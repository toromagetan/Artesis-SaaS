<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Services\BackupService;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Peringatan operasional dashboard + verifikasi backup
 * (PRD-Detail 12.6 — sisa terbangun 2026-10-23): backup hanya
 * dianggap berhasil bila LOLOS verifikasi keterbacaan, dan
 * dashboard pemegang izin pengaturan memperingatkan backup
 * rusak serta job antrean gagal. Peran tanpa izin pengaturan
 * tidak melihat peringatan operasional.
 */
class OpsWarningsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private BackupService $backups;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->kasir = User::factory()->create();
        $this->kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $this->kasir->branches()->attach(Branch::where('name', 'Pusat')->firstOrFail());
        $this->backups = app(BackupService::class);
    }

    public function test_verify_accepts_real_backup_and_rejects_corrupt_file(): void
    {
        $name = $this->backups->create($this->owner, 'uji-verifikasi');
        $this->assertTrue($this->backups->verify($name));

        $entries = $this->backups->entries();
        $mine = collect($entries)->firstWhere('name', $name);
        $this->assertTrue($mine['verified']);

        file_put_contents($this->backups->directory().'/auto-rusak.zip', 'ini bukan zip');
        $this->assertFalse($this->backups->verify('auto-rusak.zip'));
        @unlink($this->backups->directory().'/auto-rusak.zip');
        @unlink($this->backups->directory().'/'.$name);
    }

    public function test_dashboard_warns_owner_about_failed_jobs_but_not_cashier(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
        ]);

        $this->actingAs($this->owner)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.ops_queue_failed', ['count' => 1]));

        $this->actingAs($this->kasir)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(__('ui.dashboard.ops_queue_failed', ['count' => 1]));
    }
}
