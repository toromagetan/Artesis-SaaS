<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F1.6 Dashboard keuangan: kartu laba bulan berjalan per cabang
 * terpagar reports.financial DAN cakupan cabang pemanggil;
 * posisi kas hanya untuk aktor berakses global.
 */
class DashboardFinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::firstOrFail();
        $this->branchB = Branch::create(['name' => 'Cabang B', 'is_active' => true]);

        $posting = app(PostingService::class);
        $month = now()->format('Y-m');
        // Cabang A: pendapatan 500rb, beban 100rb (bulan ini)
        $posting->post('sale', null, $month.'-05', [
            ['account' => '1-1100', 'debit' => '500000', 'branch_id' => $this->branchA->id],
            ['account' => '4-1100', 'credit' => '500000', 'branch_id' => $this->branchA->id],
        ], 'penjualan A', $this->owner);
        $posting->post('salary', null, $month.'-06', [
            ['account' => '5-1500', 'debit' => '100000', 'branch_id' => $this->branchA->id],
            ['account' => '1-1100', 'credit' => '100000', 'branch_id' => $this->branchA->id],
        ], 'gaji A', $this->owner);
        // Cabang B: pendapatan 900rb (bulan ini) — tak boleh bocor ke aktor terpagar A.
        $posting->post('sale', null, $month.'-07', [
            ['account' => '1-1200', 'debit' => '900000', 'branch_id' => $this->branchB->id],
            ['account' => '4-1100', 'credit' => '900000', 'branch_id' => $this->branchB->id],
        ], 'penjualan B', $this->owner);
    }

    public function test_owner_sees_finance_card_with_per_branch_income_and_cash(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'));
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertArrayHasKey('finance', $cards);
        // Laba bulan ini total: A 400rb + B 900rb.
        $this->assertSame('1300000.00', $cards['finance']['net']->toDecimalString());
        // Posisi kas+bank: 500rb − 100rb + 900rb.
        $this->assertSame('1300000.00', $cards['finance']['cash']->toDecimalString());
        $byBranch = collect($cards['finance']['by_branch'])->keyBy('branch_id');
        $this->assertSame('400000.00', $byBranch[$this->branchA->id]['net']->toDecimalString());
        $this->assertSame('900000.00', $byBranch[$this->branchB->id]['net']->toDecimalString());
        $response->assertSee(__('ui.dashboard.finance_period'));
    }

    public function test_branch_fenced_financial_user_sees_only_own_branch_and_no_cash(): void
    {
        $role = Role::create(['name' => 'KeuanganCabang', 'label' => 'Keuangan Cabang']);
        $role->permissions()->sync(Permission::whereIn('name', ['dashboard.view', 'reports.financial'])->pluck('id'));
        $user = User::create(['name' => 'Keu A', 'username' => 'keu-f16', 'password' => bcrypt('password'), 'is_active' => true]);
        $user->roles()->attach($role);
        $user->branches()->attach($this->branchA);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertArrayHasKey('finance', $cards);
        $this->assertSame('400000.00', $cards['finance']['net']->toDecimalString());
        $this->assertNull($cards['finance']['cash']);
        $this->assertCount(1, $cards['finance']['by_branch']);
    }

    public function test_user_without_financial_permission_has_no_finance_card(): void
    {
        $kasir = User::create(['name' => 'Kasir', 'username' => 'kasir-f16', 'password' => bcrypt('password'), 'is_active' => true]);
        $kasir->roles()->attach(Role::where('name', 'Kasir')->firstOrFail());
        $kasir->branches()->attach($this->branchA);

        $response = $this->actingAs($kasir)->get(route('dashboard'));
        $response->assertOk();
        $this->assertArrayNotHasKey('finance', $response->viewData('cards'));
    }
}
