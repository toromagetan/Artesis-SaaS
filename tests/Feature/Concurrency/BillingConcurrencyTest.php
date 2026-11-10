<?php

namespace Tests\Feature\Concurrency;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Models\TenantBillingRequest;
use App\Modules\Platform\Models\TenantSubscription;
use App\Modules\Platform\Services\BillingService;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concurrency\ConcurrencyDatabase;
use Tests\TestCase;

/**
 * B-01 — Gerbang konkurensi billing: proses PHP paralel melawan
 * MySQL nyata (database unik per run, pola yang sama dengan
 * ConcurrencyGateTest). Dua pembuatan permintaan bersamaan untuk
 * satu tenant hanya boleh melahirkan SATU pending; dua konfirmasi
 * bersamaan atas request yang sama hanya boleh menerapkan paket
 * SATU kali.
 */
class BillingConcurrencyTest extends TestCase
{
    private string $dbName;

    private string $dbToken;

    private string $workDir;

    private User $platform;

    private Tenant $tenant;

    private Plan $teamPlan;

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

        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->tenant = Tenant::create([
            'name' => 'Optik Balapan', 'slug' => 'optik-balapan', 'email' => 'balapan@uji.id',
        ]);
        $owner = User::create([
            'name' => 'Owner Balapan', 'username' => 'owner-balapan',
            'email' => 'owner-balapan@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $this->tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        // Langganan awal tenant: paket Free aktif.
        app(PlanService::class)->assignPlan(
            $this->tenant,
            Plan::where('key', 'free')->firstOrFail(),
            null,
            $this->platform,
        );
        $this->teamPlan = Plan::where('key', 'team')->firstOrFail();

        $this->workDir = sys_get_temp_dir().'/lentera-billing-conc-'.uniqid();
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
                fwrite(STDERR, 'tearDown BillingConcurrencyTest: '.$e->getMessage().PHP_EOL);
            }
            foreach (glob($this->workDir.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->workDir);
        }

        parent::tearDown();
    }

    /**
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

    private function successes(array $results): int
    {
        return count(array_filter($results, fn ($r) => ($r['ok'] ?? false) === true));
    }

    public function test_concurrent_create_requests_leave_single_pending(): void
    {
        $results = $this->runWorkers('billing-create', [
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->teamPlan->id,
            'months' => 1,
            'actor_id' => $this->platform->id,
        ], 2);

        $this->assertSame(1, $this->successes($results), 'Hasil pekerja: '.json_encode($results));
        $this->assertSame(1, TenantBillingRequest::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(
            1,
            TenantBillingRequest::where('tenant_id', $this->tenant->id)
                ->where('status', TenantBillingRequest::STATUS_PENDING)->count(),
        );
    }

    public function test_concurrent_confirms_apply_plan_once(): void
    {
        $request = app(BillingService::class)->createRequest(
            $this->tenant, $this->teamPlan, 3, null, $this->platform,
        );

        $results = $this->runWorkers('billing-confirm', [
            'request_id' => $request->id,
            'actor_id' => $this->platform->id,
        ], 2);

        $this->assertSame(1, $this->successes($results), 'Hasil pekerja: '.json_encode($results));

        // Tepat satu langganan Team lahir dan aktif; total langganan
        // tenant = Free awal + Team sekali.
        $activeTeam = TenantSubscription::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->teamPlan->id)
            ->where('status', TenantSubscription::STATUS_ACTIVE)
            ->get();
        $this->assertCount(1, $activeTeam);
        $this->assertSame(1, TenantSubscription::where('tenant_id', $this->tenant->id)
            ->where('plan_id', $this->teamPlan->id)->count());
        $this->assertSame(2, TenantSubscription::where('tenant_id', $this->tenant->id)->count());

        $fresh = $request->refresh();
        $this->assertSame(TenantBillingRequest::STATUS_CONFIRMED, $fresh->status);
        $this->assertSame($activeTeam->first()->id, $fresh->subscription_id);
    }
}
