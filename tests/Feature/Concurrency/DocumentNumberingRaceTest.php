<?php

namespace Tests\Feature\Concurrency;

use App\Modules\Platform\Models\Tenant;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * BALAPAN PENOMORAN DOKUMEN PER TENANT (ISO-1, audit isolasi
 * menyeluruh 18 Nov 2026) — dua proses PHP paralel melawan MySQL
 * nyata (database unik per run, pola gerbang konkurensi LNT-007),
 * dilepas serentak lewat berkas aba-aba:
 *
 * - Satu tenant, dua pekerja: nomor yang tercetak gabungan WAJIB
 *   tepat 0001..0010 tanpa duplikat dan tanpa lompatan — kunci
 *   baris penghitung per ember menserialkan keduanya.
 * - Dua tenant berbeda: setiap pekerja menerima tangga nomornya
 *   sendiri mulai 0001 — ember tidak saling menunggu/mengganggu
 *   dan tidak ada nomor yang tertukar antar tenant.
 */
class DocumentNumberingRaceTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

    private string $workDir;

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
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);

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
                fwrite(STDERR, 'tearDown DocumentNumberingRaceTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    private function makeTenant(string $tag): Tenant
    {
        return Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
    }

    /**
     * Lepas 2 pekerja serentak; kembalikan [hasil-ok, nomor] per pekerja.
     *
     * @return array{0: array<int, array>, 1: array<int, array>}
     */
    private function runWorkers(array $params): array
    {
        $tag = 'numbering-'.uniqid();
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
                $this->dbName, $php, escapeshellarg($worker), escapeshellarg('numbering'),
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
        $numbers = array_map(function ($f) {
            $side = $f.'.numbers.json';

            return file_exists($side) ? json_decode((string) file_get_contents($side), true) : [];
        }, $resultFiles);

        return [$ok, $numbers];
    }

    /** @param list<string> $numbers */
    private function suffixes(array $numbers): array
    {
        $suffixes = array_map(fn ($n) => substr((string) $n, -4), $numbers);
        sort($suffixes);

        return $suffixes;
    }

    public function test_same_tenant_race_mints_unique_sequence(): void
    {
        $tenant = $this->makeTenant('balap');

        [$ok, $numbers] = $this->runWorkers([
            'prefix' => 'INV',
            'tenant_ids' => [$tenant->id, $tenant->id],
            'count' => 5,
        ]);

        $this->assertTrue($ok[0]['ok'] ?? false, json_encode($ok[0]));
        $this->assertTrue($ok[1]['ok'] ?? false, json_encode($ok[1]));

        $all = array_merge($numbers[0], $numbers[1]);
        $this->assertCount(10, array_unique($all));
        $this->assertSame(
            ['0001', '0002', '0003', '0004', '0005', '0006', '0007', '0008', '0009', '0010'],
            $this->suffixes($all),
        );
        $this->assertSame(10, (int) DB::table('document_counters')
            ->where('prefix', 'INV')->where('tenant_id', $tenant->id)->value('last_number'));
    }

    public function test_cross_tenant_race_mints_independent_ladders(): void
    {
        $tenantA = $this->makeTenant('silang-a');
        $tenantB = $this->makeTenant('silang-b');

        [$ok, $numbers] = $this->runWorkers([
            'prefix' => 'INV',
            'tenant_ids' => [$tenantA->id, $tenantB->id],
            'count' => 5,
        ]);

        $this->assertTrue($ok[0]['ok'] ?? false, json_encode($ok[0]));
        $this->assertTrue($ok[1]['ok'] ?? false, json_encode($ok[1]));

        $expected = ['0001', '0002', '0003', '0004', '0005'];
        $this->assertSame($expected, $this->suffixes($numbers[0]));
        $this->assertSame($expected, $this->suffixes($numbers[1]));
        $this->assertSame(2, (int) DB::table('document_counters')->where('prefix', 'INV')->count());
    }
}
