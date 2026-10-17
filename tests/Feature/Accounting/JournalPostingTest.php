<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Accounting\Support\Chart;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InitialStockImportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.9 Jurnal otomatis (D.9 + E.1): penjualan lunas & piutang, cicilan,
 * void → jurnal balik, receipt → utang, bayar supplier, impor awal;
 * Σdebit = Σkredit selalu; periode terkunci menolak posting.
 */
class JournalPostingTest extends TestCase
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
        $this->frame = Product::factory()->ofType('frame')->create([
            'name' => 'Frame Jurnal', 'cost_price' => '60000', 'sale_price' => '100000',
        ]);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 20, 'test_seed', 1);
    }

    /** Saldo satu akun (debit − kredit) lintas jurnal, opsi per cabang. */
    private function balance(string $code): string
    {
        $account = Account::where('code', $code)->firstOrFail();
        $debit = Money::zero();
        $credit = Money::zero();
        foreach (JournalLine::where('account_id', $account->id)->get() as $line) {
            $debit = $debit->add(Money::fromDecimal((string) $line->debit));
            $credit = $credit->add(Money::fromDecimal((string) $line->credit));
        }

        return $debit->sub($credit)->toDecimalString();
    }

    private function assertAllJournalsBalanced(): void
    {
        foreach (Journal::with('lines')->get() as $journal) {
            $d = Money::zero();
            $c = Money::zero();
            foreach ($journal->lines as $line) {
                $d = $d->add(Money::fromDecimal((string) $line->debit));
                $c = $c->add(Money::fromDecimal((string) $line->credit));
            }
            $this->assertSame(0, $d->cmp($c), 'Jurnal '.$journal->number.' tidak seimbang');
        }
    }

    public function test_sale_paid_and_receivable_journals_then_void_reverses(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $sales = app(SalesService::class);

        // Lunas tunai 200.000 (2 × 100.000), pajak inklusif 11% bawaan seed.
        $sale = $sales->createSale($this->owner, [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'sale_date' => '2026-10-17',
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 2, 'unit_price' => '100000'],
        ], [
            ['method' => 'cash', 'amount' => '200000'],
        ]);
        $journal = Journal::where('source_type', 'sale')->where('source_id', $sale->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^JU-\d{6}-\d{4}$/', $journal->number);
        // Kas terdebit penuh; pendapatan = 200.000 − PPN inklusif (200rb×11/111 = 19.819,82).
        $this->assertSame('200000.00', $this->balance(Chart::KAS));
        $this->assertSame('-180180.18', $this->balance(Chart::PENDAPATAN_PENJUALAN));
        $this->assertSame('-19819.82', $this->balance(Chart::PPN_KELUARAN));
        // HPP 2 × 60.000 mengurangi persediaan (persediaan awal 0 di jurnal — seeding stok tanpa jurnal).
        $this->assertSame('120000.00', $this->balance(Chart::HPP));
        $this->assertAllJournalsBalanced();

        // Penjualan piutang: bayar 50.000 dari 100.000 → piutang 50.000; cicilan menutupnya.
        $sale2 = $sales->createSale($this->owner, [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'sale_date' => '2026-10-17',
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '100000'],
        ], [
            ['method' => 'qris', 'amount' => '50000'],
        ]);
        $this->assertSame('50000.00', $this->balance(Chart::PIUTANG_PELANGGAN));
        $sales->addPayment($this->owner, $sale2, 'transfer', '50000');
        $this->assertSame('0.00', $this->balance(Chart::PIUTANG_PELANGGAN));
        $this->assertSame(1, Journal::where('source_type', 'sale_payment')->count());
        $this->assertAllJournalsBalanced();

        // Void penjualan pertama → jurnal balik menihilkan kas & pendapatan jurnal itu.
        $kasBefore = $this->balance(Chart::KAS);
        $sales->voidSale($this->owner, $sale, 'uji void jurnal');
        $this->assertSame(1, Journal::where('source_type', 'reversal')->where('reversal_of_id', $journal->id)->count());
        $this->assertSame(
            Money::fromDecimal($kasBefore)->sub(Money::fromDecimal('200000'))->toDecimalString(),
            $this->balance(Chart::KAS),
        );
        $this->assertAllJournalsBalanced();
    }

    public function test_purchasing_and_import_journals(): void
    {
        $supplier = Supplier::create(['name' => 'PT Jurnal', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => 5, 'unit_cost' => '80000'],
        ]);
        $orders->markSent($this->owner, $po);
        $receipt = app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 5, 'unit_cost_actual' => '82000'],
        ]);
        // Dr Persediaan / Cr Utang pada harga AKTUAL 5 × 82.000 = 410.000.
        $this->assertSame('410000.00', $this->balance(Chart::PERSEDIAAN));
        $this->assertSame('-410000.00', $this->balance(Chart::UTANG_SUPPLIER));

        app(CollectivePaymentService::class)->process($this->owner, $supplier, '410000', 'transfer', '2026-10-17');
        $this->assertSame('0.00', $this->balance(Chart::UTANG_SUPPLIER));
        $this->assertSame('-410000.00', $this->balance(Chart::BANK));
        $this->assertAllJournalsBalanced();

        // Impor stok awal → Dr Persediaan / Cr Modal Persediaan Awal.
        $branchB = Branch::create(['name' => 'Cabang Jurnal']);
        $import = app(InitialStockImportService::class);
        $preview = $import->preview("sku,cabang,qty\n{$this->frame->sku},{$branchB->id},3\n");
        $import->commit($this->owner, $preview['token']);
        $this->assertSame('-180000.00', $this->balance(Chart::MODAL_PERSEDIAAN_AWAL)); // 3 × 60.000
        $this->assertAllJournalsBalanced();
    }

    public function test_period_lock_and_unbalanced_lines_rejected(): void
    {
        $posting = app(PostingService::class);
        $posting->lockPeriod($this->owner, '2026-10');
        try {
            $posting->post('manual', null, '2026-10-05', [
                ['account' => Chart::KAS, 'debit' => '1000'],
                ['account' => Chart::MODAL_PEMILIK, 'credit' => '1000'],
            ]);
            $this->fail('Posting ke periode terkunci harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, Journal::count());
        }
        // Unlock ber-alasan membuka kembali.
        $posting->unlockPeriod($this->owner, '2026-10', 'koreksi pembukuan');
        $journal = $posting->post('manual', null, '2026-10-05', [
            ['account' => Chart::KAS, 'debit' => '1000'],
            ['account' => Chart::MODAL_PEMILIK, 'credit' => '1000'],
        ]);
        $this->assertSame(1, Journal::count());

        // Tidak seimbang & akun asing ditolak.
        foreach ([
            [['account' => Chart::KAS, 'debit' => '1000'], ['account' => Chart::MODAL_PEMILIK, 'credit' => '999']],
            [['account' => '9-9999', 'debit' => '1000'], ['account' => Chart::MODAL_PEMILIK, 'credit' => '1000']],
            [['account' => Chart::KAS, 'debit' => '1000', 'credit' => '1000'], ['account' => Chart::MODAL_PEMILIK, 'credit' => '1000']],
        ] as $lines) {
            try {
                $posting->post('manual', null, '2026-11-05', $lines);
                $this->fail('Baris jurnal cacat harus ditolak');
            } catch (ValidationException) {
                $this->assertSame(1, Journal::count());
            }
        }

        // Jurnal balik: sekali saja; balik dari balik ditolak.
        $reversal = $posting->reverse($journal, 'salah catat');
        $this->assertSame('0.00', $this->balance(Chart::KAS));
        try {
            $posting->reverse($journal, 'lagi');
            $this->fail('Balik ganda harus ditolak');
        } catch (ValidationException) {
        }
        try {
            $posting->reverse($reversal, 'balik dari balik');
            $this->fail('Jurnal balik tidak dapat dibalik');
        } catch (ValidationException) {
            $this->assertSame(2, Journal::count());
        }

        // Halaman jurnal baca-saja terender untuk Owner; Kasir tanpa izin jurnal → 403.
        $this->actingAs($this->owner)->get('/jurnal')->assertOk()->assertSee($journal->number);
        $this->actingAs($this->owner)->get('/akun')->assertOk()->assertSee('1-1400');
    }
}
