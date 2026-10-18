<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\InstallmentService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3.2 Cicilan & piutang: jadwal atas sisa (DP menjadi piutang
 * terjadwal), alokasi FIFO penerimaan, jurnal penerimaan, status
 * overdue terhitung, kategori umur piutang, pagar halaman piutang.
 */
class InstallmentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private User $gudang;

    private Branch $branch;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->branch->id]);

        $this->gudang = User::factory()->create();
        $this->gudang->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $this->gudang->branches()->sync([$this->branch->id]);

        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '400000']);
        app(InventoryService::class)->recordMovement(
            $this->owner, $this->frame, $this->branch, 'initial_stock', 20, 'test_seed', 1,
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function saleWithDp(array $extraData = [], string $total = '400000', string $dp = '100000'): Sale
    {
        return app(SalesService::class)->createSale($this->owner, array_merge([
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], $extraData), [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => $total],
        ], $dp === '0' ? [] : [
            ['method' => 'cash', 'amount' => $dp],
        ]);
    }

    public function test_dp_becomes_scheduled_receivable_at_sale_creation(): void
    {
        $sale = $this->saleWithDp([
            'installment_count' => 3, 'installment_first_due' => '2026-11-17', 'installment_interval_days' => 30,
        ]);

        $rows = $sale->installments()->get();
        $this->assertCount(3, $rows);
        $this->assertSame('300000.00', $rows->sum(fn ($r) => (float) $r->amount) === 300000.0 ? '300000.00' : 'mismatch');
        foreach ($rows as $row) {
            $this->assertSame('100000.00', (string) $row->amount);
            $this->assertSame('0.00', (string) $row->paid_amount);
            $this->assertSame('open', $row->status);
        }
        $this->assertSame('2026-11-17', $rows[0]->due_date->toDateString());
        $this->assertSame('2026-12-17', $rows[1]->due_date->toDateString());
        $this->assertSame('2027-01-16', $rows[2]->due_date->toDateString());
    }

    public function test_split_gives_rounding_remainder_to_last_installment(): void
    {
        $sale = $this->saleWithDp([], '100000', '0');
        $rows = app(InstallmentService::class)->createSchedule($this->owner, $sale, 3, '2026-11-17');

        $this->assertSame('33333.33', (string) $rows[0]->amount);
        $this->assertSame('33333.33', (string) $rows[1]->amount);
        $this->assertSame('33333.34', (string) $rows[2]->amount);
    }

    public function test_schedule_guards_no_remaining_duplicate_and_void(): void
    {
        $paid = $this->saleWithDp([], '400000', '400000');
        try {
            app(InstallmentService::class)->createSchedule($this->owner, $paid, 2, '2026-11-17');
            $this->fail('jadwal atas penjualan lunas harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('installment_count', $e->errors());
        }

        $sale = $this->saleWithDp();
        app(InstallmentService::class)->createSchedule($this->owner, $sale, 2, '2026-11-17');
        try {
            app(InstallmentService::class)->createSchedule($this->owner, $sale, 2, '2026-11-17');
            $this->fail('jadwal ganda harus ditolak');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('installment_count', $e->errors());
        }

        app(SalesService::class)->voidSale($this->owner, $sale, 'salah input');
        $this->assertSame(0, $sale->installments()->count());
    }

    public function test_installment_receipts_allocate_fifo_and_are_journaled(): void
    {
        $sale = $this->saleWithDp([
            'installment_count' => 3, 'installment_first_due' => '2026-11-17',
        ]);

        $payment = app(SalesService::class)->addPayment($this->owner, $sale, 'cash', '250000');
        $rows = $sale->installments()->get();
        $this->assertSame('paid', $rows[0]->status);
        $this->assertSame('paid', $rows[1]->status);
        $this->assertSame('partial', $rows[2]->status);
        $this->assertSame('50000.00', (string) $rows[2]->paid_amount);

        // Penerimaan cicilan terjurnal: Dr Kas / Cr Piutang Pelanggan.
        $lines = JournalLine::whereHas('journal', fn ($q) => $q->where('source_type', 'sale_payment')->where('source_id', $payment->id))
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->get(['accounts.code', 'journal_lines.debit', 'journal_lines.credit']);
        $this->assertSame('250000.00', (string) $lines->firstWhere('code', '1-1100')->debit);
        $this->assertSame('250000.00', (string) $lines->firstWhere('code', '1-1300')->credit);

        app(SalesService::class)->addPayment($this->owner, $sale, 'qris', '50000');
        $sale->refresh();
        $this->assertSame('paid', $sale->paymentStatus());
        $this->assertTrue($sale->installments()->get()->every(fn ($r) => $r->status === 'paid'));
    }

    public function test_overdue_is_computed_and_aging_buckets_follow_reference_date(): void
    {
        // Jadwal jatuh tempo 45 hari lalu -> kategori d30, cicilan overdue.
        $old = $this->saleWithDp(['installment_count' => 1, 'installment_first_due' => '2026-09-03']);
        $row = $old->installments()->first();
        $this->assertSame('overdue', $row->effectiveStatus());
        $aging = app(InstallmentService::class)->agingFor($old);
        $this->assertSame(45, $aging['age_days']);
        $this->assertSame('d30', $aging['bucket']);

        // Tanpa jadwal: acuan = tanggal penjualan (100 hari lalu) -> d90.
        $noSchedule = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-07-10', 'tax_mode' => 'inclusive',
        ], [['product_id' => $this->frame->id, 'qty' => 1]]);
        $aging = app(InstallmentService::class)->agingFor($noSchedule);
        $this->assertSame(100, $aging['age_days']);
        $this->assertSame('d90', $aging['bucket']);

        // Belum jatuh tempo -> lancar.
        $fresh = $this->saleWithDp(['installment_count' => 1, 'installment_first_due' => '2026-12-01']);
        $this->assertSame('open', $fresh->installments()->first()->effectiveStatus());
        $this->assertSame('current', app(InstallmentService::class)->agingFor($fresh)['bucket']);
    }

    public function test_receivables_page_filters_aging_and_export_is_permission_gated(): void
    {
        $old = $this->saleWithDp(['installment_count' => 1, 'installment_first_due' => '2026-09-03']);
        $fresh = $this->saleWithDp(['installment_count' => 1, 'installment_first_due' => '2026-12-01']);

        $this->actingAs($this->owner)->get(route('receivables.index'))
            ->assertOk()->assertSee($old->number)->assertSee($fresh->number);
        $this->actingAs($this->owner)->get(route('receivables.index', ['umur' => 'd30']))
            ->assertOk()->assertSee($old->number)->assertDontSee($fresh->number);

        // Kasir boleh melihat daftar tetapi ekspor wajib reports.export.
        $this->actingAs($this->kasir)->get(route('receivables.index'))->assertOk();
        $this->actingAs($this->kasir)->get(route('receivables.index', ['ekspor' => 1]))->assertForbidden();
        $this->actingAs($this->owner)->get(route('receivables.index', ['ekspor' => 1]))
            ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        // Gudang tanpa sales.view tertolak.
        $this->actingAs($this->gudang)->get(route('receivables.index'))->assertForbidden();
    }

    public function test_http_schedule_creation_requires_sales_pay(): void
    {
        $sale = $this->saleWithDp();

        $this->actingAs($this->kasir)->post(route('sales.installment.store', $sale), [
            'installment_count' => 2, 'installment_first_due' => '2026-11-17', 'installment_interval_days' => 30,
        ])->assertRedirect(route('sales.show', $sale));
        $this->assertSame(2, $sale->installments()->count());

        $sale2 = $this->saleWithDp();
        $this->actingAs($this->gudang)->get(route('sales.installment', $sale2))->assertForbidden();
        $this->actingAs($this->gudang)->post(route('sales.installment.store', $sale2), [
            'installment_count' => 2, 'installment_first_due' => '2026-11-17',
        ])->assertForbidden();
    }
}
