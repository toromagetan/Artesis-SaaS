<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Limit kredit formal: penjualan berpiutang yang menembus limit
 * pelanggan ditolak; pembayaran melonggarkan ruang; NULL = bebas.
 */
class CreditLimitTest extends TestCase
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

    private function sale(Customer $customer, string $price, string $pay): void
    {
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => $price]],
            $pay === '0' ? [] : [['method' => 'cash', 'amount' => $pay]]);
    }

    public function test_credit_limit_blocks_then_payment_frees_room(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'credit_limit' => '150000']);

        // Piutang 100rb dari limit 150rb: lolos.
        $this->sale($customer, '100000', '0');
        $this->assertSame('100000.00', app(SalesService::class)->outstandingReceivable($customer->id)->toDecimalString());

        // Tambah piutang 100rb lagi → total 200rb > 150rb: ditolak.
        try {
            $this->sale($customer, '100000', '0');
            $this->fail('Penjualan penembus limit seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('customer_id', $e->errors());
        }

        // Penjualan lunas tidak memakan limit: lolos.
        $this->sale($customer, '100000', '100000');

        // Bayar piutang pertama 60rb → beredar 40rb; piutang baru 100rb = 140rb ≤ 150rb: lolos.
        $first = Sale::where('customer_id', $customer->id)
            ->where('status', 'active')->orderBy('id')->firstOrFail();
        app(SalesService::class)->addPayment($this->owner, $first, 'cash', '60000');
        $this->sale($customer, '100000', '0');
        $this->assertSame('140000.00', app(SalesService::class)->outstandingReceivable($customer->id)->toDecimalString());
    }

    public function test_null_limit_means_unlimited(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'credit_limit' => null]);
        $this->sale($customer, '100000', '0');
        $this->sale($customer, '100000', '0');
        $this->assertSame('200000.00', app(SalesService::class)->outstandingReceivable($customer->id)->toDecimalString());
    }
}
