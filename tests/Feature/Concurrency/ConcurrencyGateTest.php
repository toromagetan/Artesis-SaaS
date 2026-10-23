<?php

namespace Tests\Feature\Concurrency;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\PurchasePaymentAllocation;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * GERBANG KONKURENSI (aturan berdiri owner 2026-10-17 + Blueprint
 * G.1.7): skenario tetap dijalankan proses-proses PHP PARALEL
 * melawan MySQL nyata (database unik per run, LNT-007), dilepas
 * serentak lewat berkas aba-aba. Satu kegagalan = gerbang gagal.
 *
 * Skenario: pembayaran bersamaan tak melampaui tagihan; bayar
 * kolektif ganda tanpa alokasi ganda; perebutan stok terakhir tak
 * pernah negatif; Complete ganda tercatat satu kali; penerimaan PO
 * bersamaan tak melampaui qty PO kumulatif; submit ganda satu kali.
 */
class ConcurrencyGateTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

    private User $owner;

    private Branch $branch;

    private string $workDir;

    /**
     * Koneksi admin untuk membuat/menghapus database konkurensi.
     * Portabel: di VM lokal root memakai auth_socket (soket unix);
     * di CI memakai kredensial aplikasi lewat TCP (root service).
     */
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

        // LNT-007: nama unik per run + penanda kepemilikan — tidak
        // ada lagi DROP atas nama tetap yang bisa menghancurkan
        // database milik run lain / milik developer.
        $admin = $this->adminPdo();
        $this->dbName = ConcurrencyDatabase::uniqueName();
        $this->dbToken = ConcurrencyDatabase::create($admin, $this->dbName, (string) env('DB_USERNAME', 'lentera'));

        config(['database.connections.mysql.database' => $this->dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::where('key', 'tax.rate_default')->update(['value' => '0']);

        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->workDir = sys_get_temp_dir().'/lentera-conc-'.uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Pulihkan koneksi ke database test reguler DULU (dalam
        // try) agar test berikutnya tidak tertinggal di database
        // konkurensi; penghapusan database sesudahnya hanya untuk
        // nama unik milik run ini dan dibuktikan penandanya
        // (LNT-007) — kegagalannya tidak menutupi hasil test.
        try {
            config(['database.connections.mysql.database' => env('DB_DATABASE', 'lentera_test')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } finally {
            try {
                ConcurrencyDatabase::dropOwned($this->adminPdo(), $this->dbName, $this->dbToken);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'tearDown ConcurrencyGateTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    /**
     * Lepas $count pekerja serentak untuk satu skenario; kembalikan
     * daftar hasil JSON mereka.
     *
     * @return array<int, array{ok: bool, error?: string}>
     */
    private function runWorkers(string $scenario, array $params, int $count): array
    {
        $tag = $scenario.'-'.uniqid();
        $paramsFile = $this->workDir.'/'.$tag.'-params.json';
        file_put_contents($paramsFile, json_encode($params));
        $goFile = $this->workDir.'/'.$tag.'-go';
        $worker = __DIR__.'/../../Concurrency/worker.php';
        $php = PHP_BINARY;

        $readyFiles = [];
        $resultFiles = [];
        for ($i = 0; $i < $count; $i++) {
            $readyFiles[$i] = $this->workDir.'/'.$tag.'-ready-'.$i;
            $resultFiles[$i] = $this->workDir.'/'.$tag.'-result-'.$i;
            $cmd = sprintf(
                'DB_DATABASE=%s %s %s %s %s %s %s %s >%s 2>&1 &',
                $this->dbName, $php, escapeshellarg($worker), escapeshellarg($scenario),
                escapeshellarg($paramsFile), escapeshellarg($readyFiles[$i]),
                escapeshellarg($goFile), escapeshellarg($resultFiles[$i]),
                escapeshellarg($this->workDir.'/'.$tag.'-worker-'.$i.'.log'),
            );
            exec($cmd);
        }
        // Tunggu semua siap (maks 60 dtk), lalu lepas serentak.
        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (count(array_filter($readyFiles, 'file_exists')) === $count) {
                break;
            }
            usleep(20000);
        }
        if (count(array_filter($readyFiles, 'file_exists')) !== $count) {
            $logs = [];
            foreach ($readyFiles as $i => $readyFile) {
                $logFile = $this->workDir.'/'.$tag.'-worker-'.$i.'.log';
                $logs[] = 'worker '.$i.': '.(file_exists($logFile) ? (string) file_get_contents($logFile) : '(tanpa log)');
            }
            $this->fail('Pekerja tidak siap tepat waktu. Log pekerja: '.implode(' || ', $logs));
        }
        file_put_contents($goFile, 'go');

        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (count(array_filter($resultFiles, 'file_exists')) === $count) {
                break;
            }
            usleep(20000);
        }
        $this->assertSame($count, count(array_filter($resultFiles, 'file_exists')), 'Pekerja tidak selesai tepat waktu');

        return array_map(fn ($f) => json_decode((string) file_get_contents($f), true), $resultFiles);
    }

    private function successes(array $results): int
    {
        return count(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
    }

    public function test_concurrent_payments_never_exceed_bill(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 10, 'conc_seed', 1);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['product_id' => $frame->id, 'qty' => 1, 'unit_price' => '100000']]);

        $results = $this->runWorkers('pay', ['sale_id' => $sale->id, 'amount' => '60000'], 4);
        $this->assertSame(1, $this->successes($results), 'Tepat satu pembayaran 60rb boleh masuk ke tagihan 100rb');
        $fresh = Sale::withoutGlobalScopes()->findOrFail($sale->id);
        $this->assertSame('60000.00', (string) $fresh->paid_amount);
    }

    public function test_double_collective_payment_allocates_once(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000']);
        $supplier = Supplier::create(['name' => 'PT Konkuren', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        foreach (range(1, 3) as $i) {
            $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
                ['product_id' => $frame->id, 'qty' => 40, 'unit_cost' => '50000'],
            ]);
            $orders->markSent($this->owner, $po);
            app(ReceiptService::class)->store($this->owner, $po, [
                ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 40],
            ]);
        }

        $results = $this->runWorkers('collective', ['supplier_id' => $supplier->id, 'amount' => '6000000'], 3);
        $this->assertSame(1, $this->successes($results));
        $this->assertSame(1, PurchasePayment::count());
        $sum = Money::zero();
        foreach (PurchasePaymentAllocation::all() as $allocation) {
            $sum = $sum->add(Money::fromDecimal((string) $allocation->amount));
        }
        $this->assertSame('6000000.00', $sum->toDecimalString()); // Σ alokasi == pembayaran (G.1.3)
    }

    public function test_last_stock_race_never_negative(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['name' => 'Frame Rebutan', 'cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 1, 'conc_seed', 1);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);

        $results = $this->runWorkers('sale', [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'product_id' => $frame->id, 'price' => '100000',
        ], 4);
        $this->assertSame(1, $this->successes($results), 'Stok terakhir hanya boleh terjual satu kali');
        $this->assertSame(0, app(InventoryService::class)->currentStock($frame->id, $this->branch->id));
        $min = StockMovement::where('product_id', $frame->id)->min('balance_after');
        $this->assertGreaterThanOrEqual(0, (int) $min, 'Saldo tidak pernah negatif');
    }

    public function test_double_complete_recorded_once(): void
    {
        $lens = Product::factory()->ofType('lens')->create(['cost_price' => '70000', 'sale_price' => '150000']);
        app(InventoryService::class)->recordMovement($this->owner, $lens, $this->branch, 'initial_stock', 5, 'conc_seed', 1);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['product_id' => $lens->id, 'qty' => 1, 'unit_price' => '150000']]);
        $process = LensProcess::firstOrFail();

        $results = $this->runWorkers('complete', ['process_id' => $process->id], 4);
        $this->assertSame(1, $this->successes($results));
        $fresh = $process->refresh();
        $this->assertSame('done', $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_concurrent_receiving_never_exceeds_po_qty(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000']);
        $supplier = Supplier::create(['name' => 'PT Terima Konkuren', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $frame->id, 'qty' => 5, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($this->owner, $po);

        $results = $this->runWorkers('receive', [
            'po_id' => $po->id, 'po_item_id' => $po->items->first()->id, 'qty' => 3,
        ], 4);
        $this->assertSame(1, $this->successes($results), 'Dua penerimaan 3 unit akan melampaui PO 5 unit');
        $this->assertSame(3, (int) $po->items()->first()->qty_received);
        $this->assertSame(3, app(InventoryService::class)->currentStock($frame->id, $this->branch->id));
    }

    public function test_double_submit_applied_once(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000']);
        $pr = app(PurchaseRequestService::class)->create($this->owner, $this->branch->id, [
            ['product_id' => $frame->id, 'qty' => 2],
        ]);

        $results = $this->runWorkers('submit', ['pr_id' => $pr->id], 3);
        $this->assertSame(1, $this->successes($results));
        $this->assertSame('submitted', $pr->refresh()->status);
    }
}
