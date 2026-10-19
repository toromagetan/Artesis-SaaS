<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\IncentiveService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Insentif berkala: aturan persen untuk Kasir dihitung dari
 * penjualan cashier pada rentang; penyesuaian draft; persetujuan
 * Owner/Admin memosting Dr Beban Insentif / Cr Utang Insentif;
 * pembayaran melunasi utang insentif.
 */
class IncentiveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private User $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        Setting::set('tax.rate_default', '0');

        $this->kasir = User::factory()->create(['username' => 'kasir.insentif']);
        $this->kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->branch->id]);

        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 20, 'test', 1);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        // Penjualan oleh kasir: 2 transaksi × 100rb di September 2026.
        foreach (['2026-09-05', '2026-09-20'] as $date) {
            app(SalesService::class)->createSale($this->kasir, [
                'customer_id' => $customer->id, 'branch_id' => $this->branch->id,
                'sale_date' => $date, 'tax_mode' => 'inclusive',
            ], [['product_id' => $frame->id, 'qty' => 1, 'unit_price' => '100000']],
                [['method' => 'cash', 'amount' => '100000']]);
        }
    }

    private function balanceOf(string $code): string
    {
        $account = Account::where('code', $code)->firstOrFail();

        return (string) JournalLine::where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')->value('balance');
    }

    public function test_generate_adjust_approve_pay_flow(): void
    {
        $service = app(IncentiveService::class);
        $service->createRule($this->owner, [
            'name' => 'Insentif Kasir 5%', 'recipient_role' => 'Kasir', 'product_id' => null,
            'calculation_type' => 'percent', 'value' => '5',
            'effective_from' => '2026-01-01', 'effective_to' => null, 'is_active' => true,
        ]);

        [$created, $skipped] = $service->generate($this->owner, $this->branch->id, '2026-09-01', '2026-09-30');
        $this->assertSame(1, $created);
        $this->assertSame(0, $skipped);
        $period = IncentivePeriod::firstOrFail();
        $this->assertSame('200000.00', (string) $period->transaction_total);
        $this->assertSame('10000.00', (string) $period->incentive_total); // 5% × 200rb

        // Generate ulang rentang sama → dilewati.
        [$created2, $skipped2] = $service->generate($this->owner, $this->branch->id, '2026-09-01', '2026-09-30');
        $this->assertSame(0, $created2);
        $this->assertSame(1, $skipped2);

        // Penyesuaian +5rb saat draft.
        $service->addAdjustment($this->owner, $period, '5000', 'Bonus lembur');
        $this->assertSame('15000.00', (string) $period->fresh()->incentive_total);

        // Setujui: jurnal Dr Beban Insentif / Cr Utang Insentif 15rb.
        $service->approve($this->owner, $period->fresh());
        $this->assertSame('approved', $period->fresh()->status);
        $this->assertSame('15000.00', $this->balanceOf('5-1800'));
        $this->assertSame('-15000.00', $this->balanceOf('2-1600'));

        // Penyesuaian sesudah setuju ditolak.
        try {
            $service->addAdjustment($this->owner, $period->fresh(), '1000', 'Terlambat');
            $this->fail('Penyesuaian non-draft seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        // Bayar tunai: utang insentif lunas.
        $service->pay($this->owner, $period->fresh(), 'cash');
        $this->assertSame('paid', $period->fresh()->status);
        $this->assertSame('0.00', $this->balanceOf('2-1600'));
    }

    public function test_pages_gated_by_payroll_permission(): void
    {
        $this->actingAs($this->owner)->get(route('incentives.index'))->assertOk();
        $this->actingAs($this->kasir)->get(route('incentives.index'))->assertForbidden();
    }
}
