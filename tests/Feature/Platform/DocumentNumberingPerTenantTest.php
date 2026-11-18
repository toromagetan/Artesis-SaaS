<?php

namespace Tests\Feature\Platform;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Support\Numbering;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ISO-1 (audit isolasi menyeluruh 18 Nov 2026): nomor dokumen per
 * tenant. Setiap tenant menaiki tangga nomornya sendiri mulai 0001
 * — celah nomor tidak lagi membocorkan volume tenant lain, dan
 * nomor yang sama sah hidup di dua tenant berbeda. Ember NULL
 * tetap tangga platform/instalasi mandiri.
 */
class DocumentNumberingPerTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{0: Tenant, 1: User, 2: Branch} */
    private function makeTenantOwner(string $tag): array
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
        $owner = User::forceCreate([
            'name' => 'Owner '.$tag, 'username' => 'owner'.$tag,
            'email' => 'owner'.$tag.'@uji.id', 'password' => bcrypt('password'),
            'tenant_id' => $tenant->id, 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner, $branch];
    }

    public function test_journal_numbers_run_on_independent_ladders(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->makeTenantOwner('a');
        [$tenantB, $ownerB, $branchB] = $this->makeTenantOwner('b');
        $posting = app(PostingService::class);
        $period = now()->format('Ym');

        $journalA1 = $posting->post('uji', null, now(), [
            ['account' => Chart::KAS, 'branch_id' => $branchA->id, 'debit' => '1000'],
            ['account' => Chart::PENDAPATAN_PENJUALAN, 'branch_id' => $branchA->id, 'credit' => '1000'],
        ], 'Jurnal A1', $ownerA);
        $journalB1 = $posting->post('uji', null, now(), [
            ['account' => Chart::KAS, 'branch_id' => $branchB->id, 'debit' => '2000'],
            ['account' => Chart::PENDAPATAN_PENJUALAN, 'branch_id' => $branchB->id, 'credit' => '2000'],
        ], 'Jurnal B1', $ownerB);
        $journalA2 = $posting->post('uji', null, now(), [
            ['account' => Chart::KAS, 'branch_id' => $branchA->id, 'debit' => '3000'],
            ['account' => Chart::PENDAPATAN_PENJUALAN, 'branch_id' => $branchA->id, 'credit' => '3000'],
        ], 'Jurnal A2', $ownerA);

        // Kedua tenant mulai dari 0001; A naik ke 0002 tanpa
        // terpengaruh aktivitas B.
        $this->assertSame('JU-'.$period.'-0001', $journalA1->number);
        $this->assertSame('JU-'.$period.'-0001', $journalB1->number);
        $this->assertSame('JU-'.$period.'-0002', $journalA2->number);

        // Dua baris penghitung JU — satu per tenant, tanpa ember NULL.
        $counters = DB::table('document_counters')->where('prefix', 'JU')->get();
        $this->assertCount(2, $counters);
        $this->assertEqualsCanonicalizing(
            [$tenantA->id => 2, $tenantB->id => 1],
            $counters->mapWithKeys(fn ($row) => [(int) $row->tenant_id => (int) $row->last_number])->all(),
        );
    }

    public function test_branch_derived_numbering_uses_branch_tenant(): void
    {
        [$tenantA, , $branchA] = $this->makeTenantOwner('a');
        [$tenantB, , $branchB] = $this->makeTenantOwner('b');
        $period = now()->format('Ym');

        $this->assertSame('INV-'.$period.'-0001', Numbering::nextForBranch('INV', $branchA->id));
        $this->assertSame('INV-'.$period.'-0001', Numbering::nextForBranch('INV', $branchB->id));
        $this->assertSame('INV-'.$period.'-0002', Numbering::nextForBranch('INV', $branchA->id));

        $this->assertSame($tenantA->id, (int) DB::table('document_counters')
            ->where('prefix', 'INV')->where('last_number', 2)->value('tenant_id'));
        $this->assertSame($tenantB->id, (int) DB::table('document_counters')
            ->where('prefix', 'INV')->where('last_number', 1)->value('tenant_id'));
    }

    public function test_standalone_bucket_keeps_single_ladder(): void
    {
        // Tanpa tenant sama sekali: ember NULL adalah satu-satunya
        // tangga (perilaku instalasi mandiri tidak berubah).
        $period = now()->format('Ym');

        $this->assertSame('INV-'.$period.'-0001', Numbering::next('INV', now(), null, true));
        $this->assertSame('INV-'.$period.'-0002', Numbering::next('INV', now(), null, true));
        $this->assertSame(1, (int) DB::table('document_counters')->where('prefix', 'INV')->count());
    }
}
