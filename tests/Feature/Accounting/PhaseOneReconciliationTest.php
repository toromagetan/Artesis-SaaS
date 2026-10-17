<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\FinancialReportService;
use App\Modules\Accounting\Services\FixedAssetService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\ManualJournalService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\PurchaseReturnService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Reporting\Services\ReportService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Carbon\Carbon;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * GERBANG FASE 1 — rekonsiliasi periode contoh (September 2026,
 * sebulan penuh aktivitas lewat service modul nyata): penjualan
 * tunai + kredit + pembayaran susulan, pembelian + retur +
 * pembayaran, penyusutan, jurnal manual.
 *
 * Bukti yang dituntut Roadmap:
 * (a) total laporan = total jurnal & neraca seimbang;
 * (c) hitungan manual INDEPENDEN (SQL agregat yang dirumuskan
 *     berbeda dari service) cocok dengan keluaran sistem.
 */
class PhaseOneReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        // Bekukan waktu di akhir September: dokumen tanpa tanggal
        // eksplisit (retur pembelian) memakai tanggal hari berjalan,
        // dan skenario gerbang ini mensimulasikan operasi September.
        $this->travelTo(Carbon::parse('2026-09-30 12:00:00'));
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $b = $this->branch->id;

        $manual = app(ManualJournalService::class);
        $akun = fn (string $code) => Account::where('code', $code)->firstOrFail()->id;

        // 1 Sep — setoran modal tunai 10.000.000 (jurnal manual).
        $manual->postManual($this->owner, '2026-09-01', 'Setoran modal', [
            ['account_id' => $akun('1-1100'), 'debit' => '10000000'],
            ['account_id' => $akun('3-1000'), 'credit' => '10000000'],
        ]);

        // 2–3 Sep — PO frame 10 @400.000, diterima penuh.
        $supplier = Supplier::create(['name' => 'PT Gerbang', 'is_active' => true]);
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '400000', 'sale_price' => '500000']);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $b, [
            ['product_id' => $frame->id, 'qty' => 10, 'unit_cost' => '400000'],
        ]);
        $orders->markSent($this->owner, $po);
        $receipt = app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 10],
        ], '2026-09-03', 'SJ-GB-001');

        // 5 Sep — penjualan tunai 2 frame (total 1.110.000 incl. pajak eksklusif).
        $customer = Customer::factory()->create(['branch_id' => $b]);
        $sales = app(SalesService::class);
        $sales->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-09-05', 'tax_mode' => 'exclusive',
        ], [['product_id' => $frame->id, 'qty' => 2]], [['method' => 'cash', 'amount' => '1110000']]);

        // 8 Sep — retur pembelian 1 frame (receipt belum dibayar → kurangi utang).
        app(PurchaseReturnService::class)->store($this->owner, $receipt, [
            ['receipt_item_id' => $receipt->items->first()->id, 'qty' => 1],
        ], 'Kemasan rusak');

        // 10 Sep — bayar lunas sisa utang 3.600.000 tunai.
        app(PurchasePaymentService::class)->paySingle($this->owner, $receipt->refresh(), '3600000', 'cash', '2026-09-10');

        // 12 Sep — penjualan kredit 1 frame (555.000), dibayar susulan tunai.
        $sale2 = $sales->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-09-12', 'tax_mode' => 'exclusive',
        ], [['product_id' => $frame->id, 'qty' => 1]], []);
        $sales->addPayment($this->owner, $sale2, 'cash', '555000');

        // 15 Sep — aset tetap (perolehan Agustus) disusutkan untuk September.
        $assets = app(FixedAssetService::class);
        $assets->create($this->owner, [
            'name' => 'Mesin Edger', 'acquired_at' => '2026-08-20',
            'acquisition_cost' => '12000000', 'useful_life_months' => 12,
        ]);
        $assets->depreciatePeriod($this->owner, '2026-09');

        // 20 Sep — jurnal manual beban gaji cabang 750.000.
        $manual->postManual($this->owner, '2026-09-20', 'Gaji September', [
            ['account_id' => $akun('5-1500'), 'debit' => '750000', 'branch_id' => $b],
            ['account_id' => $akun('1-1100'), 'credit' => '750000', 'branch_id' => $b],
        ]);
    }

    public function test_full_month_reconciles_across_reports_and_journals(): void
    {
        $financial = app(FinancialReportService::class);
        $from = '2026-09-01';
        $to = '2026-09-30';

        // (a1) Seluruh jurnal seimbang secara global.
        $totals = DB::table('journal_lines')->selectRaw('SUM(debit) as d, SUM(credit) as c')->first();
        $this->assertSame((string) $totals->d, (string) $totals->c);

        // (a2) Neraca seimbang & cocok dengan agregat SQL independen per kelompok.
        $sheet = $financial->balanceSheet($to);
        $this->assertTrue($sheet['balanced']);
        $independent = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journals.journal_date', '<=', $to)
            ->groupBy('accounts.group')
            ->selectRaw("accounts.group as grp, SUM(CASE WHEN accounts.group IN ('asset','expense') THEN journal_lines.debit - journal_lines.credit ELSE journal_lines.credit - journal_lines.debit END) as saldo")
            ->pluck('saldo', 'grp');
        // Jangkar tangan: Kas 7.315.000 + Persediaan 2.400.000 − Akumulasi 1.000.000.
        $this->assertSame('8715000.00', Money::fromDecimal((string) $independent['asset'])->toDecimalString());
        $this->assertEquals(
            Money::fromDecimal((string) $independent['asset'])->toDecimalString(),
            $sheet['total_assets']->toDecimalString(),
        );
        $this->assertEquals(
            Money::fromDecimal((string) $independent['liability'])->toDecimalString(),
            $sheet['total_liabilities']->toDecimalString(),
        );
        $earningsIndependent = Money::fromDecimal((string) $independent['revenue'])->sub(Money::fromDecimal((string) $independent['expense']));
        $this->assertSame($earningsIndependent->toDecimalString(), $sheet['earnings']->toDecimalString());

        // (c) Laba rugi: jangkar hitungan tangan + agregat independen.
        // Pendapatan 1.500.000; beban = HPP 1.200.000 + penyusutan 1.000.000 + gaji 750.000.
        $income = $financial->incomeStatement($from, $to);
        $this->assertSame('1500000.00', $income['total_revenue']->toDecimalString());
        $this->assertSame('2950000.00', $income['total_expense']->toDecimalString());
        $this->assertSame('-1450000.00', $income['net']->toDecimalString());

        // (a3) Laporan pembelian (M.10) = jurnal penerimaan − retur pembelian.
        $report = app(ReportService::class)->purchasesNet($from, $to, [$this->branch->id]);
        $reportNet = Money::zero();
        foreach ($report as $row) {
            $reportNet = $reportNet->add($row['net']);
        }
        // Ikatan laporan↔jurnal: kredit Utang dari jurnal penerimaan
        // dikurangi debit Utang dari jurnal retur pembelian.
        $receiptCredit = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1100')->where('journals.source_type', 'receipt')
            ->sum('journal_lines.credit');
        $returnDebit = DB::table('journal_lines')
            ->join('journals', 'journals.id', '=', 'journal_lines.journal_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1100')->where('journals.source_type', 'purchase_return')
            ->sum('journal_lines.debit');
        $this->assertSame('3600000.00', $reportNet->toDecimalString());
        $this->assertSame(
            Money::fromDecimal((string) $receiptCredit)->sub(Money::fromDecimal((string) $returnDebit))->toDecimalString(),
            $reportNet->toDecimalString(),
        );

        // (a4) Arus kas: saldo akhir = saldo buku besar Kas + Bank (LedgerService).
        $flow = $financial->cashFlow($from, $to);
        $kas = Account::where('code', '1-1100')->firstOrFail();
        $bank = Account::where('code', '1-1200')->firstOrFail();
        $ledger = app(LedgerService::class);
        $kasClosing = $ledger->ledger($kas, '2000-01-01', $to)['closing'];
        $bankClosing = $ledger->ledger($bank, '2000-01-01', $to)['closing'];
        $this->assertSame($kasClosing->add($bankClosing)->toDecimalString(), $flow['closing']->toDecimalString());
        // Kas: 10.000.000 + 1.110.000 + 555.000 − 3.600.000 − 750.000 = 7.315.000; Bank 0.
        $this->assertSame('7315000.00', $flow['closing']->toDecimalString());
    }
}
