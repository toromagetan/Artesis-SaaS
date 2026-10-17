<?php

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Events\PurchasePaymentAllocationCreated;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Models\PurchasePaymentAllocation;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Support\Money;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.6 Pembayaran supplier (D.7 kontrak keras): tunggal terbatas sisa;
 * kolektif FIFO dengan validasi sebelum transaksi dan ATOMIK terhadap
 * injeksi kegagalan di tengah alokasi; retur mengurangi tagihan /
 * menjadi kredit supplier.
 */
class PurchasePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Supplier $supplier;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->supplier = Supplier::create(['name' => 'PT Bayar Uji', 'is_active' => true]);
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '100000']);
    }

    /** Buat receipt lunas-belum-bayar senilai $total pada tanggal terima tertentu. */
    private function makeReceipt(string $receivedDate, int $qty, string $unitCost): Receipt
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $this->supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_cost' => $unitCost],
        ]);
        $orders->markSent($this->owner, $po);

        return app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty],
        ], $receivedDate);
    }

    public function test_pay_single_bounded_by_remaining(): void
    {
        $receipt = $this->makeReceipt('2026-10-10', 2, '100000'); // 200.000
        $service = app(PurchasePaymentService::class);

        $service->paySingle($this->owner, $receipt, '120000', 'transfer', '2026-10-12');
        $this->assertSame('80000.00', $receipt->refresh()->remainingAmount()->toDecimalString());
        $this->assertSame('partial', $receipt->paymentStatus());

        try {
            $service->paySingle($this->owner, $receipt->refresh(), '80001', 'cash');
            $this->fail('Bayar melebihi sisa harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(1, PurchasePayment::count());
        }

        $service->paySingle($this->owner, $receipt->refresh(), '80000', 'cash');
        $this->assertSame('paid', $receipt->refresh()->paymentStatus());

        // Receipt lunas tidak dapat dibayar lagi.
        $this->expectException(ValidationException::class);
        $service->paySingle($this->owner, $receipt->refresh(), '1000', 'cash');
    }

    public function test_collective_fifo_three_receipts_like_production_case(): void
    {
        // Meniru kasus produksi Artesis: 3 receipt @ 2.000.000, bayar 6.000.000.
        $r1 = $this->makeReceipt('2026-10-01', 20, '100000');
        $r2 = $this->makeReceipt('2026-10-03', 20, '100000');
        $r3 = $this->makeReceipt('2026-10-05', 20, '100000');

        $payment = app(CollectivePaymentService::class)->process(
            $this->owner, $this->supplier, '6000000', 'transfer', '2026-10-15',
        );

        $this->assertMatchesRegularExpression('/^PAY-\d{6}-\d{4}$/', $payment->number);
        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame(3, $allocations->count());
        $this->assertSame(
            [$r1->id, $r2->id, $r3->id],
            $allocations->pluck('receipt_id')->all(),
        ); // FIFO: tertua dulu
        $sum = Money::zero();
        foreach ($allocations as $allocation) {
            $sum = $sum->add(Money::fromDecimal((string) $allocation->amount));
        }
        $this->assertSame('6000000.00', $sum->toDecimalString()); // Σ alokasi == amount (G.1.3)
        foreach ([$r1, $r2, $r3] as $r) {
            $this->assertSame('paid', $r->refresh()->paymentStatus());
        }
    }

    public function test_collective_partial_allocation_spreads_fifo(): void
    {
        $r1 = $this->makeReceipt('2026-10-01', 10, '100000'); // 1.000.000
        $r2 = $this->makeReceipt('2026-10-02', 10, '100000'); // 1.000.000

        $payment = app(CollectivePaymentService::class)->process(
            $this->owner, $this->supplier, '1500000', 'cash',
        );
        $allocations = $payment->allocations()->orderBy('id')->get();
        $this->assertSame('1000000.00', (string) $allocations[0]->amount);
        $this->assertSame('500000.00', (string) $allocations[1]->amount);
        $this->assertSame('paid', $r1->refresh()->paymentStatus());
        $this->assertSame('500000.00', $r2->refresh()->remainingAmount()->toDecimalString());
    }

    public function test_collective_validation_before_transaction(): void
    {
        $this->makeReceipt('2026-10-01', 5, '100000'); // sisa 500.000
        $service = app(CollectivePaymentService::class);

        // Melebihi total sisa → ditolak; nol dokumen pembayaran tercipta.
        try {
            $service->process($this->owner, $this->supplier, '500001', 'transfer');
            $this->fail('Kolektif melebihi sisa harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, PurchasePayment::count());
            $this->assertSame(0, PurchasePaymentAllocation::count());
        }

        // Supplier nonaktif ditolak.
        $this->supplier->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $service->process($this->owner, $this->supplier, '100000', 'transfer');
    }

    public function test_collective_failure_injection_rolls_back_everything(): void
    {
        $r1 = $this->makeReceipt('2026-10-01', 10, '100000');
        $r2 = $this->makeReceipt('2026-10-02', 10, '100000');
        $r3 = $this->makeReceipt('2026-10-03', 10, '100000');

        // Ledakkan proses tepat pada alokasi KEDUA di dalam transaksi.
        $count = 0;
        Event::listen(PurchasePaymentAllocationCreated::class, function () use (&$count) {
            $count++;
            if ($count === 2) {
                throw new \RuntimeException('Injeksi kegagalan di tengah alokasi');
            }
        });

        try {
            app(CollectivePaymentService::class)->process(
                $this->owner, $this->supplier, '3000000', 'transfer',
            );
            $this->fail('Injeksi harus menggagalkan proses');
        } catch (\RuntimeException) {
            // TIDAK ADA keadaan tercatat-sebagian:
            $this->assertSame(0, PurchasePayment::count());
            $this->assertSame(0, PurchasePaymentAllocation::count());
            foreach ([$r1, $r2, $r3] as $r) {
                $this->assertSame('1000000.00', $r->refresh()->remainingAmount()->toDecimalString());
            }
        }

        // HTTP: kegagalan kolektif menjadi pesan ramah, bukan 500.
        $count = 0; // listener meledak lagi pada alokasi kedua
        $response = $this->actingAs($this->owner)->post('/pembelian/bayar-kolektif', [
            'supplier_id' => $this->supplier->id,
            'amount' => '3000000',
            'method' => 'transfer',
        ]);
        $response->assertSessionHasErrors('amount');
        $this->assertSame(0, PurchasePayment::count());
    }

    public function test_purchase_return_reduces_payable_then_becomes_credit(): void
    {
        $receipt = $this->makeReceipt('2026-10-10', 4, '100000'); // 400.000, stok 4
        $item = $receipt->items->first();
        $service = app(PurchaseReturnService::class);
        $stock = app(InventoryService::class);
        $this->assertSame(4, $stock->currentStock($this->frame->id, $this->branch->id));

        // Retur saat belum lunas → reduces_payable + stok keluar.
        $retur = $service->store($this->owner, $receipt, [
            ['receipt_item_id' => $item->id, 'qty' => 1],
        ], 'Kemasan rusak');
        $this->assertSame('reduces_payable', $retur->credit_status);
        $this->assertSame('100000.00', (string) $retur->total_amount);
        $this->assertSame('300000.00', $receipt->refresh()->remainingAmount()->toDecimalString());
        $this->assertSame(3, $stock->currentStock($this->frame->id, $this->branch->id));

        // Qty retur melebihi sisa diterima → ditolak.
        try {
            $service->store($this->owner, $receipt->refresh(), [
                ['receipt_item_id' => $item->id, 'qty' => 4],
            ], 'Terlalu banyak');
            $this->fail('Retur melebihi qty harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(1, PurchaseReturn::count());
        }

        // Lunasi sisa 300.000, lalu retur lagi → supplier_credit.
        app(PurchasePaymentService::class)->paySingle($this->owner, $receipt->refresh(), '300000', 'cash');
        $this->assertSame('paid', $receipt->refresh()->paymentStatus());
        $retur2 = $service->store($this->owner, $receipt->refresh(), [
            ['receipt_item_id' => $item->id, 'qty' => 1],
        ], 'Retur susulan');
        $this->assertSame('supplier_credit', $retur2->credit_status);
        $this->assertSame(2, $stock->currentStock($this->frame->id, $this->branch->id));
    }
}
