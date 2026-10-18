<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3.1: DP + split payment multi-metode + mode PPN per transaksi.
 * Pembayaran awal terpecah tercatat per metode; jurnal penyelesaian
 * mendebit akun tender per metode dan sisanya ke Piutang Pelanggan.
 */
class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '400000']);
        // Frame tipe ketat stok: sediakan stok agar penjualan sah.
        app(InventoryService::class)->recordMovement(
            $this->owner, $this->frame, $branch, 'initial_stock', 10, 'test_seed', 1,
        );
    }

    public function test_split_dp_two_methods_records_each_and_receivable_for_the_rest(): void
    {
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '100000'],
            ['method' => 'qris', 'amount' => '150000', 'reference' => 'QR-1'],
        ]);

        $payments = $sale->payments()->whereNull('reversal_of_id')->orderBy('id')->get();
        $this->assertCount(2, $payments);
        $this->assertSame('cash', $payments[0]->method);
        $this->assertSame('qris', $payments[1]->method);
        $this->assertSame('250000.00', Money::fromDecimal((string) $sale->paid_amount)->toDecimalString());
        $this->assertSame('partial', $sale->paymentStatus());
        $this->assertSame('150000.00', $sale->remaining()->toDecimalString());

        // Jurnal penyelesaian: Kas 100rb, Bank 150rb (qris), Piutang 150rb.
        $debits = JournalLine::whereHas('journal', fn ($q) => $q->where('source_type', 'sale')->where('source_id', $sale->id))
            ->where('debit', '>', 0)
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->pluck('journal_lines.debit', 'accounts.code');
        $this->assertSame('100000.00', (string) $debits['1-1100']);
        $this->assertSame('150000.00', (string) $debits['1-1200']);
        $this->assertSame('150000.00', (string) $debits['1-1300']);
    }

    public function test_split_total_exceeding_total_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '300000'],
            ['method' => 'card', 'amount' => '200000'],
        ]);
    }

    public function test_exclusive_tax_per_transaction_with_dp(): void
    {
        // Harga 400rb eksklusif PPN 11% -> total 444rb; DP 200rb tunai.
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'exclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '200000'],
        ]);

        $this->assertSame('44000.00', (string) $sale->tax_amount);
        $this->assertSame('444000.00', (string) $sale->total_amount);
        $this->assertSame('244000.00', $sale->remaining()->toDecimalString());
        $this->assertSame('partial', $sale->paymentStatus());
    }

    public function test_http_store_accepts_split_payment_rows(): void
    {
        $response = $this->actingAs($this->owner)->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-18',
            'tax_mode' => 'inclusive',
            'items' => [['product_id' => $this->frame->id, 'qty' => 1]],
            'payments' => [
                ['method' => 'cash', 'amount' => '50000', 'reference' => ''],
                ['method' => 'transfer', 'amount' => '75000', 'reference' => 'TRX-9'],
            ],
        ]);
        $response->assertRedirect();
        $sale = Sale::latest('id')->firstOrFail();
        $this->assertSame(2, $sale->payments()->count());
        $this->assertSame('125000.00', (string) $sale->paid_amount);
    }
}
