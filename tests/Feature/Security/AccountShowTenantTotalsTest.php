<?php

namespace Tests\Feature\Security;

use App\Modules\Accounting\Models\Account;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit keamanan 17 Nov 2026, temuan F-3: halaman detail akun sempat
 * menjumlah baris jurnal SEMUA tenant (relasi lines() tak terfilter)
 * dan crash 500 bila baris terbaru milik tenant lain (relasi journal
 * null di view). Bagan akunnya sendiri global secara desain; angka
 * dan barisnya wajib terpagar ember aktor.
 */
class AccountShowTenantTotalsTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantOwner(string $tag): array
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
        $owner = User::create([
            'email_verified_at' => now(), 'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner, $branch];
    }

    private function account(): Account
    {
        $id = DB::table('accounts')->insertGetId([
            'code' => '8-8888', 'name' => 'Akun Uji Audit', 'group' => 'expense',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Account::findOrFail($id);
    }

    private function journalLine(int $tenantId, int $branchId, int $accountId, string $number, int $debit, string $memo): void
    {
        $journalId = DB::table('journals')->insertGetId([
            'number' => $number, 'journal_date' => now()->toDateString(),
            'source_type' => 'manual', 'description' => 'Jurnal uji '.$number,
            'tenant_id' => $tenantId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('journal_lines')->insert([
            'journal_id' => $journalId, 'account_id' => $accountId, 'branch_id' => $branchId,
            'debit' => $debit, 'credit' => 0, 'memo' => $memo,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_owner_sees_own_total(): void
    {
        [$tenantB, $ownerB, $branchB] = $this->tenantOwner('asa');
        $account = $this->account();
        $this->journalLine($tenantB->id, $branchB->id, $account->id, 'JU-ASA-1', 123456789, 'Memo tenant B');

        $response = $this->actingAs($ownerB)->get(route('accounts.show', $account));
        $response->assertOk();
        $response->assertSee('123.456.789');
    }

    public function test_totals_exclude_other_tenant_lines(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->tenantOwner('ata');
        [$tenantB, , $branchB] = $this->tenantOwner('atb');
        $account = $this->account();
        $this->journalLine($tenantB->id, $branchB->id, $account->id, 'JU-ATB-1', 123456789, 'Memo rahasia tenant B');
        for ($i = 0; $i < 10; $i++) {
            $this->journalLine($tenantA->id, $branchA->id, $account->id, 'JU-ATA-'.$i, 100, 'Baris tenant A '.$i);
        }

        $response = $this->actingAs($ownerA)->get(route('accounts.show', $account));
        $response->assertOk();
        // Total tenant A hanya 1.000; angka gabungan dan angka tenant B tidak boleh muncul.
        foreach (['123.457.789', '123457789', '123.456.789'] as $leak) {
            $this->assertStringNotContainsString($leak, $response->getContent());
        }
        $response->assertDontSee('Memo rahasia tenant B');
    }

    public function test_foreign_newest_lines_do_not_crash_owner_view(): void
    {
        [$tenantA, , $branchA] = $this->tenantOwner('aca');
        [$tenantB, $ownerB, $branchB] = $this->tenantOwner('acb');
        $account = $this->account();
        $this->journalLine($tenantB->id, $branchB->id, $account->id, 'JU-ACB-1', 500, 'Baris tenant B');
        for ($i = 0; $i < 10; $i++) {
            $this->journalLine($tenantA->id, $branchA->id, $account->id, 'JU-ACA-'.$i, 100, 'Baris asing '.$i);
        }

        $response = $this->actingAs($ownerB)->get(route('accounts.show', $account));
        $response->assertOk();
    }
}
