<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\PaygCreditLedger;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PaygCreditService;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Patok harga PAYG (keputusan owner 2026-11-18, menutup F-8):
 * setiap baris usage PAYG mencap unit_price tarif yang berlaku
 * SAAT penjualan terjadi, dan baris reversal mencerminkan harga
 * baris usage asal — agar nilai rupiah pemakaian (termasuk
 * tunggakan saldo negatif) dapat direkonsiliasi per transaksi
 * walau tarif platform berubah sesudahnya.
 */
class PaygPricePinningTest extends TestCase
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
    private function tenantOwner(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'payg')->firstOrFail(),
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

        return [$tenant, $owner, $branch];
    }

    private function setPrice(int $price): void
    {
        $this->actingAs($this->platform)->put(route('platform.billing.settings'), [
            'payg_price_per_transaction' => $price,
        ])->assertRedirect();
    }

    /** Penjualan frame sederhana Rp100.000 lunas tunai oleh owner tenant. */
    private function makeSale(User $owner, Tenant $tenant, Branch $branch): Sale
    {
        $detail = FrameDetail::factory()->create([
            'product_id' => Product::factory()->forType('frame')
                ->create(['name' => 'Frame '.Str::random(6), 'tenant_id' => $tenant->id])->id,
            'sku' => 'PG-'.strtoupper(Str::random(10)),
            'cost_price' => '40000', 'sale_price' => '100000', 'tenant_id' => $tenant->id,
        ]);
        app(InventoryService::class)->recordMovement(
            $owner, $detail, $branch, 'initial_stock', 10, 'test_seed', 1,
        );
        $customer = Customer::factory()->create(['branch_id' => $branch->id, 'tenant_id' => $tenant->id]);

        return app(SalesService::class)->createSale($owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'inclusive', 'branch_id' => $branch->id,
        ], [
            ['detail_type' => 'frame', 'detail_id' => $detail->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '100000'],
        ]);
    }

    private function usageRow(Tenant $tenant, Sale $sale): PaygCreditLedger
    {
        return PaygCreditLedger::where('tenant_id', $tenant->id)
            ->where('kind', 'usage')->where('sale_id', $sale->id)->firstOrFail();
    }

    public function test_usage_row_pins_the_tariff_in_effect_at_sale_time(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');

        $sale = $this->makeSale($owner, $tenant, $branch);

        $row = $this->usageRow($tenant, $sale);
        $this->assertSame(500, (int) $row->unit_price);
        $this->assertSame(500, (int) $row->amount);
        $this->assertSame(500, app(PaygCreditService::class)->consumedAmount($tenant));
    }

    public function test_each_usage_keeps_its_own_price_across_a_tariff_change(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);

        $first = $this->makeSale($owner, $tenant, $branch);
        $this->setPrice(750);
        $second = $this->makeSale($owner, $tenant, $branch);

        $this->assertSame(500, (int) $this->usageRow($tenant, $first)->unit_price);
        $this->assertSame(750, (int) $this->usageRow($tenant, $second)->unit_price);
        // Rekonsiliasi: nilai pemakaian adalah jumlah harga terpatok
        // masing-masing baris, bukan kredit x tarif berjalan (2 x 750).
        $this->assertSame(1250, $service->consumedAmount($tenant));
        $this->assertSame(-2, $service->balance($tenant));
    }

    public function test_reversal_mirrors_the_original_usage_price_not_the_current_tariff(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);

        $sale = $this->makeSale($owner, $tenant, $branch);
        $this->setPrice(750);
        $service->reverseForSale($tenant, $sale, $owner);

        $reversal = PaygCreditLedger::where('tenant_id', $tenant->id)
            ->where('kind', 'reversal')->where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame(500, (int) $reversal->unit_price);
        $this->assertSame(500, (int) $reversal->amount);
        $this->assertSame(0, $service->consumedAmount($tenant));
        $this->assertSame(0, $service->balance($tenant));
    }

    public function test_consumed_amount_counts_usage_only_not_topups(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);

        $request = $service->requestTopup($tenant, 10, $this->platform);
        $service->confirm($request, $this->platform);
        $this->makeSale($owner, $tenant, $branch);

        // Top-up Rp5.000 adalah uang masuk, bukan pemakaian.
        $this->assertSame(500, $service->consumedAmount($tenant));
        $this->assertSame(9, $service->balance($tenant));
    }
}
