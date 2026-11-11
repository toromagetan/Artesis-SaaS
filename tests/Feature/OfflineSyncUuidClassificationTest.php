<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\OfflineSyncService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Koreksi audit PR #33 putaran 4 — klasifikasi pelanggaran indeks
 * unik client_uuid pada jalur tangkap (catch) syncOne.
 *
 * Skenario balapan yang sesungguhnya (pemenang tercatat DI ANTARA
 * pemeriksaan duplikat dan penyisipan) tidak dapat diproduksi
 * ulang secara deterministik oleh dua request berurutan, jadi di
 * sini momen itu disimulasikan persis: SalesService tiruan
 * mencatat penjualan pemenang terlebih dahulu, lalu melempar
 * QueryException pelanggaran unik — sama seperti yang dilempar
 * MySQL saat balapan nyata terjadi. Klasifikasi yang dibuktikan:
 * pemenang seember → duplicate; pemenang tenant lain →
 * uuid_conflict; galat basis data lain → dilempar apa adanya.
 * Balapan proses-sungguhan dibuktikan terpisah di
 * Tests\Feature\Concurrency\OfflineSyncRaceTest.
 */
class OfflineSyncUuidClassificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private OfflineDevice $device;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->device = OfflineDevice::create([
            'name' => 'UJI-BALAPAN', 'branch_id' => $this->branch->id,
            'user_id' => $this->owner->id, 'token_hash' => hash('sha256', 'token-uji'),
            'created_by' => $this->owner->id,
        ]);
    }

    private function uniqueViolation(string $uuid): QueryException
    {
        return new QueryException(
            'mysql',
            'insert into `sales` (`client_uuid`) values (?)',
            [$uuid],
            new \PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '{$uuid}' for key 'sales.sales_client_uuid_unique'"),
        );
    }

    /** Layanan sinkronisasi dengan SalesService tiruan yang menjalankan $onCreate saat createSale dipanggil. */
    private function serviceWithCreate(callable $onCreate): OfflineSyncService
    {
        $sales = Mockery::mock(SalesService::class);
        $sales->shouldReceive('createSale')->andReturnUsing($onCreate);

        return new OfflineSyncService($sales, app(AuditLogger::class));
    }

    private function intent(string $uuid): array
    {
        return ['client_uuid' => $uuid, 'local_no' => '001', 'offline_at' => '2026-11-11 09:00:00',
            'customer_id' => 1, 'tax_mode' => 'exclusive',
            'items' => [['product_id' => 1, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '100000']]];
    }

    public function test_race_winner_in_own_bucket_classifies_as_duplicate(): void
    {
        $service = $this->serviceWithCreate(function () {
            // Pemenang balapan tercatat lebih dulu di ember yang
            // sama, lalu sisipan perangkat ini ditolak indeks unik.
            $winner = Sale::factory()->create([
                'client_uuid' => 'balapan-sendiri', 'branch_id' => $this->branch->id,
            ]);
            throw $this->uniqueViolation('balapan-sendiri');
        });

        $results = $service->sync($this->device, [$this->intent('balapan-sendiri')]);

        $this->assertSame('duplicate', $results[0]['status']);
        $winner = Sale::withoutGlobalScopes()->where('client_uuid', 'balapan-sendiri')->firstOrFail();
        $this->assertSame($winner->id, $results[0]['sale_id']);
        $this->assertSame($winner->number, $results[0]['number']);
        $this->assertSame(0, OfflineException::count());
    }

    public function test_race_winner_in_other_tenant_classifies_as_conflict(): void
    {
        $tenantB = Tenant::create(['name' => 'Optik B', 'slug' => 'optik-b', 'email' => 'b@uji.id']);
        $branchB = Branch::create(['name' => 'Cabang B', 'tenant_id' => $tenantB->id]);
        $service = $this->serviceWithCreate(function () use ($branchB) {
            Sale::factory()->create([
                'client_uuid' => 'balapan-asing', 'branch_id' => $branchB->id,
            ]);
            throw $this->uniqueViolation('balapan-asing');
        });

        $results = $service->sync($this->device, [$this->intent('balapan-asing')]);

        $this->assertSame('exception', $results[0]['status']);
        $this->assertSame('uuid_conflict', $results[0]['reason']);
        $this->assertArrayNotHasKey('sale_id', $results[0]);
        $this->assertArrayNotHasKey('number', $results[0]);
        $this->assertSame(1, OfflineException::where('reason', 'uuid_conflict')->count());
    }

    public function test_non_unique_database_error_is_not_masked_as_conflict(): void
    {
        $service = $this->serviceWithCreate(function () {
            throw new QueryException(
                'mysql',
                'insert into `sales` (`client_uuid`) values (?)',
                ['balapan-galat'],
                new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away'),
            );
        });

        try {
            $service->sync($this->device, [$this->intent('balapan-galat')]);
            $this->fail('QueryException non-unik seharusnya diteruskan, bukan disamarkan.');
        } catch (QueryException) {
            $this->assertSame(0, OfflineException::count());
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
