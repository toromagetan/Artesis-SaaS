<?php

namespace Tests\Feature\Concurrency;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * BALAPAN PERUBAHAN AKUN vs POSTING JURNAL (verifikasi audit
 * independen 18 Nov 2026, temuan E) — dua proses PHP paralel
 * melawan MySQL nyata (database unik per run, pola LNT-007):
 * pekerja -0 mengubah kode sebuah akun lewat AccountService,
 * pekerja -1 memposting jurnal ke kode lama akun itu lewat
 * PostingService, dilepas serentak.
 *
 * Keadaan akhir yang sah TEPAT satu:
 * - Perubahan menang: kode berubah, posting gagal bersih
 *   (ValidationException) dan tidak ada baris jurnal di akun itu; ATAU
 * - Posting menang: jurnal terposting, perubahan ditolak
 *   (ValidationException: akun sudah memiliki baris) dan kode tetap.
 *
 * Keadaan TERLARANG yang ditutup kunci baris akun di kedua sisi:
 * baris jurnal mendarat di akun yang kodenya berubah sesudah
 * resolusi, atau kode berubah di atas akun yang sudah terpakai.
 * Tiga ronde dengan akun sasaran berbeda untuk menekan kebetulan
 * urutan pelepasan.
 */
class CoaUpdatePostingRaceTest extends TestCase
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
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);

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
                fwrite(STDERR, 'tearDown CoaUpdatePostingRaceTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    public function test_account_code_change_and_first_posting_never_interleave(): void
    {
        $rounds = [
            ['1-1100', '9-9001'],
            ['1-1200', '9-9002'],
            ['1-1300', '9-9003'],
        ];

        foreach ($rounds as [$oldCode, $newCode]) {
            $account = Account::where('code', $oldCode)->whereNull('tenant_id')->firstOrFail();
            $results = $this->runWorkers([
                'account_id' => $account->id,
                'old_code' => $oldCode,
                'new_code' => $newCode,
                'offset_code' => '4-1100',
                'date' => now()->format('Y-m-d'),
            ]);

            [$updater, $poster] = $results;
            $successes = (int) ($updater['ok'] ?? false) + (int) ($poster['ok'] ?? false);
            $this->assertSame(
                1,
                $successes,
                "Ronde {$oldCode}: tepat satu pihak boleh berhasil. Hasil: ".json_encode($results),
            );

            $account->refresh();
            $lines = JournalLine::where('account_id', $account->id)->count();
            if ($updater['ok']) {
                // Perubahan menang: posting gagal bersih, akun
                // berubah kode dan tetap tanpa baris.
                $this->assertSame(ValidationException::class, $poster['error'] ?? null);
                $this->assertSame($newCode, $account->code);
                $this->assertSame(0, $lines);
            } else {
                // Posting menang: perubahan ditolak, kode tetap,
                // tepat satu baris jurnal di akun sasaran.
                $this->assertSame(ValidationException::class, $updater['error'] ?? null);
                $this->assertSame($oldCode, $account->code);
                $this->assertSame(1, $lines);
            }
        }
    }

    /**
     * Lepas 2 pekerja serentak untuk skenario 'coa-race';
     * kembalikan hasil JSON per pekerja ([updater, poster]).
     *
     * @return array{0: array, 1: array}
     */
    private function runWorkers(array $params): array
    {
        $tag = 'coa-race-'.uniqid();
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
                $this->dbName, $php, escapeshellarg($worker), escapeshellarg('coa-race'),
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

        return array_map(
            fn ($f) => json_decode((string) file_get_contents($f), true),
            $resultFiles,
        );
    }
}
