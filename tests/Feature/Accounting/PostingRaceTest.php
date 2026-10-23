<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\User;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * Bukti balapan untuk audit eksternal LNT-002 & LNT-005 (proses
 * PHP paralel melawan MySQL nyata (database unik per run,
 * LNT-007), pola yang sama
 * dengan ConcurrencyGateTest):
 * - LNT-002: posting vs kunci periode serentak TIDAK PERNAH
 *   meninggalkan jurnal tak-tertandai di periode terkunci.
 * - LNT-005: dua pembalikan serentak atas jurnal yang sama
 *   menghasilkan TEPAT satu jurnal pembalik.
 */
class PostingRaceTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

    private User $owner;

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

        // LNT-007: nama unik per run + penanda kepemilikan (sama
        // seperti ConcurrencyGateTest).
        $admin = $this->adminPdo();
        $this->dbName = ConcurrencyDatabase::uniqueName();
        $this->dbToken = ConcurrencyDatabase::create($admin, $this->dbName, (string) env('DB_USERNAME', 'lentera'));

        config(['database.connections.mysql.database' => $this->dbName]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->workDir = sys_get_temp_dir().'/lentera-race-'.uniqid();
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Pemulihan koneksi lebih dulu (try), penghapusan hanya
        // atas nama unik milik run ini dengan bukti penanda
        // kepemilikan (LNT-007) — sama seperti ConcurrencyGateTest.
        try {
            config(['database.connections.mysql.database' => env('DB_DATABASE', 'lentera_test')]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        } finally {
            try {
                ConcurrencyDatabase::dropOwned($this->adminPdo(), $this->dbName, $this->dbToken);
            } catch (\Throwable $e) {
                fwrite(STDERR, 'tearDown PostingRaceTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    /** @return array<int, array{ok: bool, error?: string}> */
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
        $deadline = microtime(true) + 60;
        while (microtime(true) < $deadline) {
            if (count(array_filter($readyFiles, 'file_exists')) === $count) {
                break;
            }
            usleep(20000);
        }
        $this->assertSame($count, count(array_filter($readyFiles, 'file_exists')), 'Pekerja tidak siap tepat waktu');
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

    public function test_post_vs_lock_race_never_leaves_unflagged_journal(): void
    {
        foreach (['2031-01', '2031-02', '2031-03'] as $period) {
            $this->runWorkers('postlock', ['period' => $period, 'date' => $period.'-15'], 2);

            $this->assertTrue(app(PostingService::class)->isPeriodLocked($period), "Periode {$period} harus berakhir terkunci.");
            $unflagged = Journal::where('source_type', 'race')
                ->whereRaw('substr(journal_date, 1, 7) = ?', [$period])
                ->where('period_locked', false)
                ->count();
            $this->assertSame(0, $unflagged, "Periode {$period}: jurnal balapan masuk sesudah kunci tanpa tertandai.");
        }
    }

    public function test_double_reverse_race_creates_exactly_one_reversal(): void
    {
        $journal = app(PostingService::class)->post('race-asal', null, now()->toDateString(), [
            ['account' => '1-1100', 'debit' => '5000'],
            ['account' => '4-1100', 'credit' => '5000'],
        ], 'jurnal asal uji LNT-005', $this->owner);

        $results = $this->runWorkers('reverse', ['journal_id' => $journal->id], 2);

        $successes = count(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
        $this->assertSame(1, $successes, 'Tepat satu pembalikan boleh berhasil.');
        $this->assertSame(1, Journal::where('reversal_of_id', $journal->id)->count(), 'Tepat satu jurnal pembalik boleh ada.');
    }
}
