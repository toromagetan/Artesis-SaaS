<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AiCreditLedger;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\AiCreditService;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Patok harga kredit AI (keputusan owner 2026-11-19, meniru
 * patok harga PAYG PR #85): setiap baris usage kredit AI mencap
 * unit_price tarif yang berlaku SAAT jawaban terjadi — agar
 * nilai rupiah pemakaian dapat direkonstruksi per jawaban walau
 * tarif platform berubah sesudahnya. Harga bawaan per
 * pertanyaan Rp100 adalah ketetapan owner 2026-11-19.
 */
class AiCreditPricePinningTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User} */
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
            'email_verified_at' => now(),
            'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner];
    }

    private function giveCredit(Tenant $tenant, int $questions): void
    {
        $service = app(AiCreditService::class);
        $request = $service->requestTopup($tenant, $questions, $this->platform);
        $service->confirm($request, $this->platform);
    }

    private function setPrice(int $price): void
    {
        $this->actingAs($this->platform)->put(route('platform.billing.settings'), [
            'ai_price_per_question' => $price,
        ])->assertRedirect();
    }

    /** @return list<AiCreditLedger> */
    private function usageRows(Tenant $tenant): array
    {
        return AiCreditLedger::where('tenant_id', $tenant->id)
            ->where('kind', 'usage')->orderBy('id')->get()->all();
    }

    public function test_usage_row_pins_the_price_in_effect_at_answer_time(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(AiCreditService::class);
        $this->giveCredit($tenant, 10);

        $this->assertTrue($service->chargeForAnswer($tenant, $owner));

        $rows = $this->usageRows($tenant);
        $this->assertCount(1, $rows);
        // Harga bawaan adalah ketetapan owner: Rp100/pertanyaan.
        $this->assertSame(100, (int) $rows[0]->unit_price);
        $this->assertSame(100, (int) $rows[0]->amount);
        $this->assertSame(9, $service->balance($tenant));
    }

    public function test_each_usage_keeps_its_own_price_across_a_tariff_change(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(AiCreditService::class);
        $this->giveCredit($tenant, 10);

        $this->assertTrue($service->chargeForAnswer($tenant, $owner));
        $this->setPrice(500);
        $this->assertTrue($service->chargeForAnswer($tenant, $owner));

        $rows = $this->usageRows($tenant);
        $this->assertCount(2, $rows);
        $this->assertSame(100, (int) $rows[0]->unit_price);
        $this->assertSame(500, (int) $rows[1]->unit_price);
        // Rekonsiliasi: nilai pemakaian adalah jumlah harga terpatok
        // masing-masing baris (100 + 500), bukan 2 x tarif berjalan.
        $this->assertSame(600, (int) collect($rows)->sum('amount'));
        $this->assertSame(8, $service->balance($tenant));
    }

    public function test_empty_balance_charges_nothing_and_writes_no_usage_row(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(AiCreditService::class);

        $this->assertFalse($service->chargeForAnswer($tenant, $owner));

        $this->assertCount(0, $this->usageRows($tenant));
        $this->assertSame(0, $service->balance($tenant));
    }

    public function test_topup_rows_are_not_usage_and_keep_their_frozen_price(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(AiCreditService::class);

        // Top-up dibekukan pada harga saat pengajuan (100).
        $this->giveCredit($tenant, 10);
        $this->setPrice(500);
        $this->assertTrue($service->chargeForAnswer($tenant, $owner));

        $topup = AiCreditLedger::where('tenant_id', $tenant->id)
            ->where('kind', 'topup')->firstOrFail();
        $this->assertSame(100, (int) $topup->unit_price);
        $this->assertSame(1000, (int) $topup->amount);

        // Baris usage memakai tarif berjalan saat jawaban (500),
        // bukan harga beku top-up.
        $rows = $this->usageRows($tenant);
        $this->assertCount(1, $rows);
        $this->assertSame(500, (int) $rows[0]->unit_price);
    }
}
