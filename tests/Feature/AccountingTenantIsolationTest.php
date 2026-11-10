<?php

namespace Tests\Feature;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modul B1 — Akuntansi per tenant (audit ulang isolasi
 * 10 Nov 2026): jurnal dicap tenant pemiliknya dan hanya
 * terlihat tenant itu; kunci periode berlaku per tenant —
 * satu tenant menutup bulan tidak mengunci tenant lain;
 * laporan keuangan (laba rugi) hanya mengagregat jurnal
 * tenant aktor.
 */
class AccountingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
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

    /** Jurnal pendapatan sederhana: Dr Kas / Cr Pendapatan Penjualan. */
    private function revenueJournal(User $actor, Branch $branch, string $amount): Journal
    {
        return app(PostingService::class)->post('manual', null, now()->toDateString(), [
            ['account' => '1-1100', 'branch_id' => $branch->id, 'debit' => $amount],
            ['account' => '4-1100', 'branch_id' => $branch->id, 'credit' => $amount],
        ], 'Jurnal uji '.$actor->username, $actor);
    }

    public function test_journals_are_stamped_and_hidden_across_tenants(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB, $branchB] = $this->tenantAdmin('b');

        $journalA = $this->revenueJournal($adminA, $branchA, '500000');
        $this->revenueJournal($adminB, $branchB, '900000');

        $this->assertSame($tenantA->id, (int) $journalA->tenant_id);

        $this->actingAs($adminB)->get(route('journals.index'))
            ->assertOk()
            ->assertDontSee($journalA->number);

        $this->actingAs($adminB)->get(route('journals.show', $journalA))
            ->assertNotFound();

        $this->actingAs($adminA)->get(route('journals.show', $journalA))
            ->assertOk();
    }

    public function test_period_lock_applies_per_tenant(): void
    {
        [, $adminA, $branchA] = $this->tenantAdmin('a');
        [, $adminB, $branchB] = $this->tenantAdmin('b');
        $period = now()->format('Y-m');
        $posting = app(PostingService::class);

        $posting->lockPeriod($adminA, $period);

        $this->assertTrue($posting->isPeriodLocked($period, $adminA->tenant_id));
        $this->assertFalse($posting->isPeriodLocked($period, $adminB->tenant_id));

        try {
            $this->revenueJournal($adminA, $branchA, '1000');
            $this->fail('Posting tenant A seharusnya ditolak pada periode terkunci.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        $journalB = $this->revenueJournal($adminB, $branchB, '2000');
        $this->assertSame($adminB->tenant_id, (int) $journalB->tenant_id);

        $posting->unlockPeriod($adminA, $period, 'koreksi uji');
        $this->assertFalse($posting->isPeriodLocked($period, $adminA->tenant_id));
    }

    public function test_financial_reports_only_aggregate_own_tenant(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $adminB, $branchB] = $this->tenantAdmin('b');
        $this->revenueJournal($adminA, $branchA, '500000');
        $this->revenueJournal($adminB, $branchB, '900000');

        $reports = app(FinancialReportService::class);
        $from = now()->startOfMonth()->toDateString();
        $to = now()->toDateString();

        $statementB = $reports->incomeStatement($from, $to, null, $tenantB->id);
        $this->assertSame('900000.00', $statementB['total_revenue']->toDecimalString());

        $statementA = $reports->incomeStatement($from, $to, null, $tenantA->id);
        $this->assertSame('500000.00', $statementA['total_revenue']->toDecimalString());

        $sheetB = $reports->balanceSheet($to, $tenantB->id);
        $this->assertSame('900000.00', $sheetB['earnings']->toDecimalString());

        // Ekspor laba-rugi tenant B tidak memuat angka tenant A.
        $csv = $this->actingAs($adminB)->get(route('financial.income', ['ekspor' => 1]));
        $csv->assertOk();
        $this->assertStringNotContainsString('500000', $csv->streamedContent());
        $this->assertStringContainsString('900000', $csv->streamedContent());
    }

    /**
     * Temuan 1 (koreksi audit independen): indeks unik gabungan
     * (period, tenant_id) TIDAK menjamin keunikan ember NULL di
     * MySQL. Kolom generated tenant_key kini menegakkannya:
     * dua kunci periode yang sama untuk instalasi mandiri tidak
     * dapat disimpan, termasuk sisipan langsung bersamaan,
     * sementara ember tenant berbeda tetap dapat berdampingan.
     */
    public function test_period_lock_uniqueness_covers_null_tenant_bucket(): void
    {
        // Jalur layanan ember mandiri dijalankan SEBELUM tenant
        // mana pun dibuat (sesudah itu akun tanpa tenant berubah
        // klasifikasi menjadi unassigned).
        $owner = User::where('username', 'owner')->firstOrFail();
        $posting = app(PostingService::class);
        $posting->lockPeriod($owner, '2031-05');
        $posting->lockPeriod($owner, '2031-05');
        $this->assertSame(1, DB::table('period_locks')->where('period', '2031-05')->count());
        $this->assertTrue($posting->isPeriodLocked('2031-05', null));

        [$tenantA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        $period = '2031-04';
        $insert = fn (?int $tenantId) => DB::table('period_locks')->insert([
            'period' => $period, 'tenant_id' => $tenantId,
            'locked_by' => $this->platform->id, 'locked_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $insert(null);
        $insert($tenantA->id);
        $insert($tenantB->id);
        $this->assertSame(3, DB::table('period_locks')->where('period', $period)->count());

        foreach ([null, $tenantA->id] as $dupe) {
            try {
                $insert($dupe);
                $this->fail('Kunci periode ganda seharusnya ditolak basis data.');
            } catch (QueryException) {
                // Ditolak indeks unik (period, tenant_key).
            }
        }
        $this->assertSame(3, DB::table('period_locks')->where('period', $period)->count());
    }

    /**
     * Temuan 2: PostingService gagal-tertutup — aktor platform &
     * unassigned ditolak; cabang baris wajib seember dengan
     * jurnal; penurunan tanpa aktor menuntut cabang seember.
     */
    public function test_posting_service_fails_closed_for_unauthorized_contexts(): void
    {
        [, $adminA, $branchA] = $this->tenantAdmin('a');
        [, , $branchB] = $this->tenantAdmin('b');
        $posting = app(PostingService::class);
        $lines = fn (Branch $b) => [
            ['account' => '1-1100', 'branch_id' => $b->id, 'debit' => '1000'],
            ['account' => '4-1100', 'branch_id' => $b->id, 'credit' => '1000'],
        ];

        // Aktor platform: ditolak walau baris menunjuk cabang nyata.
        try {
            $posting->post('manual', null, now()->toDateString(), $lines($branchA), 'uji', $this->platform);
            $this->fail('Aktor platform seharusnya ditolak.');
        } catch (AuthorizationException) {
        }

        // Aktor unassigned (tanpa tenant pada instalasi bersama).
        $unassigned = User::create([
            'name' => 'Yatim', 'username' => 'yatim-akuntansi',
            'email' => 'yatim-akuntansi@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => null,
        ]);
        try {
            $posting->post('manual', null, now()->toDateString(), $lines($branchA), 'uji', $unassigned);
            $this->fail('Aktor unassigned seharusnya ditolak.');
        } catch (AuthorizationException) {
        }

        // Aktor tenant A dengan baris cabang tenant B: ditolak.
        try {
            $posting->post('manual', null, now()->toDateString(), $lines($branchB), 'uji', $adminA);
            $this->fail('Baris cabang tenant lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        // Tanpa aktor + cabang campuran dua tenant: ditolak.
        try {
            $posting->post('sale', 1, now()->toDateString(), [
                ['account' => '1-1100', 'branch_id' => $branchA->id, 'debit' => '1000'],
                ['account' => '4-1100', 'branch_id' => $branchB->id, 'credit' => '1000'],
            ], 'uji', null);
            $this->fail('Cabang campuran lintas tenant seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        // Tanpa aktor + tanpa cabang: identitas sumber tak terbukti.
        try {
            $posting->post('sale', 1, now()->toDateString(), [
                ['account' => '1-1100', 'debit' => '1000'],
                ['account' => '4-1100', 'credit' => '1000'],
            ], 'uji', null);
            $this->fail('Posting tanpa aktor & tanpa cabang seharusnya ditolak.');
        } catch (AuthorizationException) {
        }

        // Tanpa aktor + cabang seember (jalur listener domain): sah
        // dan tercap tenant pemilik cabang.
        $journal = $posting->post('sale', 99, now()->toDateString(), $lines($branchA), 'uji', null);
        $this->assertSame($adminA->tenant_id, (int) $journal->tenant_id);
        $this->assertDatabaseCount('journals', 1);
    }

    /**
     * Temuan 2 (penimpa): tenantOverride bukan jalan pintas —
     * tenant yang tidak ada ditolak, dan aktor tidak dapat
     * menimpa ke ember tenant lain.
     */
    public function test_posting_tenant_override_is_validated(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        $posting = app(PostingService::class);
        $lines = [
            ['account' => '1-1100', 'branch_id' => $branchA->id, 'debit' => '1000'],
            ['account' => '4-1100', 'branch_id' => $branchA->id, 'credit' => '1000'],
        ];

        try {
            $posting->post('depreciation', 1, now()->toDateString(), $lines, 'uji', $adminA, null, $tenantB->id, true);
            $this->fail('Penimpa ke tenant lain seharusnya ditolak.');
        } catch (AuthorizationException) {
        }

        try {
            $posting->post('depreciation', 1, now()->toDateString(), $lines, 'uji', null, null, 999999, true);
            $this->fail('Penimpa ke tenant yang tidak ada seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        $journal = $posting->post('depreciation', 1, now()->toDateString(), $lines, 'uji', null, null, $tenantA->id, true);
        $this->assertSame($tenantA->id, (int) $journal->tenant_id);
    }

    /**
     * Temuan 6: tenant yang masih memiliki data bisnis TIDAK
     * dapat dihapus — FK restrictOnDelete menjaga kepemilikan
     * data agar tidak berubah menjadi ember instalasi mandiri.
     * Tenant tanpa data bisnis tetap dapat dihapus.
     */
    public function test_tenant_with_business_data_cannot_be_deleted(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        $journal = $this->revenueJournal($adminA, $branchA, '500000');

        try {
            $tenantA->delete();
            $this->fail('Penghapusan tenant berdata bisnis seharusnya ditolak basis data.');
        } catch (QueryException) {
        }
        $this->assertDatabaseHas('tenants', ['id' => $tenantA->id]);
        $this->assertSame($tenantA->id, (int) $journal->fresh()->tenant_id);

        $empty = Tenant::create(['name' => 'Optik Kosong', 'slug' => 'optik-kosong', 'email' => 'kosong@uji.id']);
        $empty->delete();
        $this->assertDatabaseMissing('tenants', ['id' => $empty->id]);
    }
}
