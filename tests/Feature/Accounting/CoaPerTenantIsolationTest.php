<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountService;
use App\Modules\Accounting\Services\ManualJournalService;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * ISO-2 (audit isolasi menyeluruh 18 Nov 2026): bagan akun milik
 * per tenant. Setiap tenant menerima salinan bagannya sendiri saat
 * dibuat; tidak ada aktor tenant yang dapat melihat, mengubah,
 * menonaktifkan, atau memposting ke akun tenant lain / templat
 * platform. Ember NULL tetap bagan platform & instalasi mandiri.
 */
class CoaPerTenantIsolationTest extends TestCase
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

    private function kasOf(int $tenantId): Account
    {
        return Account::where('tenant_id', $tenantId)->where('code', Chart::KAS)->firstOrFail();
    }

    public function test_tenant_creation_seeds_its_own_chart(): void
    {
        [$tenantA] = $this->makeTenantOwner('a');

        $codes = Account::where('tenant_id', $tenantA->id)->orderBy('code')->pluck('code')->all();
        $template = Account::whereNull('tenant_id')->orderBy('code')->pluck('code')->all();

        $this->assertSame($template, $codes);
        $this->assertCount(24, $codes);
    }

    public function test_rename_and_create_stay_inside_own_bucket(): void
    {
        [$tenantA, $ownerA] = $this->makeTenantOwner('a');
        [$tenantB, $ownerB] = $this->makeTenantOwner('b');
        $kasA = $this->kasOf($tenantA->id);
        $kasB = $this->kasOf($tenantB->id);

        // B mengganti nama Kas MILIKNYA — Kas A tidak berubah.
        $this->actingAs($ownerB)->put(route('accounts.update', $kasB), [
            'code' => Chart::KAS, 'name' => 'Kas Milik B', 'group' => 'asset',
        ])->assertRedirect(route('accounts.show', $kasB));
        $this->assertSame('Kas Milik B', $kasB->fresh()->name);
        $this->assertSame('Kas', $kasA->fresh()->name);

        // Indeks A tidak memuat nama khas B; indeks B memuatnya.
        $this->actingAs($ownerA)->get(route('accounts.index'))
            ->assertOk()->assertDontSee('Kas Milik B');
        $this->actingAs($ownerB)->get(route('accounts.index'))
            ->assertOk()->assertSee('Kas Milik B');

        // Kode yang sama boleh hidup di dua ember berbeda.
        $this->actingAs($ownerB)->post(route('accounts.store'), [
            'code' => '9-9999', 'name' => 'Akun Uji B', 'group' => 'asset',
        ])->assertRedirect();
        $this->actingAs($ownerA)->post(route('accounts.store'), [
            'code' => '9-9999', 'name' => 'Akun Uji A', 'group' => 'asset',
        ])->assertRedirect();
        $this->assertSame('Akun Uji A', Account::where('tenant_id', $tenantA->id)->where('code', '9-9999')->firstOrFail()->name);
        $this->assertSame('Akun Uji B', Account::where('tenant_id', $tenantB->id)->where('code', '9-9999')->firstOrFail()->name);
    }

    public function test_cross_tenant_account_access_is_not_found(): void
    {
        [$tenantA] = $this->makeTenantOwner('a');
        [, $ownerB] = $this->makeTenantOwner('b');
        $kasA = $this->kasOf($tenantA->id);

        $this->actingAs($ownerB)->get(route('accounts.show', $kasA))->assertNotFound();
        $this->actingAs($ownerB)->get(route('accounts.edit', $kasA))->assertNotFound();
        $this->actingAs($ownerB)->put(route('accounts.update', $kasA), [
            'code' => Chart::KAS, 'name' => 'Dibajak B', 'group' => 'asset',
        ])->assertNotFound();
        $this->actingAs($ownerB)->post(route('accounts.toggle-active', $kasA))->assertNotFound();

        $this->assertSame('Kas', $kasA->fresh()->name);
        $this->assertTrue($kasA->fresh()->is_active);
    }

    public function test_service_refuses_cross_bucket_writes(): void
    {
        [$tenantA] = $this->makeTenantOwner('a');
        [, $ownerB] = $this->makeTenantOwner('b');
        $kasA = $this->kasOf($tenantA->id);
        $service = app(AccountService::class);

        foreach ([
            fn () => $service->update($ownerB, $kasA, ['name' => 'Dibajak']),
            fn () => $service->setActive($ownerB, $kasA, false),
        ] as $call) {
            try {
                $call();
                $this->fail('Penulisan lintas ember harus ditolak service.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Kas', $kasA->fresh()->name);
        $this->assertTrue($kasA->fresh()->is_active);
    }

    public function test_posting_lands_on_own_bucket_accounts(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->makeTenantOwner('a');
        [$tenantB] = $this->makeTenantOwner('b');

        $journal = app(PostingService::class)->post('uji', null, now(), [
            ['account' => Chart::KAS, 'branch_id' => $branchA->id, 'debit' => '1000'],
            ['account' => Chart::PENDAPATAN_PENJUALAN, 'branch_id' => $branchA->id, 'credit' => '1000'],
        ], 'Posting uji ISO-2', $ownerA);

        $this->assertSame($tenantA->id, (int) $journal->tenant_id);
        foreach ($journal->lines as $line) {
            $this->assertSame($tenantA->id, (int) $line->account->tenant_id);
        }
        $this->assertSame(0, (int) Account::where('tenant_id', $tenantB->id)
            ->whereHas('lines')->count());
    }

    public function test_manual_journal_cannot_point_at_foreign_account(): void
    {
        [$tenantA] = $this->makeTenantOwner('a');
        [, $ownerB, $branchB] = $this->makeTenantOwner('b');
        $kasA = $this->kasOf($tenantA->id);
        $pendapatanB = Account::where('tenant_id', $ownerB->tenant_id)
            ->where('code', Chart::PENDAPATAN_PENJUALAN)->firstOrFail();

        try {
            app(ManualJournalService::class)->postManual($ownerB, now()->toDateString(), 'Jurnal uji', [
                ['account_id' => $kasA->id, 'branch_id' => $branchB->id, 'debit' => '1000'],
                ['account_id' => $pendapatanB->id, 'branch_id' => $branchB->id, 'credit' => '1000'],
            ]);
            $this->fail('Jurnal manual menunjuk akun tenant lain harus ditolak.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_platform_bucket_is_template_not_tenant_charts(): void
    {
        [$tenantA] = $this->makeTenantOwner('a');

        // Ember platform (NULL) adalah templat — akun khas tenant
        // tidak menjadi bagian templat. (Antarmuka akun adalah fitur
        // paket tenant; aktor platform mengelolanya di tingkat
        // service, bukan lewat UI tenant.)
        Account::where('tenant_id', $tenantA->id)->where('code', Chart::KAS)
            ->update(['name' => 'Kas Khas Tenant A']);
        $this->assertSame('Kas', Account::whereNull('tenant_id')
            ->where('code', Chart::KAS)->firstOrFail()->name);
        $this->assertNull(Account::bucketOf($this->platform));

        // Akun yang dibuat platform masuk ember NULL dan tidak
        // muncul di indeks tenant yang sudah ada.
        app(AccountService::class)->create($this->platform, [
            'code' => '8-8888', 'name' => 'Akun Templat Baru', 'group' => 'asset',
        ]);
        $created = Account::where('code', '8-8888')->firstOrFail();
        $this->assertNull($created->tenant_id);
    }
}
