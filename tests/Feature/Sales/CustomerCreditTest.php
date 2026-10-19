<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\CustomerCredit;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\CustomerCreditService;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Voucher kredit pelanggan (paritas Artesis CustomerCredit):
 * terbit dari retur bermetode kredit toko; diterapkan terkontrol
 * ke penjualan lain pelanggan yang sama. Plafon piutang
 * (credit_limit) TIDAK ada di Artesis dan sudah dicabut.
 */
class CustomerCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        Setting::set('tax.rate_default', '0');
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 50, 'test', 1);
    }

    private function balanceOf(string $code): string
    {
        $account = Account::where('code', $code)->firstOrFail();

        return (string) JournalLine::where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')->value('balance');
    }

    private function paidSale(Customer $customer, string $price): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => $price]],
            [['method' => 'cash', 'amount' => $price]]);
    }

    private function unpaidSale(Customer $customer, string $price): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => $price]], []);
    }

    private function issueCredit(Customer $customer, string $price = '100000'): CustomerCredit
    {
        $sale = $this->paidSale($customer, $price);
        $item = $sale->items->first();
        app(SaleReturnService::class)->create($this->owner, $sale, [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Tukar model', 'store_credit');

        return CustomerCredit::where('customer_id', $customer->id)->firstOrFail();
    }

    public function test_store_credit_return_issues_voucher(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $credit = $this->issueCredit($customer);

        $this->assertMatchesRegularExpression('/^CR-\d{6}-\d{4}$/', $credit->credit_number);
        $this->assertSame('100000.00', (string) $credit->amount);
        $this->assertSame('100000.00', (string) $credit->remaining_amount);
        $this->assertSame(CustomerCredit::STATUS_ACTIVE, $credit->status);
        $this->assertNotNull($credit->sale_return_id);
        // Jurnal retur: Kredit Toko (2-1500) bertambah di sisi kredit.
        $this->assertSame('-100000.00', $this->balanceOf('2-1500'));
    }

    public function test_apply_credit_pays_another_sale_and_exhausts(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $credit = $this->issueCredit($customer);
        $target = $this->unpaidSale($customer, '150000');

        $application = app(CustomerCreditService::class)->apply($this->owner, $credit, $target, '100000');

        $target->refresh();
        $this->assertSame('100000.00', (string) $target->paid_amount);
        $payment = $target->payments()->where('method', 'store_credit')->firstOrFail();
        $this->assertSame('credit-application:'.$application->id, $payment->reference);

        $credit->refresh();
        $this->assertSame('0.00', (string) $credit->remaining_amount);
        $this->assertSame(CustomerCredit::STATUS_EXHAUSTED, $credit->status);
        // Penerapan: Dr Kredit Toko mengimbangi Cr saat penerbitan → saldo nol;
        // piutang pelanggan (1-1300) berkurang sebesar kredit yang dipakai.
        $this->assertSame('0.00', $this->balanceOf('2-1500'));
    }

    public function test_apply_guards(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $other = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $credit = $this->issueCredit($customer);
        $service = app(CustomerCreditService::class);

        // Penjualan pelanggan lain ditolak.
        $foreignSale = $this->unpaidSale($other, '50000');
        try {
            $service->apply($this->owner, $credit, $foreignSale, '10000');
            $this->fail('Kredit pelanggan lain seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sale_id', $e->errors());
        }

        // Melebihi sisa saldo kredit ditolak.
        $ownSale = $this->unpaidSale($customer, '150000');
        try {
            $service->apply($this->owner, $credit, $ownSale, '100001');
            $this->fail('Nominal di atas sisa kredit seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Melebihi sisa tagihan penjualan ditolak.
        $smallSale = $this->unpaidSale($customer, '40000');
        try {
            $service->apply($this->owner, $credit, $smallSale, '50000');
            $this->fail('Nominal di atas sisa tagihan seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Penerapan pertama berhasil; penerapan kedua ke penjualan
        // yang sama ditolak (anti ganda ala Artesis).
        $service->apply($this->owner, $credit, $ownSale, '60000');
        try {
            $service->apply($this->owner, $credit->refresh(), $ownSale, '10000');
            $this->fail('Penerapan ganda ke penjualan yang sama seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sale_id', $e->errors());
        }

        // Sisa 40rb diterapkan ke penjualan lain → habis; kredit
        // habis tidak dapat dipakai lagi.
        $service->apply($this->owner, $credit->refresh(), $smallSale, '40000');
        $this->assertSame(CustomerCredit::STATUS_EXHAUSTED, $credit->refresh()->status);
        try {
            $service->apply($this->owner, $credit->refresh(), $this->unpaidSale($customer, '10000'), '1000');
            $this->fail('Kredit habis seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('credit', $e->errors());
        }
    }

    public function test_no_credit_limit_block_remains(): void
    {
        // credit_limit dicabut: piutang besar tanpa plafon berjalan.
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->unpaidSale($customer, '100000');
        $this->unpaidSale($customer, '100000');
        $this->assertSame('200000.00', app(SalesService::class)->outstandingReceivable($customer->id)->toDecimalString());
    }

    public function test_customer_page_lists_credit_and_apply_flow_over_http(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $credit = $this->issueCredit($customer);
        $target = $this->unpaidSale($customer, '80000');

        $this->actingAs($this->owner)
            ->get(route('customers.show', $customer))
            ->assertOk()
            ->assertSee($credit->credit_number);

        $this->actingAs($this->owner)
            ->get(route('customer-credits.apply', $credit))
            ->assertOk()
            ->assertSee($target->number);

        $this->actingAs($this->owner)
            ->post(route('customer-credits.apply.store', $credit), [
                'sale_id' => $target->id, 'amount' => '80000',
            ])
            ->assertRedirect(route('sales.show', $target));

        $this->assertSame('80000.00', (string) $target->refresh()->paid_amount);
        // Kredit 100rb dipakai 80rb → sisa 20rb, status tetap aktif.
        $this->assertSame('20000.00', (string) $credit->refresh()->remaining_amount);
        $this->assertSame(CustomerCredit::STATUS_ACTIVE, $credit->status);
    }
}
