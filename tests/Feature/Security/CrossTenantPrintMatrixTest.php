<?php

namespace Tests\Feature\Security;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\PurchasePaymentAllocation;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleReturn;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * D-02 GAP-1 — Matriks cetak lintas tenant. Seluruh kelompok rute
 * cetak (penjualan, retur, pembelian, penerimaan, pembayaran,
 * transfer, jurnal, resep, pengguna, label) adalah entry point
 * terpisah dari halaman show; dokumen milik tenant A tidak boleh
 * dapat dicetak oleh aktor tenant B yang berizin penuh di tenantnya
 * sendiri. Kontrol positif membuktikan rute yang sama bekerja untuk
 * pemilik dokumen, jadi penolakan di sini adalah isolasi, bukan
 * kerusakan rute.
 */
class CrossTenantPrintMatrixTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private Tenant $tenantA;

    private User $adminA;

    private Branch $branchA;

    private Branch $branchA2;

    private User $adminB;

    private FrameDetail $productA;

    private Sale $saleA;

    private SaleReturn $returnA;

    private PurchaseOrder $poA;

    private Receipt $receiptA;

    private PurchaseRequest $prA;

    private PurchasePayment $paymentA;

    private Journal $journalA;

    private StockTransfer $transferA;

    private Prescription $prescriptionA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$this->tenantA, $this->adminA, $this->branchA] = $this->tenantAdmin('a');
        $this->branchA2 = Branch::create(['name' => 'Cabang A2', 'tenant_id' => $this->tenantA->id]);
        $this->adminA->branches()->attach($this->branchA2);
        [, $this->adminB] = $this->tenantAdmin('b');

        $this->buildTenantADocuments();
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
            'email_verified_at' => now(),            'name' => 'Admin Cetak '.$tag, 'username' => 'admin-cetak-'.$tag,
            'email' => 'admin-cetak-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function buildTenantADocuments(): void
    {
        $supplier = Supplier::create(['name' => 'PT Lensa A', 'is_active' => true, 'tenant_id' => $this->tenantA->id]);
        $this->productA = FrameDetail::factory()->create(['product_id' => Product::factory()->forType('frame')->create(['name' => 'Frame Cetak A', 'brand' => 'Uji', 'tenant_id' => $this->tenantA->id])->id, 'sku' => 'SKU-CETAK-A', 'cost_price' => 100000, 'sale_price' => 250000, 'is_active' => true, 'tenant_id' => $this->tenantA->id]);
        $customer = Customer::create([
            'name' => 'Pelanggan Cetak A', 'phone' => '0812999', 'branch_id' => $this->branchA->id,
            'tenant_id' => $this->tenantA->id,
        ]);

        $orders = app(PurchaseOrderService::class);
        $this->poA = $orders->create($this->adminA, $supplier->id, $this->branchA->id, [
            ['detail_type' => 'frame', 'detail_id' => $this->productA->id, 'qty' => 5, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($this->adminA, $this->poA);
        $this->receiptA = app(ReceiptService::class)->store($this->adminA, $this->poA, [
            ['purchase_order_item_id' => $this->poA->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '100000'],
        ]);

        $this->saleA = app(SalesService::class)->createSale($this->adminA, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-02',
            'tax_mode' => 'exclusive', 'branch_id' => $this->branchA->id,
        ], [['detail_type' => 'frame', 'detail_id' => $this->productA->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);

        $this->returnA = SaleReturn::create([
            'sale_id' => $this->saleA->id, 'branch_id' => $this->branchA->id, 'number' => 'RT-CETAK-A',
            'reason' => 'Tukar model', 'refund_method' => 'store_credit', 'total_amount' => '250000',
            'created_by' => $this->adminA->id,
        ]);
        $this->returnA->items()->create([
            'sale_item_id' => $this->saleA->items->first()->id, 'qty' => 1, 'condition' => 'resale', 'amount' => '250000',
        ]);

        $this->prA = PurchaseRequest::create([
            'number' => 'PR-CETAK-A', 'branch_id' => $this->branchA->id,
            'requester_id' => $this->adminA->id, 'status' => 'draft',
        ]);
        $this->prA->items()->create([
            'detail_type' => 'frame', 'detail_id' => $this->productA->id, 'qty' => 3, 'estimated_price' => '100000', 'supplier_id' => $supplier->id,
        ]);

        $this->paymentA = PurchasePayment::create([
            'number' => 'PAY-CETAK-A', 'supplier_id' => $supplier->id, 'method' => 'transfer',
            'amount' => '500000', 'payment_date' => '2026-10-03', 'paid_by' => $this->adminA->id,
        ]);
        PurchasePaymentAllocation::create([
            'purchase_payment_id' => $this->paymentA->id, 'receipt_id' => $this->receiptA->id, 'amount' => '500000',
        ]);

        $this->journalA = app(PostingService::class)->post('manual', null, '2026-10-05', [
            ['account' => '1-1100', 'branch_id' => $this->branchA->id, 'debit' => '1000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $this->branchA->id, 'debit' => '0', 'credit' => '1000'],
        ], 'Jurnal cetak A', $this->adminA);

        $this->transferA = StockTransfer::create([
            'number' => 'TRF-CETAK-A', 'from_branch_id' => $this->branchA->id, 'to_branch_id' => $this->branchA2->id,
            'status' => 'sent', 'sent_by' => $this->adminA->id,
        ]);

        $this->prescriptionA = Prescription::factory()->create([
            'customer_id' => $customer->id, 'branch_id' => $this->branchA->id, 'tenant_id' => $this->tenantA->id,
        ]);
    }

    private function assertDenied(TestResponse $response, string $marker): void
    {
        $this->assertContains(
            $response->status(),
            [403, 404],
            'Rute cetak lintas tenant harus menolak (403/404), dapat '.$response->status(),
        );
        $response->assertDontSee($marker, false);
    }

    public function test_sales_print_family_denied_for_other_tenant(): void
    {
        $b = $this->adminB;

        $this->assertDenied($this->actingAs($b)->get(route('sales.print-receipt', $this->saleA)), $this->saleA->number);
        $this->assertDenied($this->actingAs($b)->get(route('sales.print-order', $this->saleA)), $this->saleA->number);
        $this->assertDenied($this->actingAs($b)->get(route('sales.print-invoice', $this->saleA)), $this->saleA->number);
        $this->assertDenied($this->actingAs($b)->get(route('sales.print-prescription', $this->saleA)), $this->saleA->number);
        $this->assertDenied($this->actingAs($b)->get(route('sales.returns.print', $this->returnA)), 'RT-CETAK-A');
    }

    public function test_purchasing_print_family_denied_for_other_tenant(): void
    {
        $b = $this->adminB;

        $this->assertDenied($this->actingAs($b)->get(route('po.print', $this->poA)), $this->poA->number);
        $this->assertDenied($this->actingAs($b)->get(route('pr.print', $this->prA)), 'PR-CETAK-A');
        $this->assertDenied($this->actingAs($b)->get(route('receipts.print', $this->receiptA)), $this->receiptA->number);
    }

    public function test_payments_print_denied_for_other_tenant(): void
    {
        // Temuan D-02 (payments.print tanpa pagar) DITUTUP oleh modul
        // perbaikan payments: CollectivePaymentController kini
        // memeriksa kepemilikan pembayaran secara eksplisit sebelum
        // memuat relasi ke view. Cakupan lengkap (result, platform,
        // unassigned, regresi proses kolektif) ada di
        // PaymentAccessTenantIsolationTest.
        $this->assertDenied($this->actingAs($this->adminB)->get(route('payments.print', $this->paymentA)), 'PAY-CETAK-A');
    }

    public function test_inventory_accounting_clinical_print_denied_for_other_tenant(): void
    {
        $b = $this->adminB;

        $this->assertDenied($this->actingAs($b)->get(route('transfers.print', $this->transferA)), 'TRF-CETAK-A');
        $this->assertDenied($this->actingAs($b)->get(route('journals.print', $this->journalA)), $this->journalA->number);
        $this->assertDenied($this->actingAs($b)->get(route('prescriptions.print', $this->prescriptionA)), 'Pelanggan Cetak A');
    }

    public function test_user_prints_and_labels_do_not_leak_other_tenant(): void
    {
        $b = $this->adminB;

        $this->assertDenied($this->actingAs($b)->get(route('users.print-profile', $this->adminA)), 'Admin Cetak a');

        $this->actingAs($b)->get(route('users.print'))
            ->assertOk()
            ->assertSee('Admin Cetak b')
            ->assertDontSee('Admin Cetak a');

        $labels = $this->actingAs($b)->post(route('labels.print'), ['product_ids' => [$this->productA->id]]);
        $labels->assertDontSee('SKU-CETAK-A', false);
        $labels->assertDontSee('Frame Cetak A', false);
    }

    public function test_owner_can_print_own_documents_control(): void
    {
        $a = $this->adminA;

        $this->actingAs($a)->get(route('sales.print-invoice', $this->saleA))->assertOk()->assertSee($this->saleA->number);
        $this->actingAs($a)->get(route('po.print', $this->poA))->assertOk()->assertSee($this->poA->number);
        $this->actingAs($a)->get(route('journals.print', $this->journalA))->assertOk()->assertSee($this->journalA->number);
        $this->actingAs($a)->get(route('transfers.print', $this->transferA))->assertOk()->assertSee('TRF-CETAK-A');
    }
}
