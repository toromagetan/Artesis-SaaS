<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\FixedAsset;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F1.4: master aset tetap + penyusutan garis lurus terjurnal.
 * Matematika: half-up per bulan; bulan terjadwal terakhir
 * membebankan sisa sehingga akumulasi = harga perolehan persis.
 * Idempoten per aset-periode; aset dilepas/habis dilewati;
 * periode terkunci menggulung.
 */
class FixedAssetTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private FixedAssetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->service = app(FixedAssetService::class);
    }

    private function makeAsset(string $cost = '12000000', int $months = 12, string $acquired = '2026-08-15'): FixedAsset
    {
        return $this->service->create($this->owner, [
            'name' => 'Mesin Edger', 'acquired_at' => $acquired,
            'acquisition_cost' => $cost, 'useful_life_months' => $months,
        ]);
    }

    public function test_create_via_http_and_permission_fences(): void
    {
        $response = $this->actingAs($this->owner)->post(route('assets.store'), [
            'name' => 'Slit Lamp', 'acquired_at' => '2026-09-01',
            'acquisition_cost' => '8500000', 'useful_life_months' => 60,
        ]);
        $asset = FixedAsset::firstOrFail();
        $response->assertRedirect(route('assets.show', $asset));
        $this->assertDatabaseHas('audit_logs', ['action' => 'assets.create', 'subject_id' => $asset->id]);
        $this->actingAs($this->owner)->get(route('assets.index'))->assertOk();

        $admin = $this->makeUserWithRole('Admin');
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($admin)->get(route('assets.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('assets.store'), [])->assertForbidden();
        $this->actingAs($platform)->get(route('assets.index'))->assertForbidden();
        $this->actingAs($this->owner)->delete(route('assets.show', $asset))->assertMethodNotAllowed();
    }

    public function test_monthly_depreciation_posts_journal_and_is_idempotent(): void
    {
        $asset = $this->makeAsset();

        $result = $this->service->depreciatePeriod($this->owner, '2026-08');
        $this->assertSame(1, $result['posted']);
        $this->assertSame('1000000.00', $result['total']->toDecimalString());

        $journal = Journal::where('source_type', 'depreciation')->firstOrFail();
        $lines = $journal->lines()->with('account')->get()->keyBy(fn ($l) => $l->account->code);
        $this->assertSame('1000000.00', (string) $lines['5-1600']->debit);
        $this->assertSame('1000000.00', (string) $lines['1-1600']->credit);
        $this->assertSame('1000000.00', $asset->fresh()->accumulated_amount);

        // Jalankan ulang periode sama → tidak ada jurnal baru.
        $again = $this->service->depreciatePeriod($this->owner, '2026-08');
        $this->assertSame(0, $again['posted']);
        $this->assertSame(1, Journal::where('source_type', 'depreciation')->count());

        // Bulan berikutnya terposting normal.
        $next = $this->service->depreciatePeriod($this->owner, '2026-09');
        $this->assertSame(1, $next['posted']);
        $this->assertSame('2000000.00', $asset->fresh()->accumulated_amount);
    }

    public function test_final_month_charges_exact_remainder(): void
    {
        // 1.000.000 / 3 bulan: 333.333,33 + 333.333,33 + 333.333,34.
        $asset = $this->makeAsset('1000000', 3, '2026-08-01');
        $this->service->depreciatePeriod($this->owner, '2026-08');
        $this->service->depreciatePeriod($this->owner, '2026-09');
        $last = $this->service->depreciatePeriod($this->owner, '2026-10');
        $this->assertSame('333333.34', $last['total']->toDecimalString());
        $this->assertSame('1000000.00', $asset->fresh()->accumulated_amount);
        $this->assertTrue($asset->fresh()->isFullyDepreciated());

        // Sudah habis: periode berikutnya dilewati.
        $after = $this->service->depreciatePeriod($this->owner, '2026-11');
        $this->assertSame(0, $after['posted']);
    }

    public function test_disposed_future_and_locked_period_are_respected(): void
    {
        $disposed = $this->makeAsset();
        $this->service->dispose($this->owner, $disposed);
        $future = $this->service->create($this->owner, [
            'name' => 'Belum Datang', 'acquired_at' => '2026-12-01',
            'acquisition_cost' => '5000000', 'useful_life_months' => 10,
        ]);

        $result = $this->service->depreciatePeriod($this->owner, '2026-10');
        $this->assertSame(0, $result['posted']); // disposed + belum dimiliki
        $this->assertSame(0, Journal::where('source_type', 'depreciation')->count());

        // Periode terkunci: run gagal total, akumulasi tidak berubah.
        $active = $this->makeAsset();
        app(PostingService::class)->lockPeriod($this->owner, '2026-08');
        try {
            $this->service->depreciatePeriod($this->owner, '2026-08');
            $this->fail('Penyusutan ke periode terkunci harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
        $this->assertSame('0.00', $active->fresh()->accumulated_amount);
        $this->assertSame(0, Journal::where('source_type', 'depreciation')->count());
    }

    public function test_artisan_command_runs_depreciation(): void
    {
        $this->makeAsset();
        $this->artisan('lentera:penyusutan', ['periode' => '2026-08'])->assertSuccessful();
        $this->assertSame(1, Journal::where('source_type', 'depreciation')->count());
        $this->artisan('lentera:penyusutan', ['periode' => '2026-08'])->assertSuccessful();
        $this->assertSame(1, Journal::where('source_type', 'depreciation')->count());
    }

    private function makeUserWithRole(string $roleName): User
    {
        $user = User::create([
            'name' => $roleName, 'username' => strtolower($roleName).'-f14',
            'password' => bcrypt('password'), 'is_active' => true,
        ]);
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        $user->branches()->attach(Branch::firstOrFail());

        return $user;
    }
}
