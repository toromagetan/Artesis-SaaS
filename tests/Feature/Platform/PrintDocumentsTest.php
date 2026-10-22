<?php

namespace Tests\Feature\Platform;

use App\Modules\Accounting\Services\PostingService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dokumen cetak yang hilang (audit 23 Okt 2026 B3+B4): struk termal
 * 58/80mm, cetak PR, nota retur, kuitansi pembayaran, GRN, voucher
 * jurnal — semuanya render 200 untuk aktor berizin dan tertutup
 * untuk yang tidak.
 */
class PrintDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Supplier $supplier;

    private Product $product;

    private Customer $customer;

    private PurchaseOrder $po;

    private Receipt $receipt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->seed(AccountingSeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->supplier = Supplier::create(['name' => 'PT Lensa', 'is_active' => true]);
        $this->product = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-1', 'name' => 'Ray RB-1 Hitam', 'cost_price' => '100000', 'sale_price' => '250000',
        ]);
        $this->customer = Customer::factory()->create(['name' => 'Budi', 'phone' => '0812000', 'branch_id' => $this->branch->id]);

        $orders = app(PurchaseOrderService::class);
        $this->po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->product->id, 'qty' => 5, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($this->owner, $this->po);
        $this->receipt = app(ReceiptService::class)->store($this->owner, $this->po, [
            ['purchase_order_item_id' => $this->po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '100000'],
        ]);
    }

    private function makeSale(): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-02',
            'tax_mode' => 'exclusive', 'branch_id' => $this->branch->id,
        ], [['product_id' => $this->product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '250000']]);
    }

    public function test_all_print_documents_render(): void
    {
        $sale = $this->makeSale();
        $return = SaleReturn::create(['sale_id' => $sale->id, 'branch_id' => $this->branch->id, 'number' => 'RT-TEST-1', 'reason' => 'Tukar model', 'refund_method' => 'store_credit', 'total_amount' => '250000', 'created_by' => $this->owner->id]);
        $return->items()->create(['sale_item_id' => $sale->items->first()->id, 'qty' => 1, 'condition' => 'resale', 'amount' => '250000']);

        $pr = PurchaseRequest::create(['number' => 'PR-TEST-1', 'branch_id' => $this->branch->id, 'requester_id' => $this->owner->id, 'status' => 'draft']);
        $pr->items()->create(['product_id' => $this->product->id, 'qty' => 3, 'estimated_price' => '100000', 'supplier_id' => $this->supplier->id]);

        $payment = PurchasePayment::create(['number' => 'PAY-TEST-1', 'supplier_id' => $this->supplier->id, 'method' => 'transfer', 'amount' => '500000', 'payment_date' => '2026-10-03', 'paid_by' => $this->owner->id]);
        PurchasePaymentAllocation::create(['purchase_payment_id' => $payment->id, 'receipt_id' => $this->receipt->id, 'amount' => '500000']);

        $journal = app(PostingService::class)->post('manual', null, '2026-10-05', [
            ['account' => '1-1100', 'branch_id' => $this->branch->id, 'debit' => '1000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $this->branch->id, 'debit' => '0', 'credit' => '1000'],
        ], 'Jurnal uji cetak', $this->owner);

        $this->actingAs($this->owner)->get(route('sales.print-receipt', $sale))->assertOk()->assertSee($sale->number)->assertSee('80mm');
        $this->actingAs($this->owner)->get(route('sales.print-receipt', [$sale, 'lebar' => 58]))->assertOk()->assertSee('58mm');
        $this->actingAs($this->owner)->get(route('sales.returns.print', $return))->assertOk()->assertSee('RT-TEST-1');
        $this->actingAs($this->owner)->get(route('pr.print', $pr))->assertOk()->assertSee('PR-TEST-1');
        $this->actingAs($this->owner)->get(route('receipts.print', $this->receipt))->assertOk()->assertSee($this->receipt->number);
        $this->actingAs($this->owner)->get(route('payments.print', $payment))->assertOk()->assertSee('PAY-TEST-1')->assertSee($this->receipt->number);
        $this->actingAs($this->owner)->get(route('journals.print', $journal))->assertOk()->assertSee($journal->number);
    }

    public function test_print_documents_require_permissions(): void
    {
        $sale = $this->makeSale();
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $gudang->branches()->sync([$this->branch->id]);

        // Gudang tidak memegang sales.view.
        $this->actingAs($gudang)->get(route('sales.print-receipt', $sale))->assertForbidden();
    }
}
