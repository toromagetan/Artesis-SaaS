<?php

namespace Tests\Feature\Comms;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Membership\Models\PointsLedger;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipLevelSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * GERBANG KELUAR FASE 3 (Roadmap §9):
 * (a) DP + split 2 metode + 3 cicilan sampai lunas — rekonsiliasi
 *     penuh transaksi = pembayaran = piutang = jurnal = laporan;
 * (b) 100% pesan keluar tercatat di log; gateway gagal total pun
 *     transaksi tetap tersimpan dan pesan tercatat untuk antre ulang;
 * (c) saldo Liabilitas Poin di jurnal == Σ poin aktif di ledger.
 */
class PhaseThreeGateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, WaTemplateSeeder::class, MembershipLevelSeeder::class]);
        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Gerbang Tiga', 'phone' => '0812-3333-4444',
        ]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        app(InventoryService::class)
            ->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 10, 'test_seed', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Σ (debit, kredit) satu akun di jurnal-jurnal milik penjualan ini. */
    private function accountTotalsForSale(Sale $sale, string $accountCode): array
    {
        $journalIds = Journal::where(function ($q) use ($sale) {
            $q->where(fn ($s) => $s->where('source_type', 'sale')->where('source_id', $sale->id))
                ->orWhere(fn ($s) => $s->where('source_type', 'sale_payment')
                    ->whereIn('source_id', $sale->payments()->pluck('id')));
        })->pluck('id');
        $accountId = Account::where('code', $accountCode)->value('id');
        $lines = JournalLine::whereIn('journal_id', $journalIds)->where('account_id', $accountId);

        return [
            'debit' => (string) (clone $lines)->sum('debit'),
            'credit' => (string) (clone $lines)->sum('credit'),
        ];
    }

    public function test_full_chain_dp_split_installments_reconciles_and_logs(): void
    {
        $sales = app(SalesService::class);

        // Eksklusif: pokok 1.000.000 + PPN 110.000 = total 1.110.000.
        // DP split: tunai 200.000 + QRIS 100.000; sisa 810.000 dicicil 3x.
        $sale = $sales->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'exclusive',
            'installment_count' => 3, 'installment_first_due' => '2026-11-17',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 2],
        ], [
            ['method' => 'cash', 'amount' => '200000'],
            ['method' => 'qris', 'amount' => '100000'],
        ]);

        $this->assertSame('partial', $sale->paymentStatus());
        $this->assertCount(3, $sale->installments()->get());
        foreach ($sale->installments()->get() as $installment) {
            $this->assertSame('270000.00', (string) $installment->amount);
            $this->assertSame('open', $installment->status);
        }

        // Halaman piutang memuat penjualan ini selama belum lunas.
        $this->actingAs($this->owner)->get('/piutang')->assertOk()->assertSee($sale->number);

        // Lunasi tiga cicilan dengan tiga metode berbeda.
        $sales->addPayment($this->owner, $sale, 'cash', '270000');
        $sales->addPayment($this->owner, $sale, 'qris', '270000');
        $sales->addPayment($this->owner, $sale, 'transfer', '270000');

        $sale->refresh();
        $this->assertSame('paid', $sale->paymentStatus());
        $this->assertTrue($sale->remaining()->isZero());
        $this->assertSame(['paid', 'paid', 'paid'], $sale->installments()->pluck('status')->all());

        // Pembayaran == total transaksi.
        $paidSum = Money::fromDecimal((string) $sale->payments()->whereNull('reversal_of_id')->sum('amount'));
        $this->assertTrue($paidSum->equals(Money::fromDecimal((string) $sale->total_amount)));

        // Jurnal: piutang dibuka 810.000 saat transaksi dan tertutup
        // persis oleh tiga penerimaan cicilan.
        $piutang = $this->accountTotalsForSale($sale, '1-1300');
        $this->assertSame('810000.00', $piutang['debit']);
        $this->assertSame('810000.00', $piutang['credit']);
        // Tender: Kas 200+270; Bank 100 (qris) + 270 (qris) + 270 (transfer).
        $kas = $this->accountTotalsForSale($sale, '1-1100');
        $bank = $this->accountTotalsForSale($sale, '1-1200');
        $this->assertSame('470000.00', $kas['debit']);
        $this->assertSame('640000.00', $bank['debit']);
        // Pendapatan & PPN dari jurnal penyelesaian.
        $pendapatan = $this->accountTotalsForSale($sale, '4-1100');
        $ppn = $this->accountTotalsForSale($sale, '2-1400');
        $this->assertSame('1000000.00', $pendapatan['credit']);
        $this->assertSame('110000.00', $ppn['credit']);

        // Laporan piutang: penjualan lunas tidak lagi tampil.
        $this->actingAs($this->owner)->get('/piutang')->assertOk()->assertDontSee($sale->number);

        // (b) Invoice WA untuk penjualan ini tercatat di log.
        $invoice = WaMessage::where('template_key', 'invoice')->where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertStringContainsString($sale->number, $invoice->body);

        // (c) Poin: basis di luar pajak 1.000.000 -> 100 poin; liabilitas
        // jurnal == Σ poin aktif x nilai tukar.
        $this->assertSame(100, (int) PointsLedger::sum('points'));
        $liability = Money::fromDecimal((string) JournalLine::join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1200')->sum('credit'))
            ->sub(Money::fromDecimal((string) JournalLine::join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
                ->where('accounts.code', '2-1200')->sum('debit')));
        $expected = app(PointsService::class)->pointValue()->mul(100);
        $this->assertTrue($liability->equals($expected), 'liabilitas poin harus sama dengan poin aktif x nilai tukar');
    }

    public function test_gateway_total_failure_keeps_sale_and_logs_message(): void
    {
        WaGateway::create([
            'provider' => 'meta_cloud_api',
            'sender_number' => '6281234567890',
            'credentials_encrypted' => [
                'phone_number_id' => '1234567890', 'waba_id' => '1',
                'access_token' => 'EAAB-token', 'verify_token' => 'x', 'api_version' => 'v21.0',
            ],
            'is_active' => true,
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 500)]);

        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '500000'],
        ]);

        // Transaksi utuh tersimpan & lunas meski gateway mati total.
        $this->assertSame('paid', $sale->paymentStatus());
        $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'active']);

        // Pesan TETAP tercatat (untuk antrean ulang di produksi).
        $invoice = WaMessage::where('template_key', 'invoice')->where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($invoice);
        $this->assertStringContainsString($sale->number, $invoice->body);
        $this->assertContains($invoice->status, ['queued', 'failed']);
    }
}
