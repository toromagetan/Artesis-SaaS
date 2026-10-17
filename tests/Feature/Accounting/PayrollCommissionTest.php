<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Services\CommissionService;
use App\Modules\Accounting\Services\PayrollService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Setting;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F1.7 Payroll & komisi: payroll per periode terjurnal & entri
 * terkunci sesudah posting; komisi dokter terakru dari penjualan
 * final; komisi teknisi dari proses Complete; void mengoreksi
 * lewat baris penyeimbang; pembayaran komisi menjurnal Utang→Kas.
 */
class PayrollCommissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000', 'cost_price' => '400000']);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 20, 'test_seed', 1);
    }

    private function staff(string $username): User
    {
        $user = User::create(['name' => ucfirst($username), 'username' => $username, 'password' => bcrypt('password'), 'is_active' => true]);
        $user->branches()->attach($this->branch);

        return $user;
    }

    private function accountBalance(string $code): string
    {
        $row = DB::table('journal_lines')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', $code)
            ->selectRaw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as saldo')
            ->first();

        return Money::fromDecimal((string) ($row->saldo ?? '0'))->toDecimalString();
    }

    public function test_payroll_posting_journals_net_and_locks_entries(): void
    {
        $service = app(PayrollService::class);
        $staff = $this->staff('staf-f17');

        $service->upsertEntry($this->owner, $this->owner->id, '2026-09', '5000000', '500000', '250000');
        $service->upsertEntry($this->owner, $staff->id, '2026-09', '3000000', '0', '0');
        $this->assertSame('5250000.00', Payroll::where('employee_user_id', $this->owner->id)->first()->net_amount);
        $this->assertSame(0, Journal::where('source_type', 'payroll')->count());

        $journal = $service->postPeriod($this->owner, '2026-09', 'cash');
        $this->assertNotNull($journal);
        // Dr Beban Gaji 8.250.000 = Cr Kas (akun 5-1500 debit-normal).
        $this->assertSame('8250000.00', $this->accountBalance('5-1500'));
        $this->assertSame('-8250000.00', $this->accountBalance('1-1100'));
        $this->assertSame(2, Payroll::whereNotNull('journal_id')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payrolls.post']);

        // Idempoten + entri terkunci.
        $this->assertNull($service->postPeriod($this->owner, '2026-09', 'cash'));
        $this->assertSame(1, Journal::where('source_type', 'payroll')->count());
        try {
            $service->upsertEntry($this->owner, $staff->id, '2026-09', '3500000', '0', '0');
            $this->fail('Entri terjurnal harus terkunci.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
    }

    public function test_doctor_commission_accrues_posts_and_pays(): void
    {
        $doctor = Doctor::create(['name' => 'dr. Rujukan', 'commission_rate' => '10', 'is_active' => true]);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-10', 'tax_mode' => 'exclusive', 'doctor_id' => $doctor->id,
        ], [['product_id' => $this->frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '555000']]);

        $row = Commission::where('source_sale_id', $sale->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('doctor', $row->type);
        $this->assertSame('500000.00', $row->base_amount); // 555.000 − pajak 55.000
        $this->assertSame('50000.00', $row->amount);
        $this->assertSame('2026-10', $row->period);
        $this->assertSame('accrued', $row->status);

        $service = app(CommissionService::class);
        // Belum terjurnal → belum bisa dibayar.
        try {
            $service->payBeneficiary($this->owner, 'doctor', $doctor->id, null, 'cash');
            $this->fail('Komisi belum terjurnal tidak boleh dibayar.');
        } catch (ValidationException) {
        }

        $this->assertNotNull($service->postPeriod($this->owner, '2026-10'));
        $payable = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1300')->selectRaw('SUM(journal_lines.credit) - SUM(journal_lines.debit) as s')->value('s');
        $this->assertSame('50000.00', Money::fromDecimal((string) $payable)->toDecimalString());

        $service->payBeneficiary($this->owner, 'doctor', $doctor->id, null, 'cash');
        $this->assertSame('paid', $row->fresh()->status);
        $credit = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1300')->selectRaw('SUM(journal_lines.credit) - SUM(journal_lines.debit) as s')->value('s');
        $this->assertSame('0.00', Money::fromDecimal((string) $credit)->toDecimalString());
    }

    public function test_technician_commission_accrues_on_completion(): void
    {
        Setting::updateOrCreate(['key' => 'lab.technician_commission_rate'], ['value' => '5']);
        $technician = $this->staff('teknisi-f17');
        $lens = Product::factory()->ofType('lens')->create(['sale_price' => '300000', 'cost_price' => '200000']);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-11', 'tax_mode' => 'exclusive',
        ], [['product_id' => $lens->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '333000']]);

        $process = LensProcess::where('sale_id', $sale->id)->first();
        $this->assertNotNull($process);
        $lab = app(LensProcessService::class);
        $lab->assignTechnician($this->owner, $process, $technician->id);
        $this->assertSame(0, Commission::where('type', 'technician')->count());

        $lab->complete($this->owner, $process->refresh());
        $row = Commission::where('type', 'technician')->first();
        $this->assertNotNull($row);
        $this->assertSame($technician->id, $row->beneficiary_user_id);
        $this->assertSame('15000.00', $row->amount); // 5% × 300.000 (di luar pajak)
        $this->assertSame(now()->format('Y-m'), $row->period);
    }

    public function test_void_creates_offsetting_correction_and_repost_zeroes_payable(): void
    {
        $doctor = Doctor::create(['name' => 'dr. Koreksi', 'commission_rate' => '10', 'is_active' => true]);
        $sales = app(SalesService::class);
        $sale = $sales->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => now()->toDateString(), 'tax_mode' => 'exclusive', 'doctor_id' => $doctor->id,
        ], [['product_id' => $this->frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '555000']]);

        $service = app(CommissionService::class);
        $period = now()->format('Y-m');
        $this->assertNotNull($service->postPeriod($this->owner, $period));

        $sales->voidSale($this->owner, $sale, 'Salah input dokter');
        $correction = Commission::where('corrects_id', '!=', null)->first();
        $this->assertNotNull($correction);
        $this->assertSame('-50000.00', $correction->amount);
        $this->assertSame('corrected', $correction->status);
        $this->assertSame($period, $correction->period);
        // Baris asal tidak diubah.
        $this->assertSame('50000.00', Commission::where('source_sale_id', $sale->id)->whereNull('corrects_id')->first()->amount);

        // Posting ulang periode menjurnal koreksi → utang komisi kembali nol.
        $this->assertNotNull($service->postPeriod($this->owner, $period));
        $net = DB::table('journal_lines')->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1300')->selectRaw('SUM(journal_lines.credit) - SUM(journal_lines.debit) as s')->value('s');
        $this->assertSame('0.00', Money::fromDecimal((string) $net)->toDecimalString());
    }

    public function test_payroll_and_commission_pages_are_gated(): void
    {
        $this->actingAs($this->owner)->get(route('payroll.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('commissions.index'))->assertOk();

        $admin = $this->staff('admin-f17');
        $admin->roles()->attach(Role::where('name', 'Admin')->firstOrFail());
        $this->actingAs($admin)->get(route('payroll.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('commissions.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('payroll.post'), ['period' => '2026-10', 'method' => 'cash'])->assertForbidden();

        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get(route('payroll.index'))->assertForbidden();
    }
}
