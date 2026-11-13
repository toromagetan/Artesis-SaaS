<?php

namespace Tests\Feature\Platform;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R4 — metadata cabang pada dashboard: payload halaman (HTML) dan
 * endpoint JSON /dashboard/data hanya boleh memuat nama/ID cabang
 * dalam wilayah aktor. Data keuangan aktor sendiri tetap benar;
 * aktor unassigned tidak memperoleh metadata cabang bisnis apa pun.
 */
class DashboardTenantMetadataTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        // Bekukan hari ke tanggal 20 agar jurnal tanggal 05 selalu
        // masuk jendela 30 hari dashboard (anti bom waktu tanggal).
        $this->travelTo(now()->setDay(20)->setTime(12, 0));
    }

    /** @return array{Tenant, Branch, User} tenant + cabang + Owner-nya */
    private function tenantOwner(string $key): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$key, 'slug' => 'optik-'.strtolower($key),
            'email' => strtolower($key).'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'team')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$key, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'name' => 'Owner '.$key, 'username' => 'owner-'.strtolower($key),
            'email' => 'owner-'.strtolower($key).'@uji.id', 'email_verified_at' => now(), 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        return [$tenant, $branch, $owner];
    }

    /** @return array{Branch, User, Branch, User} cabang+Owner Alpha, cabang+Owner Beta */
    private function twoTenantsWithJournals(): array
    {
        [, $branchA, $ownerA] = $this->tenantOwner('Alpha');
        [, $branchB, $ownerB] = $this->tenantOwner('Beta');

        $posting = app(PostingService::class);
        $month = now()->format('Y-m');
        $posting->post('sale', null, $month.'-05', [
            ['account' => '1-1100', 'debit' => '500000', 'branch_id' => $branchA->id],
            ['account' => '4-1100', 'credit' => '500000', 'branch_id' => $branchA->id],
        ], 'penjualan A', $ownerA);
        $posting->post('sale', null, $month.'-05', [
            ['account' => '1-1100', 'debit' => '900000', 'branch_id' => $branchB->id],
            ['account' => '4-1100', 'credit' => '900000', 'branch_id' => $branchB->id],
        ], 'penjualan B', $ownerB);

        return [$branchA, $ownerA, $branchB, $ownerB];
    }

    public function test_dashboard_payload_contains_only_own_tenant_branch_metadata(): void
    {
        [$branchA, $ownerA, $branchB, $ownerB] = $this->twoTenantsWithJournals();

        $response = $this->actingAs($ownerA)->get(route('dashboard'));
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertEquals([$branchA->id => 'Cabang Alpha'], $cards['finance']['branch_names']);
        $this->assertSame('500000.00', $cards['finance']['net']->toDecimalString());
        $response->assertDontSee('Cabang Beta');

        // Sisi sebaliknya simetris: Beta utuh, Alpha tidak bocor.
        $responseB = $this->actingAs($ownerB)->get(route('dashboard'));
        $responseB->assertOk();
        $cardsB = $responseB->viewData('cards');
        $this->assertEquals([$branchB->id => 'Cabang Beta'], $cardsB['finance']['branch_names']);
        $this->assertSame('900000.00', $cardsB['finance']['net']->toDecimalString());
        $responseB->assertDontSee('Cabang Alpha');
    }

    public function test_dashboard_data_endpoint_does_not_leak_other_tenant_metadata(): void
    {
        [, $ownerA] = $this->twoTenantsWithJournals();

        $json = $this->actingAs($ownerA)->getJson(route('dashboard.data'))->assertOk()->json();
        $this->assertStringContainsString('Cabang Alpha', $json['cards_html']);
        $this->assertStringNotContainsString('Cabang Beta', $json['cards_html']);
        $this->assertStringNotContainsString('Cabang Beta', $json['charts_html']);
    }

    public function test_unassigned_dashboard_has_no_business_branch_metadata(): void
    {
        $this->twoTenantsWithJournals();
        // Owner bawaan seeder: tenant_id null pada instalasi bertenant.
        $legacy = User::where('username', 'owner')->firstOrFail();

        $response = $this->actingAs($legacy)->get(route('dashboard'));
        $response->assertOk();
        $cards = $response->viewData('cards');
        $this->assertSame([], $cards['finance']['branch_names']);
        $response->assertDontSee('Cabang Alpha');
        $response->assertDontSee('Cabang Beta');
    }
}
