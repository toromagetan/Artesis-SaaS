<?php

namespace Tests\Feature\Accounting;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * LNT-005: perintah preflight `lentera:preflight-reversal` —
 * pagar prosedural sebelum migrasi indeks unik 000045 dijalankan
 * pada database lama. Kedua cabang diuji pada database coretan
 * milik test sendiri (pola LNT-007) dengan tabel journals
 * minimal TANPA indeks unik — persis keadaan pra-migrasi:
 * bersih (NULL + nilai berbeda) harus LOLOS (exit 0), duplikat
 * reversal_of_id harus GAGAL (exit 1) dan dilaporkan.
 */
class JournalReversalPreflightTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

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
        $admin->exec("CREATE TABLE {$this->dbName}.journals (id INT AUTO_INCREMENT PRIMARY KEY, reversal_of_id INT NULL)");

        config(['database.connections.mysql.database' => $this->dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');
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
                fwrite(STDERR, 'tearDown JournalReversalPreflightTest: '.$e->getMessage().PHP_EOL);
            }
        }

        parent::tearDown();
    }

    public function test_preflight_passes_without_duplicates(): void
    {
        DB::table('journals')->insert([
            ['reversal_of_id' => null],
            ['reversal_of_id' => null],
            ['reversal_of_id' => 5],
            ['reversal_of_id' => 7],
        ]);

        $code = Artisan::call('lentera:preflight-reversal');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('AMAN', Artisan::output());
    }

    public function test_preflight_fails_and_reports_duplicates(): void
    {
        DB::table('journals')->insert([
            ['reversal_of_id' => null],
            ['reversal_of_id' => 5],
            ['reversal_of_id' => 5],
            ['reversal_of_id' => 5],
            ['reversal_of_id' => 9],
            ['reversal_of_id' => 9],
        ]);

        $code = Artisan::call('lentera:preflight-reversal');

        $this->assertSame(1, $code);
        $output = Artisan::output();
        $this->assertStringContainsString('GAGAL', $output);
        $this->assertStringContainsString('reversal_of_id=5', $output);
        $this->assertStringContainsString('reversal_of_id=9', $output);
    }
}
