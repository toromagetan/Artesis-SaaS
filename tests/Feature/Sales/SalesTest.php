<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.3 Sales inti (Blueprint D.6): hitungan Money, snapshot, pembayaran
 * append-only, gerbang diskon besar, void pembalikan penuh, pagar cabang.
 */
class SalesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private Branch $branchA;

    private Customer $customer;

    private Product $frame;

    private Product $lensRx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->branchA->id]);

        $this->customer = Customer::factory()->create(['branch_id' => $this->branchA->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000', 'name' => 'Frame Uji']);
        $this->lensRx = Product::factory()->ofType('rx')->create(['sale_price' => '300000', 'name' => 'Lensa RX Uji']);

        // Sejak M.4 penjualan frame wajib punya stok (saldo tidak boleh negatif).
        app(InventoryService::class)->recordMovement(
            $this->owner, $this->frame, $this->branchA, 'initial_stock', 10, 'test_seed', 1,
        );
    }

    private function service(): SalesService
    {
        return app(SalesService::class);
    }

    public function test_create_sale_exclusive_tax_math_and_numbering(): void
    {
        $sale = $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-17',
            'tax_mode' => 'exclusive',
            'discount_amount' => '50000',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 2], // 1.000.000
        ], [
            ['method' => 'cash', 'amount' => '500000'],
        ]);

        // subtotal 1.000.000 − diskon 50.000 = 950.000; pajak 11% eksklusif = 104.500; total 1.054.500
        $this->assertMatchesRegularExpression('/^INV-\d{6}-0001$/', $sale->number);
        $this->assertSame('1000000.00', (string) $sale->subtotal_amount);
        $this->assertSame('104500.00', (string) $sale->tax_amount);
        $this->assertSame('1054500.00', (string) $sale->total_amount);
        $this->assertSame('500000.00', (string) $sale->paid_amount);
        $this->assertSame('partial', $sale->paymentStatus());
        $this->assertSame('554500.00', $sale->remaining()->toDecimalString());
    }

    public function test_create_sale_inclusive_tax_extracts_share(): void
    {
        $sale = $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id,
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '111000'],
        ]);

        // Inklusif 11%: pajak di dalam 111.000 = 111.000 × 11/111 = 11.000; total tetap 111.000
        $this->assertSame('11000.00', (string) $sale->tax_amount);
        $this->assertSame('111000.00', (string) $sale->total_amount);
    }

    public function test_prescription_snapshot_is_frozen_and_marks_used(): void
    {
        $rx = Prescription::factory()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id, 'od_sph' => -2.00,
        ]);

        $sale = $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id,
            'prescription_id' => $rx->id,
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);

        $this->assertNotNull($rx->refresh()->used_at);
        $this->assertSame('-2.00', $sale->prescription_snapshot['od']['sph']);

        // Ubah resep & master produk sesudahnya — snapshot transaksi tidak berubah.
        $rx->update(['od_sph' => -9.00]);
        $this->lensRx->update(['name' => 'Nama Baru', 'sale_price' => '999999']);
        $sale->refresh()->load('items');
        $this->assertSame('-2.00', $sale->prescription_snapshot['od']['sph']);
        $this->assertSame('Lensa RX Uji', $sale->items->first()->product_name_snapshot);
        $this->assertSame('300000.00', (string) $sale->items->first()->unit_price);
    }

    public function test_prescription_of_another_customer_is_rejected(): void
    {
        $other = Customer::factory()->create(['branch_id' => $this->branchA->id]);
        $rxOther = Prescription::factory()->create([
            'customer_id' => $other->id, 'branch_id' => $this->branchA->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id,
            'prescription_id' => $rxOther->id,
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);
    }

    public function test_rx_item_requires_prescription_ref(): void
    {
        $this->expectException(ValidationException::class);
        $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id,
        ], [
            ['product_id' => $this->lensRx->id, 'qty' => 1],
        ]);
    }

    public function test_initial_payment_above_total_is_rejected_atomically(): void
    {
        try {
            $this->service()->createSale($this->kasir, [
                'customer_id' => $this->customer->id,
            ], [
                ['product_id' => $this->frame->id, 'qty' => 1],
            ], [
                ['method' => 'cash', 'amount' => '600000'],
            ]);
            $this->fail('Seharusnya ValidationException');
        } catch (ValidationException) {
            $this->assertSame(0, Sale::count());
            $this->assertSame(0, SalePayment::count());
        }
    }

    public function test_add_payment_until_paid_and_overpay_rejected(): void
    {
        $sale = $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);

        $this->service()->addPayment($this->kasir, $sale, 'qris', '200000');
        $this->assertSame('partial', $sale->refresh()->paymentStatus());

        try {
            $this->service()->addPayment($this->kasir, $sale, 'cash', '300001');
            $this->fail('Kelebihan bayar harus ditolak');
        } catch (ValidationException) {
            $this->assertSame('200000.00', (string) $sale->refresh()->paid_amount);
        }

        $this->service()->addPayment($this->kasir, $sale, 'cash', '300000');
        $sale->refresh();
        $this->assertSame('paid', $sale->paymentStatus());
        $this->assertSame(2, $sale->payments()->count()); // append-only: baris lama utuh
    }

    public function test_high_discount_requires_permission(): void
    {
        // Diskon 30% > ambang 20%: kasir (tanpa sales.discount.high) ditolak.
        try {
            $this->service()->createSale($this->kasir, [
                'customer_id' => $this->customer->id,
                'discount_amount' => '150000',
            ], [
                ['product_id' => $this->frame->id, 'qty' => 1],
            ]);
            $this->fail('Diskon besar kasir harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('discount_amount', $e->errors());
        }

        // Owner (pemegang sales.discount.high) lolos.
        $sale = $this->service()->createSale($this->owner, [
            'customer_id' => $this->customer->id,
            'discount_amount' => '150000',
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);
        $this->assertSame('350000.00', (string) $sale->total_amount);
    }

    public function test_void_reverses_every_payment_and_locks_sale(): void
    {
        $sale = $this->service()->createSale($this->kasir, [
            'customer_id' => $this->customer->id, 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '200000'],
        ]);
        $this->service()->addPayment($this->kasir, $sale, 'transfer', '300000');

        $voided = $this->service()->voidSale($this->owner, $sale, 'Salah input harga');
        $this->assertSame('void', $voided->status);
        $this->assertSame('0.00', (string) $voided->paid_amount);

        $payments = $voided->payments()->orderBy('id')->get();
        $this->assertSame(4, $payments->count()); // 2 asli + 2 pembalik
        $this->assertSame('-200000.00', (string) $payments[2]->amount);
        $this->assertSame($payments[0]->id, $payments[2]->reversal_of_id);
        $this->assertSame('-300000.00', (string) $payments[3]->amount);
        $net = Money::zero();
        foreach ($payments as $p) {
            $net = $net->add(Money::fromDecimal((string) $p->amount));
        }
        $this->assertSame('0.00', $net->toDecimalString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'sales.void', 'subject_id' => $sale->id]);

        // Void kedua & pembayaran pada sale void ditolak.
        $this->expectException(ValidationException::class);
        $this->service()->voidSale($this->owner, $sale, 'Lagi');
    }

    public function test_http_flow_and_branch_fencing(): void
    {
        $this->actingAs($this->kasir)->post('/penjualan', [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-17',
            'tax_mode' => 'inclusive',
            'items' => [['product_id' => $this->frame->id, 'qty' => 1]],
            'payment_method' => 'cash',
            'payment_amount' => '500000',
        ])->assertRedirect();

        $sale = Sale::firstOrFail();
        $this->actingAs($this->kasir)->get('/penjualan')->assertOk()->assertSee($sale->number);
        $this->actingAs($this->kasir)->get('/penjualan/'.$sale->id)->assertOk()->assertSee('Lunas');

        // Cabang lain tidak melihat transaksi ini.
        $branchB = Branch::create(['name' => 'Cabang B']);
        $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
        $kasirB = User::factory()->create();
        $kasirB->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $kasirB->branches()->sync([$branchB->id]);
        $this->actingAs($kasirB)->get('/penjualan/'.$sale->id)->assertNotFound();
        // Hasil pencarian tidak memuat tautan detail transaksi cabang lain
        // (string nomor sendiri selalu ter-echo sebagai kata kunci).
        $this->actingAs($kasirB)->get('/cari?q='.$sale->number)->assertOk()
            ->assertDontSee('/penjualan/'.$sale->id);
        $this->actingAs($this->owner)->get('/cari?q='.$sale->number)->assertOk()
            ->assertSee('/penjualan/'.$sale->id);
        $this->assertNotNull($customerB);

        // Kasir tidak berhak void (hanya Owner di matriks C.2).
        $this->actingAs($this->kasir)->post('/penjualan/'.$sale->id.'/void', ['reason' => 'x'])->assertForbidden();

        // Platform ditolak.
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get('/penjualan')->assertForbidden();
    }
}
