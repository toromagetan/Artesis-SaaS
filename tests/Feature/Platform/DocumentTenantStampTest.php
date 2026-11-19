<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CustomerCreditService;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use App\Modules\Sales\Services\WarrantyService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Keputusan C PR-1 — pencapan: setiap jalur pembuatan dokumen
 * mencap tenant_id dari ember yang sama dengan yang dipakai
 * Numbering mencetak nomornya (cabang dokumen; untuk pembayaran
 * pembelian: tenant pemasok). Instalasi mandiri mencap NULL.
 */
class DocumentTenantStampTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantFixture(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
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

    private function tenantFrame(Tenant $tenant): FrameDetail
    {
        return FrameDetail::factory()->create([
            'product_id' => Product::factory()->forType('frame')
                ->create(['name' => 'Frame '.Str::random(6), 'tenant_id' => $tenant->id])->id,
            'sku' => 'TS-'.strtoupper(Str::random(10)),
            'cost_price' => '40000', 'sale_price' => '100000', 'tenant_id' => $tenant->id,
        ]);
    }

    private function makeSale(User $owner, Tenant $tenant, Branch $branch): Sale
    {
        $detail = $this->tenantFrame($tenant);
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

    public function test_sale_return_and_credit_carry_branch_tenant(): void
    {
        [$tenant, $owner, $branch] = $this->tenantFixture('a');

        $sale = $this->makeSale($owner, $tenant, $branch);
        $this->assertSame($tenant->id, (int) $sale->tenant_id);

        $return = app(SaleReturnService::class)->create($owner, $sale, [
            ['sale_item_id' => $sale->items->first()->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Ukuran tidak cocok', 'store_credit');
        $this->assertSame($tenant->id, (int) $return->tenant_id);

        $credit = app(CustomerCreditService::class)->issueFromReturn($owner, $return);
        $this->assertNotNull($credit);
        $this->assertSame($tenant->id, (int) $credit->tenant_id);
    }

    public function test_warranty_case_carries_branch_tenant(): void
    {
        [$tenant, $owner, $branch] = $this->tenantFixture('a');
        $sale = $this->makeSale($owner, $tenant, $branch);

        $case = app(WarrantyService::class)->createCase(
            $owner, $sale, $sale->items->first(), 1, 'Cacat produksi', null,
        );

        $this->assertSame($tenant->id, (int) $case->tenant_id);
    }

    public function test_purchase_chain_carries_tenant_and_payment_uses_supplier_bucket(): void
    {
        [$tenant, $owner, $branch] = $this->tenantFixture('a');
        $supplier = Supplier::create(['name' => 'PT Uji', 'is_active' => true, 'tenant_id' => $tenant->id]);
        $frame = $this->tenantFrame($tenant);

        $pr = app(PurchaseRequestService::class)->create($owner, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 2, 'estimated_price' => '40000'],
        ]);
        $this->assertSame($tenant->id, (int) $pr->tenant_id);

        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($owner, $supplier->id, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 2, 'unit_cost' => '100000'],
        ]);
        $this->assertSame($tenant->id, (int) $po->tenant_id);
        $orders->markSent($owner, $po);

        $receipt = app(ReceiptService::class)->store($owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 2],
        ], now()->toDateString());
        $this->assertSame($tenant->id, (int) $receipt->tenant_id);

        $retur = app(PurchaseReturnService::class)->store($owner, $receipt, [
            ['receipt_item_id' => $receipt->items->first()->id, 'qty' => 1],
        ], 'Kemasan rusak');
        $this->assertSame($tenant->id, (int) $retur->tenant_id);

        $payment = app(PurchasePaymentService::class)->paySingle($owner, $receipt->refresh(), '100000', 'cash');
        $this->assertSame($tenant->id, (int) $payment->tenant_id);
    }

    public function test_transfer_between_own_branches_carries_tenant(): void
    {
        [$tenant, $owner, $branch] = $this->tenantFixture('a');
        $branchB = Branch::create(['name' => 'Cabang Kedua A', 'tenant_id' => $tenant->id]);
        $owner->branches()->attach($branchB);
        $frame = $this->tenantFrame($tenant);
        app(InventoryService::class)->recordMovement(
            $owner, $frame, $branch, 'initial_stock', 5, 'test_seed', 1,
        );

        $transfer = app(TransferService::class)->sendFromBranchStock($owner, $branch->id, $branchB->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1],
        ]);

        $this->assertSame($tenant->id, (int) $transfer->tenant_id);
    }

    public function test_each_tenant_stamps_its_own_id(): void
    {
        [$tenantA, $ownerA, $branchA] = $this->tenantFixture('a');
        [$tenantB, $ownerB, $branchB] = $this->tenantFixture('b');
        $frameB = $this->tenantFrame($tenantB);

        $prB = app(PurchaseRequestService::class)->create($ownerB, $branchB->id, [
            ['detail_type' => 'frame', 'detail_id' => $frameB->id, 'qty' => 1],
        ]);

        $this->assertSame($tenantB->id, (int) $prB->tenant_id);
        $this->assertNotSame($tenantA->id, (int) $prB->tenant_id);
    }

    public function test_standalone_documents_stamp_null(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $frame = FrameDetail::factory()->create(['cost_price' => '40000', 'sale_price' => '100000']);

        $pr = app(PurchaseRequestService::class)->create($owner, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1],
        ]);

        $this->assertNull($pr->tenant_id);
    }
}
