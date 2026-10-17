<?php

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseRequest;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.6 Purchasing — rantai PR → PO → penerimaan bertahap (D.7 + E.3):
 * mesin status PR, konversi & penggabungan se-supplier/se-cabang,
 * penerimaan menambah stok cabang tujuan dan memelihara status PO.
 */
class PurchasingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Supplier $supplier;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->supplier = Supplier::create(['name' => 'PT Lensa Uji', 'is_active' => true]);
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '400000', 'sale_price' => '500000']);
    }

    private function approvedPr(int $qty = 5, ?int $supplierId = null): PurchaseRequest
    {
        $service = app(PurchaseRequestService::class);
        $pr = $service->create($this->owner, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'estimated_price' => '390000', 'supplier_id' => $supplierId],
        ], 'Stok menipis');
        $service->submit($this->owner, $pr);
        $service->approve($this->owner, $pr);

        return $pr;
    }

    public function test_pr_lifecycle_and_conversion(): void
    {
        $service = app(PurchaseRequestService::class);
        $pr = $this->approvedPr(5, $this->supplier->id);
        $this->assertMatchesRegularExpression('/^PR-\d{6}-0001$/', $pr->number);

        $po = $service->convertToPo($this->owner, [$pr], $this->supplier->id);
        $this->assertMatchesRegularExpression('/^PO-\d{6}-0001$/', $po->number);
        $this->assertSame('converted', $pr->refresh()->status);
        $this->assertSame($po->id, $pr->items->first()->purchase_order_id);
        // Harga PO dari estimasi PR.
        $this->assertSame('390000.00', (string) $po->items->first()->unit_cost);
        $this->assertSame('1950000.00', (string) $po->total_amount);

        // PR terkonversi tidak dapat dikonversi ulang.
        $this->expectException(ValidationException::class);
        $service->convertToPo($this->owner, [$pr->refresh()], $this->supplier->id);
    }

    public function test_merge_multiple_pr_same_supplier_same_branch_only(): void
    {
        $service = app(PurchaseRequestService::class);
        $prA = $this->approvedPr(3, $this->supplier->id);
        $prB = $this->approvedPr(2, $this->supplier->id);

        $po = $service->convertToPo($this->owner, [$prA, $prB], $this->supplier->id);
        $this->assertSame(2, $po->items()->count());
        $this->assertSame('converted', $prA->refresh()->status);
        $this->assertSame('converted', $prB->refresh()->status);

        // Supplier berbeda pada usulan item → penggabungan ditolak.
        $other = Supplier::create(['name' => 'PT Lain', 'is_active' => true]);
        $prC = $this->approvedPr(1, $other->id);
        try {
            $service->convertToPo($this->owner, [$prC], $this->supplier->id);
            $this->fail('Konversi lintas supplier harus ditolak');
        } catch (ValidationException) {
            $this->assertSame('approved', $prC->refresh()->status);
        }
    }

    public function test_pr_illegal_transitions_rejected(): void
    {
        $service = app(PurchaseRequestService::class);
        $pr = $service->create($this->owner, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);
        // Approve dari draft (belum submit) ditolak.
        $this->expectException(ValidationException::class);
        $service->approve($this->owner, $pr);
    }

    public function test_receipts_partial_then_complete_and_stock_in(): void
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 5, 'unit_cost' => '400000'],
        ]);
        $orders->markSent($this->owner, $po);
        $item = $po->items->first();
        $receipts = app(ReceiptService::class);

        $r1 = $receipts->store($this->owner, $po, [
            ['purchase_order_item_id' => $item->id, 'qty' => 2],
        ], '2026-10-17', 'SJ-001');
        $this->assertMatchesRegularExpression('/^RCV-\d{6}-0001$/', $r1->number);
        $this->assertSame('partial', $po->refresh()->status);
        $this->assertSame(2, $item->refresh()->qty_received);
        $this->assertSame(2, app(InventoryService::class)->currentStock($this->frame->id, $this->branch->id));

        // Harga aktual berbeda dicatat apa adanya.
        $r2 = $receipts->store($this->owner, $po->refresh(), [
            ['purchase_order_item_id' => $item->id, 'qty' => 3, 'unit_cost_actual' => '410000'],
        ]);
        $this->assertSame('complete', $po->refresh()->status);
        $this->assertSame('1230000.00', $r2->totalAmount()->toDecimalString());
        $this->assertSame(5, app(InventoryService::class)->currentStock($this->frame->id, $this->branch->id));
    }

    public function test_receipt_on_draft_and_over_receive_rules(): void
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 2, 'unit_cost' => '400000'],
        ]);
        $item = $po->items->first();
        $receipts = app(ReceiptService::class);

        // PO draft belum dikirim → penerimaan ditolak.
        try {
            $receipts->store($this->owner, $po, [['purchase_order_item_id' => $item->id, 'qty' => 1]]);
            $this->fail('Penerimaan PO draft harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, $po->receipts()->count());
        }

        $orders->markSent($this->owner, $po);

        // Penerimaan lebih tanpa alasan → ditolak walau owner memegang pr.approve.
        try {
            $receipts->store($this->owner, $po->refresh(), [['purchase_order_item_id' => $item->id, 'qty' => 3]]);
            $this->fail('Over-receive tanpa alasan harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, $po->receipts()->count());
        }

        // Dengan alasan tertulis + izin approve → diterima dan teraudit.
        $receipt = $receipts->store($this->owner, $po->refresh(), [
            ['purchase_order_item_id' => $item->id, 'qty' => 3],
        ], null, null, 'Bonus 1 unit dari supplier');
        $this->assertSame(3, $item->refresh()->qty_received);
        $this->assertSame('complete', $po->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'receipt.create', 'subject_id' => $receipt->id]);

        // Pengguna tanpa pr.approve tetap ditolak walau memberi alasan.
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $gudang->branches()->sync([$this->branch->id]);
        $po2 = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_cost' => '400000'],
        ]);
        $orders->markSent($this->owner, $po2);
        $this->expectException(ValidationException::class);
        $receipts->store($gudang, $po2, [
            ['purchase_order_item_id' => $po2->items->first()->id, 'qty' => 2],
        ], null, null, 'Alasan gudang');
    }

    public function test_po_void_rules(): void
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_cost' => '400000'],
        ]);
        $orders->void($this->owner, $po, 'Salah supplier');
        $this->assertSame('void', $po->refresh()->status);

        // Sesudah ada penerimaan, void ditolak (koreksi lewat retur).
        $po2 = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_cost' => '400000'],
        ]);
        $orders->markSent($this->owner, $po2);
        app(ReceiptService::class)->store($this->owner, $po2, [
            ['purchase_order_item_id' => $po2->items->first()->id, 'qty' => 1],
        ]);
        $this->expectException(ValidationException::class);
        $orders->void($this->owner, $po2->refresh(), 'Terlambat');
    }

    public function test_http_pr_po_flow(): void
    {
        $this->actingAs($this->owner)->post('/pembelian/pr', [
            'cabang_tujuan' => $this->branch->id,
            'notes' => 'Uji HTTP',
            'items' => [['product_id' => $this->frame->id, 'qty' => 4, 'estimated_price' => '395000']],
        ])->assertRedirect();
        $pr = PurchaseRequest::firstOrFail();
        $this->actingAs($this->owner)->get('/pembelian/pr')->assertOk()->assertSee($pr->number);
        $this->actingAs($this->owner)->post('/pembelian/pr/'.$pr->id.'/submit')->assertRedirect();
        $this->actingAs($this->owner)->post('/pembelian/pr/'.$pr->id.'/approve')->assertRedirect();
        $this->actingAs($this->owner)->post('/pembelian/pr/'.$pr->id.'/konversi', [
            'supplier_id' => $this->supplier->id,
        ])->assertRedirect();
        $po = PurchaseOrder::firstOrFail();
        $this->actingAs($this->owner)->get('/pembelian/po/'.$po->id)->assertOk()->assertSee($po->number);
        $this->actingAs($this->owner)->get('/cari?q='.$po->number)->assertOk()->assertSee('/pembelian/po/'.$po->id);

        // Platform ditolak.
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get('/pembelian/po')->assertForbidden();
    }
}
