<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\WarrantyCase;
use App\Modules\Sales\Services\SalesService;
use App\Modules\Sales\Services\WarrantyService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Modul Garansi (paritas alur after-sales Artesis): siklus penuh
 * kasus — buat (larangan kasus aktif ganda) → kirim barang lama →
 * terima + inspeksi di gudang (baik = kembali ke stok; rusak =
 * write-off) → pengganti dari stok (gerakan FIFO + jurnal beban
 * garansi) → terima cabang → bayar biaya (jurnal pendapatan) →
 * selesai. Jalur PO juga diuji kesiapan kirimnya.
 */
class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Branch $warehouse;

    private Product $product;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::set('tax.rate_default', '0');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->warehouse = Branch::factory()->create(['name' => 'Gudang Utama', 'is_main_warehouse' => true]);
        $supplier = Supplier::create(['name' => 'PT Garansi', 'is_active' => true]);
        $this->product = Product::factory()->ofType('frame')->create([
            'sku' => 'WR-1', 'name' => 'Frame Garansi', 'cost_price' => '40000', 'sale_price' => '100000',
        ]);
        $orders = app(PurchaseOrderService::class);
        foreach ([$this->branchA, $this->warehouse] as $branch) {
            $po = $orders->create($this->owner, $supplier->id, $branch->id, [
                ['product_id' => $this->product->id, 'qty' => 5, 'unit_cost' => '40000'],
            ]);
            $orders->markSent($this->owner, $po);
            app(ReceiptService::class)->store($this->owner, $po, [
                ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '40000'],
            ]);
        }
        $customer = Customer::factory()->create(['branch_id' => $this->branchA->id]);
        $this->sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'exclusive', 'branch_id' => $this->branchA->id,
        ], [['product_id' => $this->product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
    }

    private function openCase(): WarrantyCase
    {
        return app(WarrantyService::class)->createCase($this->owner, $this->sale,
            $this->sale->items->first(), 1, 'Lensa retak', null);
    }

    private function stockAt(int $branchId): int
    {
        return app(InventoryService::class)->balancesForBranch($branchId)[$this->product->id] ?? 0;
    }

    public function test_full_cycle_with_good_inspection_and_stock_replacement(): void
    {
        $service = app(WarrantyService::class);
        $case = $this->openCase();
        $this->assertSame('open', $case->status);
        $this->assertSame('100000.00', (string) $case->replacement->original_value);

        // Kasus aktif kedua untuk item yang sama ditolak.
        try {
            $this->openCase();
            $this->fail('Kasus ganda seharusnya ditolak');
        } catch (ValidationException) {
            $this->assertSame(1, WarrantyCase::count());
        }

        $service->sendOldItem($this->owner, $case);
        $broken = $service->receiveOldItem($this->owner, $case->fresh());
        $this->assertSame('pending_inspection', $broken->status);
        $this->assertSame($this->warehouse->id, $broken->branch_id);

        // Inspeksi BAIK: barang lama kembali ke stok gudang (5 + 1 = 6).
        $service->inspectBroken($this->owner, $broken, 'good', 'Masih layak', null);
        $this->assertSame(6, $this->stockAt($this->warehouse->id));
        $this->assertSame('old_item_received', $case->fresh()->status);

        // Pengganti dari stok dengan biaya pelanggan 25.000.
        $service->prepareReplacement($this->owner, $case->fresh(), 'stock', $this->product, '25000');
        $this->assertTrue($service->replacementReady($case->fresh()));

        // Selesai sebelum pengganti diterima harus gagal; bayar melebihi biaya juga gagal.
        $service->shipReplacement($this->owner, $case->fresh());
        $this->assertSame(5, $this->stockAt($this->warehouse->id));
        $journal = Journal::where('source_type', 'warranty_shipment')->first();
        $this->assertNotNull($journal);
        $this->assertSame('40000.00', (string) $journal->lines()->whereHas('account', fn ($q) => $q->where('code', '5-1700'))->first()->debit);

        $service->receiveReplacement($this->owner, $case->fresh());
        $this->assertSame(5, $this->stockAt($this->branchA->id)); // 5 awal - 1 terjual + 1 pengganti

        try {
            $service->pay($this->owner, $case->fresh(), 'cash', '26000');
            $this->fail('Bayar melebihi biaya seharusnya ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, $case->fresh()->payments()->count());
        }
        $service->pay($this->owner, $case->fresh(), 'cash', '25000');
        $payJournal = Journal::where('source_type', 'warranty_payment')->first();
        $this->assertNotNull($payJournal);
        $this->assertSame('25000.00', (string) $payJournal->lines()->whereHas('account', fn ($q) => $q->where('code', '4-1300'))->first()->credit);

        $service->complete($this->owner, $case->fresh());
        $this->assertSame('completed', $case->fresh()->status);
        $this->assertNotNull($case->fresh()->completed_at);
    }

    public function test_broken_inspection_write_off_and_po_replacement_readiness(): void
    {
        $service = app(WarrantyService::class);
        $case = $this->openCase();
        $service->sendOldItem($this->owner, $case);
        $broken = $service->receiveOldItem($this->owner, $case->fresh());

        // Inspeksi RUSAK: stok jual gudang tidak bertambah; write-off menutup catatan.
        $service->inspectBroken($this->owner, $broken, 'broken', 'Pecah total', 'Tidak dapat diperbaiki');
        $this->assertSame(5, $this->stockAt($this->warehouse->id));
        $adjusted = $service->adjustBroken($this->owner, $broken->fresh(), 'Musnah', 'Barang dihancurkan');
        $this->assertSame('resolved', $adjusted->status);
        $this->assertSame('adjustment', $adjusted->resolution);
        $this->assertSame(0, (int) $adjusted->quantity);

        // Pengganti via PO: belum siap sebelum PO dibuat & diterima.
        $service->prepareReplacement($this->owner, $case->fresh(), 'purchase_order', $this->product, '0');
        $supplier = Supplier::create(['name' => 'PT PO Garansi', 'is_active' => true]);
        $service->createReplacementPo($this->owner, $case->fresh(), $supplier);
        $case = $case->fresh();
        $this->assertFalse($service->replacementReady($case));
        $po = $case->replacement->purchaseOrder;
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 1, 'unit_cost_actual' => '40000'],
        ]);
        $this->assertTrue($service->replacementReady($case->fresh()));

        $service->shipReplacement($this->owner, $case->fresh());
        $service->receiveReplacement($this->owner, $case->fresh());
        $service->complete($this->owner, $case->fresh());
        $this->assertSame('completed', $case->fresh()->status);
    }

    public function test_warranty_pages_fencing_and_http_flow(): void
    {
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $this->actingAs($gudang)->get(route('warranties.index'))->assertOk();
        $this->actingAs($gudang)->get(route('warranties.create'))->assertForbidden();

        $this->actingAs($this->owner)->get(route('warranties.create', ['invoice' => $this->sale->number]))
            ->assertOk()->assertSee($this->sale->number);
        $this->actingAs($this->owner)->post(route('warranties.store'), [
            'sale_id' => $this->sale->id, 'sale_item_id' => $this->sale->items->first()->id,
            'quantity' => 1, 'reason' => 'Engsel patah',
        ])->assertRedirect();
        $case = WarrantyCase::firstOrFail();
        $this->actingAs($this->owner)->get(route('warranties.show', $case))->assertOk()->assertSee($case->number);
        $this->actingAs($this->owner)->post(route('warranties.send-old', $case))->assertRedirect();
        $this->assertSame('waiting_warehouse', $case->fresh()->status);
    }
}
