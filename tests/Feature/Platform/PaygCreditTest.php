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
use App\Modules\Platform\Models\PaygCreditRequest;
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
 * PAYG murni (keputusan owner 2026-11-15): paket tambahan bayar
 * per transaksi penjualan (bawaan Rp500). Saldo kredit transaksi
 * prabayar per tenant meniru kredit AI, dengan dua beda disengaja:
 * potongan terjadi di SalesService saat penjualan tersimpan, dan
 * saldo BOLEH negatif (kasir tidak pernah di-hard-stop).
 */
class PaygCreditTest extends TestCase
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
    private function tenantOwner(string $tag, string $planKey = 'payg'): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
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

    private function giveCredit(Tenant $tenant, int $credits): void
    {
        $service = app(PaygCreditService::class);
        $request = $service->requestTopup($tenant, $credits, $this->platform);
        $service->confirm($request, $this->platform);
    }

    /** Penjualan frame sederhana Rp100.000 lunas tunai oleh owner tenant. */
    private function makeSale(User $owner, Tenant $tenant, Branch $branch, ?string $clientUuid = null): Sale
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

        $data = [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'inclusive', 'branch_id' => $branch->id,
        ];
        if ($clientUuid !== null) {
            $data['client_uuid'] = $clientUuid;
        }

        return app(SalesService::class)->createSale($owner, $data, [
            ['detail_type' => 'frame', 'detail_id' => $detail->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '100000'],
        ]);
    }

    public function test_payg_plan_feature_map_opens_everything_except_accounting(): void
    {
        [$tenant] = $this->tenantOwner('a');
        $plans = app(PlanService::class);

        $payg = Plan::where('key', 'payg')->firstOrFail();
        $this->assertSame(0, (int) $payg->price_monthly);
        $this->assertSame(0, (int) $payg->extra_branch_price);

        $this->assertFalse($plans->featureEnabled($tenant, 'accounting_full'));
        foreach (['reports_advanced', 'roles_permissions', 'crm_members', 'purchasing_full', 'whatsapp', 'api_access', 'ai_assistant'] as $key) {
            $this->assertTrue($plans->featureEnabled($tenant, $key), $key);
        }
        foreach (['products_max', 'users_max', 'branches_max', 'report_history_days'] as $key) {
            $this->assertNull($plans->featureLimit($tenant, $key), $key);
        }
    }

    public function test_topup_request_validation_pending_and_plan_guard(): void
    {
        [, $owner] = $this->tenantOwner('a');
        [, $soloOwner] = $this->tenantOwner('s', 'solo');

        // Di bawah rentang layanan (min 10) → ditolak validasi.
        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 5])
            ->assertSessionHasErrors('credits');
        $this->assertSame(0, PaygCreditRequest::count());

        // Harga baku Rp500/transaksi dibekukan di pengajuan.
        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 100])
            ->assertRedirect(route('plans.my'));
        $request = PaygCreditRequest::firstOrFail();
        $this->assertSame(100, $request->credits);
        $this->assertSame(500, $request->unit_price);
        $this->assertSame(50000, $request->amount);

        // Pengajuan kedua saat masih menunggu → ditolak.
        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 50])
            ->assertSessionHasErrors('credits');

        // Tenant non-PAYG tidak boleh mengajukan sama sekali.
        $this->actingAs($soloOwner)->post(route('payg-credits.store'), ['credits' => 100])
            ->assertForbidden();
    }

    public function test_platform_confirm_adds_balance_exactly_once_and_reject_moves_nothing(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);

        $this->actingAs($owner)->post(route('payg-credits.store'), ['credits' => 100]);
        $request = PaygCreditRequest::firstOrFail();

        $this->actingAs($this->platform)->post(route('platform.payg-credits.confirm', $request))
            ->assertRedirect(route('platform.billing.index'));
        $this->assertSame(100, $service->balance($tenant));

        // Konfirmasi ganda ditolak; saldo tidak bergerak dua kali.
        $this->actingAs($this->platform)->post(route('platform.payg-credits.confirm', $request))
            ->assertSessionHasErrors('status');
        $this->assertSame(100, $service->balance($tenant));

        // Penolakan menutup pengajuan tanpa pergerakan saldo.
        [$tenantB, $ownerB] = $this->tenantOwner('b');
        $this->actingAs($ownerB)->post(route('payg-credits.store'), ['credits' => 50]);
        $requestB = PaygCreditRequest::where('tenant_id', $tenantB->id)->firstOrFail();
        $this->actingAs($this->platform)->post(route('platform.payg-credits.reject', $requestB))
            ->assertRedirect(route('platform.billing.index'));
        $this->assertSame(PaygCreditRequest::STATUS_REJECTED, $requestB->refresh()->status);
        $this->assertSame(0, $service->balance($tenantB));
    }

    public function test_payg_sale_charges_exactly_one_credit(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 10);
        $service = app(PaygCreditService::class);

        $sale = $this->makeSale($owner, $tenant, $branch);

        $this->assertSame(9, $service->balance($tenant));
        $usage = PaygCreditLedger::where('tenant_id', $tenant->id)->where('kind', 'usage')->get();
        $this->assertCount(1, $usage);
        $this->assertSame($sale->id, $usage->first()->sale_id);
        $this->assertSame(-1, $usage->first()->credits_delta);
        $this->assertSame(9, $usage->first()->balance_after);
    }

    public function test_duplicate_offline_sync_does_not_double_charge(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 10);
        $service = app(PaygCreditService::class);

        $first = $this->makeSale($owner, $tenant, $branch, 'uuid-payg-1');
        $second = $this->makeSale($owner, $tenant, $branch, 'uuid-payg-1');

        // Sinkron ulang mengembalikan penjualan asli (client_uuid sama).
        $this->assertSame($first->id, $second->id);
        $this->assertSame(9, $service->balance($tenant));

        // Penjaga service: pemotongan langsung ganda pun idempoten.
        $service->chargeForSale($tenant, $first, $owner);
        $this->assertSame(9, $service->balance($tenant));
        $this->assertSame(1, PaygCreditLedger::where('kind', 'usage')->where('sale_id', $first->id)->count());
    }

    public function test_void_reverses_charge_exactly_once(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 10);
        $service = app(PaygCreditService::class);

        $sale = $this->makeSale($owner, $tenant, $branch);
        $this->assertSame(9, $service->balance($tenant));

        app(SalesService::class)->voidSale($owner, $sale, 'Salah input uji');
        $this->assertSame(10, $service->balance($tenant));
        $this->assertSame(1, PaygCreditLedger::where('kind', 'reversal')->where('sale_id', $sale->id)->count());

        // Pembalikan langsung kedua tidak menggandakan kredit.
        $service->reverseForSale($tenant, $sale, $owner);
        $this->assertSame(10, $service->balance($tenant));
        $this->assertSame(1, PaygCreditLedger::where('kind', 'reversal')->where('sale_id', $sale->id)->count());
    }

    public function test_non_payg_tenant_sale_charges_nothing(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('s', 'solo');
        $this->makeSale($owner, $tenant, $branch);
        $this->assertSame(0, PaygCreditLedger::where('tenant_id', $tenant->id)->count());
    }

    public function test_standalone_sale_charges_nothing(): void
    {
        // Instalasi mandiri: owner bawaan tanpa tenant. Test ini
        // berdiri sendiri karena begitu ada baris tenant, pengguna
        // tenant_id NULL non-platform berubah status menjadi
        // UNASSIGNED (bukan lagi standalone).
        $standalone = User::where('username', 'owner')->firstOrFail();
        $mainBranch = Branch::where('name', 'Pusat')->firstOrFail();
        $detail = FrameDetail::factory()->create([
            'product_id' => Product::factory()->forType('frame')->create(['name' => 'Frame Mandiri'])->id,
            'sale_price' => '100000',
        ]);
        app(InventoryService::class)->recordMovement(
            $standalone, $detail, $mainBranch, 'initial_stock', 10, 'test_seed', 1,
        );
        $customer = Customer::factory()->create(['branch_id' => $mainBranch->id]);
        app(SalesService::class)->createSale($standalone, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'inclusive', 'branch_id' => $mainBranch->id,
        ], [
            ['detail_type' => 'frame', 'detail_id' => $detail->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '100000'],
        ]);
        $this->assertSame(0, PaygCreditLedger::count());
    }

    public function test_zero_balance_sale_still_records_and_balance_goes_negative(): void
    {
        [$tenant, $owner, $branch] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);
        $this->assertSame(0, $service->balance($tenant));

        $sale = $this->makeSale($owner, $tenant, $branch);
        $this->assertSame(-1, $service->balance($tenant));
        $this->assertSame(1, PaygCreditLedger::where('kind', 'usage')->where('sale_id', $sale->id)->count());

        $this->makeSale($owner, $tenant, $branch);
        $this->assertSame(-2, $service->balance($tenant));
    }

    public function test_charges_are_isolated_between_tenants(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->tenantOwner('a');
        [$tenantB, $ownerB, $branchB] = $this->tenantOwner('b');
        $this->giveCredit($tenantA, 10);
        $service = app(PaygCreditService::class);

        $saleB = $this->makeSale($ownerB, $tenantB, $branchB);

        $this->assertSame(10, $service->balance($tenantA));
        $this->assertSame(-1, $service->balance($tenantB));
        $this->assertSame(0, PaygCreditLedger::where('tenant_id', $tenantA->id)->where('kind', 'usage')->count());
        $this->assertSame($saleB->id, PaygCreditLedger::where('tenant_id', $tenantB->id)->where('kind', 'usage')->firstOrFail()->sale_id);
    }

    public function test_price_comes_from_platform_setting_with_default_500(): void
    {
        [$tenant] = $this->tenantOwner('a');
        $service = app(PaygCreditService::class);
        $this->assertSame(500, $service->pricePerTransaction());

        $this->actingAs($this->platform)->put(route('platform.billing.settings'), [
            'payg_price_per_transaction' => 750,
        ])->assertRedirect();
        $this->assertSame(750, $service->pricePerTransaction());

        // Pengajuan berikutnya membekukan harga baru.
        $request = $service->requestTopup($tenant, 100, $this->platform);
        $this->assertSame(750, $request->unit_price);
        $this->assertSame(75000, $request->amount);
    }

    public function test_my_plan_shows_payg_block_only_for_payg_tenants(): void
    {
        [$tenant, $owner] = $this->tenantOwner('a');
        $this->giveCredit($tenant, 25);
        [, $soloOwner] = $this->tenantOwner('s', 'solo');

        $this->actingAs($owner)->get(route('plans.my'))
            ->assertOk()
            ->assertSee(__('ui.payg_credits.title'))
            ->assertSee('25');

        $this->actingAs($soloOwner)->get(route('plans.my'))
            ->assertOk()
            ->assertDontSee(__('ui.payg_credits.title'));
    }
}
