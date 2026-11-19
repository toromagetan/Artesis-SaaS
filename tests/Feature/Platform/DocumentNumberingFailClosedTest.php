<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use App\Support\Numbering;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Numbering gagal-tertutup (verifikasi audit independen 18 Nov
 * 2026, temuan D): sebelum modul ini, pemanggilan Numbering::next()
 * tanpa tenant eksplisit yang konteks ambient-nya tidak valid jatuh
 * diam-diam ke ember global NULL, dan nextForBranch() dengan cabang
 * yang tidak ditemukan berbuat sama. Pada instalasi bertenant,
 * ember yang tidak dapat dibuktikan sekarang DITOLAK; ember NULL
 * tetap sah untuk instalasi mandiri murni dan untuk konteks tenant
 * yang tervalidasi.
 */
class DocumentNumberingFailClosedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
    }

    /** @return array{Tenant, Branch} */
    private function tenantWithBranch(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);

        return [$tenant, $branch];
    }

    public function test_ambient_call_without_provable_tenant_throws_in_multi_tenant_install(): void
    {
        $this->tenantWithBranch('a');

        // Tanpa aktor (konteks tamu): ember tidak terbukti → tolak,
        // dan tidak ada baris penghitung ember global yang tercetak.
        try {
            Numbering::next('INV');
            $this->fail('Pemanggilan ambient tanpa ember terbukti harus ditolak.');
        } catch (RuntimeException) {
            // Ditolak sesuai rancangan.
        }
        $this->assertSame(0, DB::table('document_counters')->count());
    }

    public function test_ambient_call_as_platform_actor_also_throws(): void
    {
        $this->tenantWithBranch('a');
        $platform = User::where('username', 'platform')->firstOrFail();

        // Aktor platform BUKAN bukti ember tenant mana pun.
        $this->actingAs($platform);
        $this->expectException(RuntimeException::class);
        Numbering::next('INV');
    }

    public function test_ambient_call_uses_the_authenticated_tenant_context(): void
    {
        [$tenant] = $this->tenantWithBranch('a');
        $owner = User::create([
            'email_verified_at' => now(),
            'name' => 'Owner A', 'username' => 'owner-a',
            'email' => 'owner-a@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($owner);
        $number = Numbering::next('INV');

        $this->assertSame('INV-'.now()->format('Ym').'-0001', $number);
        $this->assertSame(
            $tenant->id,
            (int) DB::table('document_counters')->where('prefix', 'INV')->value('tenant_id'),
        );
    }

    public function test_ambient_call_in_pure_standalone_install_uses_the_null_bucket(): void
    {
        // Tanpa satu pun tenant: ember NULL adalah satu-satunya
        // ember — perilaku instalasi mandiri tidak berubah.
        $number = Numbering::next('INV');

        $this->assertSame('INV-'.now()->format('Ym').'-0001', $number);
        $this->assertNull(DB::table('document_counters')->where('prefix', 'INV')->value('tenant_id'));
    }

    public function test_next_for_branch_with_unknown_branch_throws(): void
    {
        $this->tenantWithBranch('a');

        $this->expectException(RuntimeException::class);
        Numbering::nextForBranch('INV', 999999);
    }

    public function test_next_for_branch_without_branch_throws_when_tenants_exist(): void
    {
        $this->tenantWithBranch('a');

        $this->expectException(RuntimeException::class);
        Numbering::nextForBranch('INV', null);
    }

    public function test_next_for_branch_without_branch_works_in_pure_standalone_install(): void
    {
        $number = Numbering::nextForBranch('INV', null);

        $this->assertSame('INV-'.now()->format('Ym').'-0001', $number);
    }

    public function test_next_for_branch_with_orphan_branch_throws_in_multi_tenant_install(): void
    {
        // Instalasi bertenant (ada tenant A) + cabang yatim tanpa
        // tenant: cabang itu tidak membuktikan ember mana pun —
        // sebelumnya diam-diam dicetak ke ember global.
        $this->tenantWithBranch('a');
        $orphan = Branch::create(['name' => 'Cabang Yatim', 'tenant_id' => null]);

        try {
            Numbering::nextForBranch('INV', $orphan->id);
            $this->fail('Cabang tanpa tenant pada instalasi bertenant harus ditolak.');
        } catch (RuntimeException) {
            // Ditolak sesuai rancangan.
        }
        $this->assertSame(0, DB::table('document_counters')->count());
    }

    public function test_next_for_branch_with_orphan_branch_works_in_pure_standalone_install(): void
    {
        // Instalasi mandiri murni: cabang tanpa tenant adalah
        // keadaan normal — ember NULL tetap sah.
        $orphan = Branch::create(['name' => 'Cabang Tunggal', 'tenant_id' => null]);

        $number = Numbering::nextForBranch('INV', $orphan->id);

        $this->assertSame('INV-'.now()->format('Ym').'-0001', $number);
        $this->assertNull(DB::table('document_counters')->where('prefix', 'INV')->value('tenant_id'));
    }

    public function test_next_for_branch_with_other_tenants_branch_throws_under_tenant_context(): void
    {
        [$tenantA, $branchA] = $this->tenantWithBranch('a');
        [$tenantB] = $this->tenantWithBranch('b');

        // Tanpa konteks tenant, cabang itu sendiri membuktikan
        // embernya (jalur sistem in-process, ADR-022): nomor tercetak
        // ke ember tenant A.
        $this->assertSame(
            'INV-'.now()->format('Ym').'-0001',
            Numbering::nextForBranch('INV', $branchA->id),
        );

        // Di bawah konteks tenant B yang tervalidasi, id cabang
        // tenant A TIDAK dapat dipakai untuk mencetak nomor ke
        // ember A.
        $ownerB = User::create([
            'email_verified_at' => now(),
            'name' => 'Owner B', 'username' => 'owner-b',
            'email' => 'owner-b@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenantB->id,
        ]);
        $this->actingAs($ownerB);

        try {
            Numbering::nextForBranch('INV', $branchA->id);
            $this->fail('Cabang tenant lain di bawah konteks tenant pemanggil harus ditolak.');
        } catch (RuntimeException) {
            // Ditolak sesuai rancangan.
        }

        // Penghitung ember A tidak bergerak akibat percobaan itu.
        $this->assertSame(
            1,
            (int) DB::table('document_counters')
                ->where('prefix', 'INV')->where('tenant_id', $tenantA->id)->value('last_number'),
        );
    }

    public function test_explicit_calls_are_unaffected(): void
    {
        [$tenant, $branch] = $this->tenantWithBranch('a');

        $this->assertSame(
            'INV-'.now()->format('Ym').'-0001',
            Numbering::next('INV', now(), $tenant->id, true),
        );
        $this->assertSame(
            'INV-'.now()->format('Ym').'-0002',
            Numbering::nextForBranch('INV', $branch->id),
        );
        // Ember global eksplisit tetap dapat dicetak pemanggil yang
        // memang berhak (tenantSet dengan null yang disengaja).
        $this->assertSame(
            'INV-'.now()->format('Ym').'-0001',
            Numbering::next('INV', now(), null, true),
        );
    }
}
