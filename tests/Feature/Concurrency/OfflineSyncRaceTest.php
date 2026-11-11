<?php

namespace Tests\Feature\Concurrency;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\OfflineDeviceService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * BALAPAN UUID SINKRONISASI OFFLINE (koreksi audit PR #33
 * putaran 4) — dua proses PHP paralel melawan MySQL nyata
 * (database unik per run, pola gerbang konkurensi LNT-007),
 * dilepas serentak lewat berkas aba-aba, mensinkronkan UUID
 * yang SAMA dari dua perangkat:
 *
 * - Satu tenant, dua perangkat: tepat satu penjualan tercatat;
 *   hasilnya satu 'synced' + satu 'duplicate' yang menunjuk
 *   penjualan yang sama — tidak ada konflik palsu, tidak ada
 *   galat yang lolos ke pekerja.
 * - Dua tenant: tepat satu penjualan tercatat; perangkat yang
 *   kalah menerima 'uuid_conflict' TANPA id/nomor pemilik.
 *
 * Tergantung kapan pemenang tercatat relatif terhadap
 * pemeriksaan perangkat yang kalah, klasifikasi dapat lahir dari
 * pemeriksaan awal maupun dari jalur tangkap pelanggaran unik —
 * keduanya kini menghasilkan klasifikasi yang sama; jalur tangkap
 * itu sendiri dibuktikan deterministik di
 * Tests\Feature\OfflineSyncUuidClassificationTest.
 */
class OfflineSyncRaceTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

    private string $workDir;

    private User $platform;

    private function adminPdo(): \PDO
    {
        $socket = '/var/run/mysqld/mysqld.sock';
        if (file_exists($socket)) {
            try {
                return new \PDO('mysql:unix_socket='.$socket, 'root', null);
            } catch (\Throwable) {
                // jatuh ke TCP di bawah
            }
        }

        return new \PDO(
            'mysql:host=127.0.0.1;port='.(string) env('DB_PORT', '3306'),
            (string) env('DB_USERNAME', 'root'),
            (string) env('DB_PASSWORD', ''),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->adminPdo();
        $this->dbName = ConcurrencyDatabase::uniqueName();
        $this->dbToken = ConcurrencyDatabase::create($admin, $this->dbName, (string) env('DB_USERNAME', 'lentera'));

        config(['database.connections.mysql.database' => $this->dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        Setting::where('key', 'tax.rate_default')->update(['value' => '0']);

        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->workDir = sys_get_temp_dir().'/lentera-conc-'.uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        try {
            config(['database.connections.mysql.database' => env('DB_DATABASE', 'lentera_test')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } finally {
            try {
                ConcurrencyDatabase::dropOwned($this->adminPdo(), $this->dbName, $this->dbToken);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'tearDown OfflineSyncRaceTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    /** Daftarkan perangkat kasir baru; kembalikan id perangkat. */
    private function deviceId(User $admin, Tenant $tenant, Branch $branch, string $tag): int
    {
        $kasir = User::create([
            'name' => 'Kasir '.$tag, 'username' => 'kasir-'.$tag,
            'email' => 'kasir-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $kasir->branches()->attach($branch);
        $issued = app(OfflineDeviceService::class)->register($admin, 'KSR-'.$tag, $branch->id, $kasir->id);

        return $issued['device']->id;
    }

    private function stockedProduct(User $admin, Tenant $tenant, Branch $branch, string $tag): Product
    {
        $product = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-'.$tag, 'name' => 'Frame '.$tag,
            'cost_price' => '50000', 'sale_price' => '100000',
            'tenant_id' => $tenant->id,
        ]);
        $supplier = Supplier::create(['name' => 'Supplier '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($admin, $supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => 10, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($admin, $po);
        app(ReceiptService::class)->store($admin, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 10, 'unit_cost_actual' => '50000'],
        ]);

        return $product;
    }

    private function intent(string $uuid, Customer $customer, Product $product): array
    {
        return ['client_uuid' => $uuid, 'local_no' => '001', 'offline_at' => '2026-11-11 09:00:00',
            'customer_id' => $customer->id, 'tax_mode' => 'exclusive',
            'items' => [['product_id' => $product->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '100000']]];
    }

    /**
     * Lepas 2 pekerja serentak; kembalikan [hasil-ok, hasil-sync]
     * per pekerja (pola ConcurrencyGateTest).
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function runWorkers(array $params): array
    {
        $tag = 'offline_sync-'.uniqid();
        $paramsFile = $this->workDir.'/'.$tag.'-params.json';
        file_put_contents($paramsFile, json_encode($params));
        $goFile = $this->workDir.'/'.$tag.'-go';
        $worker = __DIR__.'/../../Concurrency/worker.php';
        $php = PHP_BINARY;

        $readyFiles = [];
        $resultFiles = [];
        for ($i = 0; $i < 2; $i++) {
            $readyFiles[$i] = $this->workDir.'/'.$tag.'-ready-'.$i;
            $resultFiles[$i] = $this->workDir.'/'.$tag.'-result-'.$i;
            $cmd = sprintf(
                'DB_DATABASE=%s %s %s %s %s %s %s %s >%s 2>&1 &',
                $this->dbName, $php, escapeshellarg($worker), escapeshellarg('offline_sync'),
                escapeshellarg($paramsFile), escapeshellarg($readyFiles[$i]),
                escapeshellarg($goFile), escapeshellarg($resultFiles[$i]),
                escapeshellarg($this->workDir.'/'.$tag.'-worker-'.$i.'.log'),
            );
            exec($cmd);
        }
        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (count(array_filter($readyFiles, 'file_exists')) === 2) {
                break;
            }
            usleep(20000);
        }
        if (count(array_filter($readyFiles, 'file_exists')) !== 2) {
            $logs = [];
            foreach ($readyFiles as $i => $readyFile) {
                $logFile = $this->workDir.'/'.$tag.'-worker-'.$i.'.log';
                $logs[] = 'worker '.$i.': '.(file_exists($logFile) ? (string) file_get_contents($logFile) : '(tanpa log)');
            }
            $this->fail('Pekerja tidak siap tepat waktu. Log: '.implode(' || ', $logs));
        }
        file_put_contents($goFile, 'go');

        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (count(array_filter($resultFiles, 'file_exists')) === 2) {
                break;
            }
            usleep(20000);
        }
        $this->assertSame(2, count(array_filter($resultFiles, 'file_exists')), 'Pekerja tidak selesai tepat waktu');

        $ok = array_map(fn ($f) => json_decode((string) file_get_contents($f), true), $resultFiles);
        $sync = array_map(function ($f) {
            $side = $f.'.sync.json';

            return file_exists($side) ? json_decode((string) file_get_contents($side), true) : null;
        }, $resultFiles);

        return [$ok, $sync];
    }

    public function test_same_tenant_race_yields_one_sale_and_one_duplicate(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        $product = $this->stockedProduct($adminA, $tenantA, $branchA, 'A');
        $customer = Customer::factory()->create(['branch_id' => $branchA->id, 'tenant_id' => $tenantA->id]);
        $device1 = $this->deviceId($adminA, $tenantA, $branchA, 'a1');
        $device2 = $this->deviceId($adminA, $tenantA, $branchA, 'a2');
        $intent = $this->intent('balapan-satu-tenant', $customer, $product);

        [$ok, $sync] = $this->runWorkers([
            'device_ids' => [$device1, $device2],
            'intents' => [$intent, $intent],
        ]);

        $this->assertSame([true, true], array_map(fn ($r) => $r['ok'] ?? false, $ok), 'Pekerja tidak boleh gagal: '.json_encode($ok));
        $sales = Sale::withoutGlobalScopes()->where('client_uuid', 'balapan-satu-tenant')->get();
        $this->assertCount(1, $sales, ' Tepat satu penjualan boleh tercatat untuk satu UUID.');

        $statuses = collect($sync)->map(fn ($r) => $r[0]['status'] ?? null)->sort()->values()->all();
        $this->assertSame(['duplicate', 'synced'], $statuses);
        $saleIds = collect($sync)->map(fn ($r) => $r[0]['sale_id'] ?? null)->unique()->all();
        $this->assertSame([$sales->first()->id], array_values($saleIds));
        $this->assertSame(0, OfflineException::where('reason', 'uuid_conflict')->count());
    }

    public function test_cross_tenant_race_yields_one_sale_and_one_conflict_without_leak(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $productA = $this->stockedProduct($adminA, $tenantA, $branchA, 'A');
        $productB = $this->stockedProduct($adminB, $tenantB, $branchB, 'B');
        $customerA = Customer::factory()->create(['branch_id' => $branchA->id, 'tenant_id' => $tenantA->id]);
        $customerB = Customer::factory()->create(['branch_id' => $branchB->id, 'tenant_id' => $tenantB->id]);
        $deviceA = $this->deviceId($adminA, $tenantA, $branchA, 'a1');
        $deviceB = $this->deviceId($adminB, $tenantB, $branchB, 'b1');

        [$ok, $sync] = $this->runWorkers([
            'device_ids' => [$deviceA, $deviceB],
            'intents' => [
                $this->intent('balapan-dua-tenant', $customerA, $productA),
                $this->intent('balapan-dua-tenant', $customerB, $productB),
            ],
        ]);

        $this->assertSame([true, true], array_map(fn ($r) => $r['ok'] ?? false, $ok), 'Pekerja tidak boleh gagal: '.json_encode($ok));
        $sales = Sale::withoutGlobalScopes()->where('client_uuid', 'balapan-dua-tenant')->get();
        $this->assertCount(1, $sales, 'Tepat satu penjualan boleh tercatat untuk satu UUID global.');

        $byStatus = collect($sync)->map(fn ($r) => $r[0])->keyBy('status');
        $this->assertTrue($byStatus->has('synced'), 'Satu perangkat harus berhasil: '.json_encode($sync));
        $this->assertTrue($byStatus->has('exception'), 'Perangkat yang kalah harus menerima pengecualian: '.json_encode($sync));
        $conflict = $byStatus->get('exception');
        $this->assertSame('uuid_conflict', $conflict['reason']);
        $this->assertArrayNotHasKey('sale_id', $conflict);
        $this->assertArrayNotHasKey('number', $conflict);
        $this->assertSame($sales->first()->id, $byStatus->get('synced')['sale_id']);
        $this->assertSame(1, OfflineException::where('reason', 'uuid_conflict')
            ->where('client_uuid', 'balapan-dua-tenant')->count());
    }
}
