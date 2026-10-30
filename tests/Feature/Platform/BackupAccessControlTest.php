<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\BackupService;
use App\Modules\Platform\Services\TenantSignupService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-1 — kendali akses backup database global (menutup temuan
 * audit P0-1). Matriks aktor × operasi dalam dua mode:
 *
 * MODE BERSAMA (ada tenant terdaftar):
 * - Platform Superadmin : semua operasi diizinkan (restore wajib
 *   menyebut jumlah tenant terdampak yang benar).
 * - Tenant Owner A & B  : SEMUA operasi 403 + teraudit
 *   `backup.denied`, meski peran Owner tenant memegang seluruh
 *   izin bisnis termasuk `backups.manage` & `settings.manage`.
 * - Akun tanpa tenant yang BUKAN platform (legacy 'owner'):
 *   ditolak juga — tenant_id NULL bukan jalan masuk begitu
 *   instansi melayani tenant.
 *
 * MODE INDIVIDUAL (tanpa tenant):
 * - Legacy Individual Owner: perilaku lama utuh (buat/daftar/
 *   unduh/hapus/pulihkan), unduhan teraudit `backup.downloaded`.
 * - Non-Owner berizin backups.manage: boleh operasi umum,
 *   restore tetap 403 (syarat peran Owner dipertahankan).
 */
class BackupAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private BackupService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BackupService::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->service->entries() as $entry) {
            @unlink($this->service->directory().'/'.$entry['name']);
        }
        parent::tearDown();
    }

    /** @return array{platform: User, legacy: User, ownerA: User, ownerB: User} */
    private function seedSharedMode(): array
    {
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $platform = User::where('username', 'platform')->firstOrFail();
        $legacy = User::where('username', 'owner')->firstOrFail();

        $signup = app(TenantSignupService::class);
        $ownerA = $signup->register([
            'business_name' => 'Optik Alpha', 'owner_name' => 'Alpha',
            'email' => 'alpha@uji.id', 'phone' => '081200000001',
            'branch_name' => 'Alpha Pusat', 'password' => 'rahasia-uji-123',
            'password_confirmation' => 'rahasia-uji-123',
        ], $platform);
        $ownerB = $signup->register([
            'business_name' => 'Optik Beta', 'owner_name' => 'Beta',
            'email' => 'beta@uji.id', 'phone' => '081200000002',
            'branch_name' => 'Beta Pusat', 'password' => 'rahasia-uji-123',
            'password_confirmation' => 'rahasia-uji-123',
        ], $platform);

        $this->assertSame(2, Tenant::count());

        return compact('platform', 'legacy', 'ownerA', 'ownerB');
    }

    public function test_platform_can_list_create_download_and_delete_in_shared_mode(): void
    {
        ['platform' => $platform] = $this->seedSharedMode();

        $this->actingAs($platform)->get(route('backups.index'))->assertOk();

        $this->actingAs($platform)->post(route('backups.store'))
            ->assertRedirect()->assertSessionHas('status');
        $entries = $this->service->entries();
        $this->assertCount(1, $entries);
        $name = $entries[0]['name'];

        $this->actingAs($platform)
            ->get(route('backups.download', ['berkas' => $name]))
            ->assertOk();
        $this->assertTrue(
            AuditLog::where('action', 'backup.downloaded')->where('user_id', $platform->id)->exists(),
            'Unduhan backup platform harus teraudit.'
        );

        $this->actingAs($platform)->post(route('backups.destroy'), ['berkas' => $name])
            ->assertRedirect();
        $this->assertCount(0, $this->service->entries());
    }

    public function test_platform_restore_requires_correct_affected_tenant_count(): void
    {
        ['platform' => $platform] = $this->seedSharedMode();

        $name = $this->service->create($platform);
        Setting::set('penanda.r1', 'sesudah-backup');

        // Tanpa jumlah tenant: validasi gagal, data tidak berubah.
        $this->actingAs($platform)->post(route('backups.restore'), [
            'berkas' => $name, 'konfirmasi' => 'RESTORE',
        ])->assertSessionHasErrors('jumlah_tenant');
        $this->assertSame('sesudah-backup', Setting::value('penanda.r1'));

        // Jumlah tenant salah: validasi gagal, data tidak berubah.
        $this->actingAs($platform)->post(route('backups.restore'), [
            'berkas' => $name, 'konfirmasi' => 'RESTORE', 'jumlah_tenant' => 99,
        ])->assertSessionHasErrors('jumlah_tenant');
        $this->assertSame('sesudah-backup', Setting::value('penanda.r1'));

        // Jumlah tenant benar: restore berjalan, penanda pasca-backup hilang.
        $this->actingAs($platform)->post(route('backups.restore'), [
            'berkas' => $name, 'konfirmasi' => 'RESTORE', 'jumlah_tenant' => 2,
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertNull(Setting::value('penanda.r1'));
    }

    public function test_tenant_owners_are_denied_every_backup_operation(): void
    {
        ['platform' => $platform, 'ownerA' => $ownerA, 'ownerB' => $ownerB] = $this->seedSharedMode();

        $name = $this->service->create($platform);
        Setting::set('penanda.r1', 'milik-platform');

        foreach (['A' => $ownerA, 'B' => $ownerB] as $label => $tenantOwner) {
            // Prasyarat bukti: Owner tenant MEMEGANG kedua izin —
            // penolakan di bawah murni berasal dari policy mode.
            $this->assertTrue($tenantOwner->hasPermission('backups.manage'), "Owner {$label} harus memegang backups.manage");
            $this->assertTrue($tenantOwner->hasPermission('settings.manage'), "Owner {$label} harus memegang settings.manage");
            $this->assertFalse($tenantOwner->is_platform);

            $deniedBefore = AuditLog::where('action', 'backup.denied')->where('user_id', $tenantOwner->id)->count();

            $this->actingAs($tenantOwner)->get(route('backups.index'))->assertForbidden();
            $this->actingAs($tenantOwner)->post(route('backups.store'))->assertForbidden();
            $this->actingAs($tenantOwner)->get(route('backups.download', ['berkas' => $name]))->assertForbidden();
            $this->actingAs($tenantOwner)->post(route('backups.destroy'), ['berkas' => $name])->assertForbidden();
            $this->actingAs($tenantOwner)->post(route('backups.restore'), [
                'berkas' => $name, 'konfirmasi' => 'RESTORE', 'jumlah_tenant' => 2,
            ])->assertForbidden();
            $this->actingAs($tenantOwner)->post(route('backups.offsite'), ['aktif' => '1'])->assertForbidden();

            $denied = AuditLog::where('action', 'backup.denied')->where('user_id', $tenantOwner->id)->count();
            $this->assertSame($deniedBefore + 6, $denied, "Enam penolakan Owner {$label} harus teraudit.");
        }

        // Restore yang ditolak tidak mengubah data & berkas utuh.
        $this->assertSame('milik-platform', Setting::value('penanda.r1'));
        $this->assertCount(1, $this->service->entries());
    }

    public function test_settings_manage_alone_grants_no_backup_access(): void
    {
        ['ownerA' => $ownerA] = $this->seedSharedMode();

        $role = Role::create(['name' => 'PengaturUji', 'label' => 'Pengatur Uji']);
        $role->permissions()->attach(Permission::where('name', 'settings.manage')->firstOrFail());
        $user = User::factory()->create(['tenant_id' => $ownerA->tenant_id]);
        $user->roles()->attach($role);

        $this->assertTrue($user->hasPermission('settings.manage'));
        $this->assertFalse($user->hasPermission('backups.manage'));

        $this->actingAs($user)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($user)->post(route('backups.store'))->assertForbidden();
    }

    public function test_download_path_manipulation_and_unknown_files_are_not_found(): void
    {
        ['platform' => $platform, 'ownerA' => $ownerA] = $this->seedSharedMode();

        // Tenant: manipulasi path pun berhenti di policy (403).
        $this->actingAs($ownerA)->get(route('backups.download', ['berkas' => '../.env']))->assertForbidden();
        $this->actingAs($ownerA)->get(route('backups.download', ['berkas' => '../../.env']))->assertForbidden();

        // Platform: nama dimanipulasi/ditebak tidak pernah keluar
        // dari direktori backup (basename) — 404, bukan berkas lain.
        $this->actingAs($platform)->get(route('backups.download', ['berkas' => '../.env']))->assertNotFound();
        $this->actingAs($platform)->get(route('backups.download', ['berkas' => 'backup-tebakan-2099.zip']))->assertNotFound();
    }

    public function test_tenantless_non_platform_actor_is_denied_once_tenants_exist(): void
    {
        ['legacy' => $legacy] = $this->seedSharedMode();

        // Akun legacy tanpa tenant, bukan platform, peran Owner,
        // memegang backups.manage — tetap bukan operator backup
        // pada instansi yang sudah melayani tenant.
        $this->assertNull($legacy->tenant_id);
        $this->assertFalse($legacy->is_platform);
        $this->assertTrue($legacy->hasPermission('backups.manage'));

        $this->actingAs($legacy)->get(route('backups.index'))->assertForbidden();
        $this->actingAs($legacy)->post(route('backups.store'))->assertForbidden();
        $this->assertTrue(
            AuditLog::where('action', 'backup.denied')->where('user_id', $legacy->id)->exists()
        );
    }

    public function test_individual_owner_keeps_full_backup_access(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();
        $this->assertSame(0, Tenant::count());

        $this->actingAs($owner)->get(route('backups.index'))->assertOk();
        $this->actingAs($owner)->post(route('backups.store'))
            ->assertRedirect()->assertSessionHas('status');
        $entries = $this->service->entries();
        $this->assertCount(1, $entries);
        $name = $entries[0]['name'];

        Setting::set('penanda.r1', 'sesudah-backup');

        $this->actingAs($owner)->get(route('backups.download', ['berkas' => $name]))->assertOk();
        $this->assertTrue(AuditLog::where('action', 'backup.downloaded')->exists());

        // Restore individual: cukup ketik RESTORE (tanpa jumlah tenant).
        $this->actingAs($owner)->post(route('backups.restore'), [
            'berkas' => $name, 'konfirmasi' => 'RESTORE',
        ])->assertRedirect()->assertSessionHas('status');
        $this->assertNull(Setting::value('penanda.r1'));
    }

    public function test_individual_non_owner_with_permission_cannot_restore(): void
    {
        $this->seed(IdentitySeeder::class);
        $owner = User::where('username', 'owner')->firstOrFail();
        $name = $this->service->create($owner);

        $role = Role::create(['name' => 'TeknisiBackup', 'label' => 'Teknisi Backup']);
        $role->permissions()->attach(Permission::where('name', 'backups.manage')->firstOrFail());
        $teknisi = User::factory()->create();
        $teknisi->roles()->attach($role);

        // Operasi umum boleh…
        $this->actingAs($teknisi)->get(route('backups.index'))->assertOk();
        // …restore tetap khusus Owner pada mode individual.
        $this->actingAs($teknisi)->post(route('backups.restore'), [
            'berkas' => $name, 'konfirmasi' => 'RESTORE',
        ])->assertForbidden();
        $this->assertTrue(
            AuditLog::where('action', 'backup.denied')
                ->where('user_id', $teknisi->id)
                ->where('new_values->operation', 'restore')
                ->exists()
        );
    }
}
