<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modul perbaikan payments (temuan D-02) — dokumen pembayaran
 * kolektif: cetak (payments.print) dan hasil proses
 * (payments.collective.result). PurchasePayment tidak memakai
 * scope, sehingga binding rute meloloskan pembayaran tenant mana
 * pun; CollectivePaymentController kini memeriksa kepemilikan
 * secara eksplisit sebelum memuat relasi: pemilik pembayaran
 * adalah tenant dari supplier-nya. Test ini membuktikan pemilik
 * tetap bisa membuka kedua halaman, tenant lain ditolak 404,
 * aktor platform dan unassigned ditolak, dan alur proses kolektif
 * lewat HTTP tidak regresi.
 */
class PaymentAccessTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    private Branch $branchB;

    private Supplier $supplierB;

    private Product $productB;

    private PurchasePayment $paymentA;

    private PurchasePayment $paymentB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $this->adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $this->adminB, $this->branchB] = $this->tenantAdmin('b');

        $this->paymentA = $this->makePayment($tenantA, $this->adminA, $branchA, 'A');
        $this->paymentB = $this->makePayment($tenantB, $this->adminB, $this->branchB, 'B');
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
            'name' => 'Admin Bayar '.$tag, 'username' => 'admin-bayar-'.$tag,
            'email' => 'admin-bayar-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    /** Rantai nyata per tenant: PO → terima → bayar kolektif via service. */
    private function makePayment(Tenant $tenant, User $admin, Branch $branch, string $tag): PurchasePayment
    {
        $supplier = Supplier::create(['name' => 'Supplier Bayar '.$tag, 'is_active' => true, 'tenant_id' => $tenant->id]);
        $product = Product::factory()->ofType('frame')->create(['tenant_id' => $tenant->id, 'cost_price' => '100000']);
        if ($tag === 'B') {
            $this->supplierB = $supplier;
            $this->productB = $product;
        }

        $receipt = $this->makeReceipt($admin, $supplier, $branch, $product);

        return app(CollectivePaymentService::class)->process($admin, $supplier, '100000', 'transfer');
    }

    private function makeReceipt(User $admin, Supplier $supplier, Branch $branch, Product $product): Receipt
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($admin, $supplier->id, $branch->id, [
            ['product_id' => $product->id, 'qty' => 1, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($admin, $po);

        return app(ReceiptService::class)->store($admin, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 1],
        ], '2026-11-01');
    }

    /** Aktor berizin rute tetapi tanpa konteks tenant yang sah. */
    private function privilegedActor(array $attributes): User
    {
        $role = Role::create(['name' => 'peninjau-bayar-'.uniqid(), 'label' => 'Peninjau Bayar']);
        $role->permissions()->sync(
            Permission::whereIn('name', ['po.view', 'payments.collective'])->pluck('id')
        );

        $user = User::factory()->create($attributes);
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_print_and_result_open_for_owning_tenant(): void
    {
        $this->actingAs($this->adminA)->get(route('payments.print', $this->paymentA))
            ->assertOk()->assertSee($this->paymentA->number);
        $this->actingAs($this->adminA)->get(route('payments.collective.result', $this->paymentA))
            ->assertOk();
        $this->actingAs($this->adminB)->get(route('payments.print', $this->paymentB))
            ->assertOk()->assertSee($this->paymentB->number);
        $this->actingAs($this->adminB)->get(route('payments.collective.result', $this->paymentB))
            ->assertOk();
    }

    public function test_print_and_result_denied_for_other_tenant(): void
    {
        $this->actingAs($this->adminB)->get(route('payments.print', $this->paymentA))->assertNotFound();
        $this->actingAs($this->adminB)->get(route('payments.collective.result', $this->paymentA))->assertNotFound();
        $this->actingAs($this->adminA)->get(route('payments.print', $this->paymentB))->assertNotFound();
        $this->actingAs($this->adminA)->get(route('payments.collective.result', $this->paymentB))->assertNotFound();
    }

    public function test_print_and_result_denied_for_platform_and_unassigned(): void
    {
        $platform = $this->privilegedActor(['is_platform' => true, 'tenant_id' => null]);
        $unassigned = $this->privilegedActor(['is_platform' => false, 'tenant_id' => null]);

        // Kedua aktor tanpa konteks tenant yang sah ditolak SEBELUM
        // dokumen termuat — berlapis: gerbang fitur paket menolak
        // aktor tanpa konteks tenant valid (403, lihat
        // PlanGateTenantContextTest), dan pagar kepemilikan di
        // controller adalah lapis terakhir (404). Pagar kepemilikan
        // sendiri terbukti tepat-404 pada kasus lintas tenant di
        // test sebelumnya, di mana aktor lolos gerbang paket + izin.
        foreach ([$platform, $unassigned] as $actor) {
            foreach ([route('payments.print', $this->paymentA), route('payments.collective.result', $this->paymentA)] as $url) {
                $status = $this->actingAs($actor)->get($url)->status();
                $this->assertContains($status, [403, 404], "Aktor tanpa konteks sah tidak boleh membuka {$url}");
            }
        }
    }

    public function test_collective_process_via_http_still_works_and_new_payment_is_fenced(): void
    {
        // Receipt kedua milik tenant B yang belum dibayar.
        $this->makeReceipt($this->adminB, $this->supplierB, $this->branchB, $this->productB);
        $before = PurchasePayment::count();

        $response = $this->actingAs($this->adminB)->post(route('payments.collective.process'), [
            'supplier_id' => $this->supplierB->id, 'amount' => '100000', 'method' => 'transfer',
        ]);

        $newPayment = PurchasePayment::latest('id')->firstOrFail();
        $response->assertRedirect(route('payments.collective.result', $newPayment));
        $this->assertSame($before + 1, PurchasePayment::count());
        $this->assertSame($this->supplierB->id, $newPayment->supplier_id);

        // Halaman hasil pembayaran baru terbuka untuk pemiliknya dan
        // tertutup untuk tenant lain.
        $this->actingAs($this->adminB)->get(route('payments.collective.result', $newPayment))->assertOk();
        $this->actingAs($this->adminA)->get(route('payments.collective.result', $newPayment))->assertNotFound();
        $this->actingAs($this->adminA)->get(route('payments.print', $newPayment))->assertNotFound();
    }
}
