<?php

namespace Tests\Feature\Lab;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\ProcurementStatusService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * PARITAS ARTESIS (koreksi owner 2026-10-18): PR otomatis dari
 * penjualan untuk kekurangan lensa; edging hanya sesudah lensa
 * diterima; status proses turunan dari rantai PR -> PO -> terima.
 */
class AutoPurchaseRequestParityTest extends TestCase
{
    use RefreshDatabase;

    private $owner;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    private function saleWith(array $items)
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], $items);
    }

    public function test_rx_sale_always_creates_submitted_auto_pr_linked_to_sale(): void
    {
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);
        $sale = $this->saleWith([['product_id' => $rx->id, 'qty' => 2, 'prescription_ref' => 'pair']]);

        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $this->assertSame('submitted', $pr->status);
        $this->assertSame((int) $this->branch->id, (int) $pr->branch_id);
        $this->assertSame(2, (int) $pr->items()->firstOrFail()->qty);
        $this->assertSame('pr', app(ProcurementStatusService::class)->stageFor($sale));
    }

    public function test_stocked_lens_creates_no_pr_and_partial_shortage_creates_pr_for_the_rest(): void
    {
        $lens = Product::factory()->ofType('lens')->create(['sale_price' => '200000']);
        app(InventoryService::class)->recordMovement($this->owner, $lens, $this->branch, 'initial_stock', 3, 'test_seed', 1);

        $full = $this->saleWith([['product_id' => $lens->id, 'qty' => 2]]);
        $this->assertSame(0, PurchaseRequest::where('sale_id', $full->id)->count());
        $this->assertSame('stock', app(ProcurementStatusService::class)->stageFor($full));

        // Sisa stok 1, jual 3 -> PR hanya untuk kekurangan 2.
        $partial = $this->saleWith([['product_id' => $lens->id, 'qty' => 3]]);
        $pr = PurchaseRequest::where('sale_id', $partial->id)->firstOrFail();
        $this->assertSame(2, (int) $pr->items()->firstOrFail()->qty);
    }

    public function test_frame_only_sale_creates_no_pr_and_no_stage(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 2, 'test_seed', 1);
        $sale = $this->saleWith([['product_id' => $frame->id, 'qty' => 1]]);

        $this->assertSame(0, PurchaseRequest::where('sale_id', $sale->id)->count());
        $this->assertNull(app(ProcurementStatusService::class)->stageFor($sale));
    }

    public function test_void_cancels_submitted_auto_pr_but_keeps_approved_one(): void
    {
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);
        $sales = app(SalesService::class);

        $saleA = $this->saleWith([['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair']]);
        $sales->voidSale($this->owner, $saleA, 'salah input');
        $this->assertSame('cancelled', PurchaseRequest::where('sale_id', $saleA->id)->firstOrFail()->status);

        $saleB = $this->saleWith([['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair']]);
        $prB = PurchaseRequest::where('sale_id', $saleB->id)->firstOrFail();
        app(PurchaseRequestService::class)->approve($this->owner, $prB);
        $sales->voidSale($this->owner, $saleB, 'salah input');
        $this->assertSame('approved', $prB->refresh()->status);
    }

    public function test_edging_blocked_until_lenses_received_then_stage_received(): void
    {
        $rx = Product::factory()->ofType('rx')->create(['sale_price' => '450000']);
        $supplier = Supplier::create(['name' => 'PT Lab Uji', 'is_active' => true]);
        $sale = $this->saleWith([['product_id' => $rx->id, 'qty' => 1, 'prescription_ref' => 'pair']]);
        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $lab = app(LensProcessService::class);

        try {
            $lab->transition($this->owner, $process, 'edging');
            $this->fail('Edging harus ditolak sebelum lensa diterima');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
        $this->assertSame('waiting', $process->refresh()->status);

        // Rantai Artesis: PR disetujui -> PO -> kirim -> terima penuh.
        $pr = PurchaseRequest::where('sale_id', $sale->id)->firstOrFail();
        $prs = app(PurchaseRequestService::class);
        $prs->approve($this->owner, $pr);
        $po = $prs->convertToPo($this->owner, [$pr->refresh()], $supplier->id);
        $this->assertSame('po', app(ProcurementStatusService::class)->stageFor($sale));
        app(PurchaseOrderService::class)->markSent($this->owner, $po);
        $this->assertSame('ordered', app(ProcurementStatusService::class)->stageFor($sale));
        $poItem = $po->items()->firstOrFail();
        app(ReceiptService::class)->store($this->owner, $po->refresh(), [
            ['purchase_order_item_id' => $poItem->id, 'qty' => 1, 'unit_cost_actual' => '300000'],
        ]);
        $this->assertSame('complete', $po->refresh()->status);
        $this->assertSame('received', app(ProcurementStatusService::class)->stageFor($sale));

        $lab->transition($this->owner, $process, 'edging');
        $this->assertSame('edging', $process->refresh()->status);
    }
}
