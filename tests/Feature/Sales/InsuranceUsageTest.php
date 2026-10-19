<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Insurance;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Models\InsuranceUsage;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Pemakaian asuransi (paritas Artesis): master memiliki
 * claim_value; klaim menjadi kaki pembayaran 'insurance'
 * (Dr Piutang Asuransi 1-1700 / Cr Piutang Pelanggan); histori
 * usage adalah snapshot yang tampil sebagai LAPORAN. Artesis
 * tidak mengelola penerimaan klaim — tidak ada modul receipt.
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
        $this->insurance = Insurance::create(['name' => 'Sehat Bersama', 'claim_value' => '100000', 'is_active' => true]);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'insurance_id' => $this->insurance->id]);
    }

    private function balanceOf(string $code): string
    {
        $account = Account::where('code', $code)->firstOrFail();

        return (string) JournalLine::where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')->value('balance');
    }

    private function saleWithClaim(): void
    {
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'), 'tax_mode' => 'inclusive',
            'insurance_id' => $this->insurance->id, 'insurance_claim_amount' => '80000',
        ], [['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '200000']],
            [['method' => 'cash', 'amount' => '50000']]);
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
        // Piutang Asuransi (1-1700) memegang klaim; tidak ada
        // mekanisme penerimaan yang menguranginya (paritas Artesis).
        $this->assertSame('80000.00', $this->balanceOf('1-1700'));
        $usage = InsuranceUsage::firstOrFail();
        $this->assertSame('80000.00', (string) $usage->insurance_claim_amount);
        $this->assertSame('200000.00', (string) $usage->transaction_amount);
        $this->assertSame('70000.00', app(SalesService::class)->outstandingReceivable($this->customer->id)->toDecimalString());
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

    public function test_usage_history_is_a_report_and_old_claim_pages_are_gone(): void
    {
        $this->saleWithClaim();

        $this->actingAs($this->owner)->get(route('reports.insurance-usages'))
            ->assertOk()->assertSee('Sehat Bersama');

        // Modul penerimaan klaim versi lama tidak ada lagi.
        $this->actingAs($this->owner)->get('/penjualan/klaim-asuransi')->assertNotFound();

        // Laporan terpagar izin reports.sales: Gudang tidak punya.
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()]);
        $gudang->branches()->sync([$this->branch->id]);
        $this->actingAs($gudang)->get(route('reports.insurance-usages'))->assertForbidden();
    }

    public function test_insurance_master_crud_with_claim_value(): void
    {
        $this->actingAs($this->owner)->get(route('insurances.index'))
            ->assertOk()->assertSee('Sehat Bersama');

        $this->actingAs($this->owner)->post(route('insurances.store'), [
            'name' => 'BPJS Kesehatan', 'claim_value' => '150000',
        ])->assertRedirect(route('insurances.index'));
        $created = Insurance::where('name', 'BPJS Kesehatan')->firstOrFail();
        $this->assertSame('150000.00', (string) $created->claim_value);

        // claim_value wajib (paritas Artesis: required).
        $this->actingAs($this->owner)->post(route('insurances.store'), [
            'name' => 'Tanpa Nilai',
        ])->assertSessionHasErrors('claim_value');

        // Nonaktif: hilang dari pilihan penjualan, data tidak terhapus.
        $this->actingAs($this->owner)->post(route('insurances.toggle-active', $created))->assertRedirect();
        $this->assertFalse($created->refresh()->is_active);

        // Tanpa izin insurances.manage → 403.
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()]);
        $gudang->branches()->sync([$this->branch->id]);
        $this->actingAs($gudang)->get(route('insurances.index'))->assertForbidden();
    }
}
