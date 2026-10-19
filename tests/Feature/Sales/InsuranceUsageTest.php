<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\InsuranceUsage;
use App\Modules\Sales\Services\InsuranceClaimService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pemakaian asuransi: klaim menjadi kaki pembayaran 'insurance'
 * (Dr Piutang Asuransi 1-1700), histori usage tercatat, dan
 * penerimaan klaim melunasi piutang asuransi secara bertahap.
 */
class InsuranceUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Product $frame;

    private Insurance $insurance;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        Setting::set('tax.rate_default', '0');
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '200000']);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 10, 'test', 1);
        $this->insurance = Insurance::create(['name' => 'Sehat Bersama', 'is_active' => true]);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'insurance_id' => $this->insurance->id]);
    }

    private function balanceOf(string $code): string
    {
        $account = Account::where('code', $code)->firstOrFail();

        return (string) JournalLine::where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')->value('balance');
    }

    public function test_sale_with_claim_posts_insurance_leg_and_usage(): void
    {
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
            'insurance_id' => $this->insurance->id, 'insurance_claim_amount' => '80000',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '200000']],
            [['method' => 'cash', 'amount' => '50000']]);

        // Terbayar = tunai 50rb + klaim 80rb; sisa piutang pelanggan 70rb.
        $this->assertSame('130000.00', (string) $sale->paid_amount);
        $this->assertSame('80000.00', $this->balanceOf('1-1700'));
        $usage = InsuranceUsage::firstOrFail();
        $this->assertSame('80000.00', (string) $usage->insurance_claim_amount);
        $this->assertSame('80000.00', $usage->remainingAmount());
        $this->assertSame('70000.00', app(SalesService::class)->outstandingReceivable($this->customer->id)->toDecimalString());

        // Penerimaan klaim bertahap: 30rb lalu 50rb → lunas; lebih ditolak.
        $service = app(InsuranceClaimService::class);
        $service->receive($this->owner, $usage, '30000', 'transfer');
        $this->assertSame('50000.00', $usage->fresh()->remainingAmount());
        $this->assertSame('50000.00', $this->balanceOf('1-1700'));
        try {
            $service->receive($this->owner, $usage->fresh(), '50001', 'transfer');
            $this->fail('Penerimaan melebihi sisa klaim seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
        $service->receive($this->owner, $usage->fresh(), '50000', 'transfer');
        $this->assertSame('0.00', $usage->fresh()->remainingAmount());
        $this->assertSame('0.00', $this->balanceOf('1-1700'));
    }

    public function test_claim_requires_active_insurance_and_within_total(): void
    {
        $service = app(SalesService::class);
        $items = [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '200000']];
        $base = [
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
        ];
        try {
            $service->createSale($this->owner, $base + ['insurance_claim_amount' => '50000'], $items, []);
            $this->fail('Klaim tanpa asuransi seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('insurance_id', $e->errors());
        }
        try {
            $service->createSale($this->owner, $base + [
                'insurance_id' => $this->insurance->id, 'insurance_claim_amount' => '200001',
            ], $items, []);
            $this->fail('Klaim melebihi total seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('insurance_claim_amount', $e->errors());
        }
        $this->assertSame(0, InsuranceUsage::count());
    }

    public function test_claim_pages_respect_permissions(): void
    {
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
            'insurance_id' => $this->insurance->id, 'insurance_claim_amount' => '80000',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '200000']], []);

        $usage = InsuranceUsage::firstOrFail();
        $this->actingAs($this->owner)->get(route('insurance-claims.index'))
            ->assertOk()->assertSee('Sehat Bersama');
        $this->actingAs($this->owner)->get(route('insurance-claims.show', $usage))
            ->assertOk()->assertSee('Sehat Bersama');
        $this->actingAs($this->owner)->post(route('insurance-claims.receive', $usage), [
            'amount' => '80000', 'method' => 'transfer',
        ])->assertRedirect();
        $this->assertSame('0.00', $usage->fresh()->remainingAmount());
    }
}
