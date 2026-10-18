<?php

namespace Tests\Feature\Membership;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Membership\Models\MembershipLevel;
use App\Modules\Membership\Models\PointsLedger;
use App\Modules\Membership\Services\PointsService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\MembershipLevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3.6 Membership & poin: earn (basis di luar pajak, pengali level),
 * redeem sebagai pembayaran, pembalikan saat void, kedaluwarsa FIFO,
 * penyesuaian manual, dan invariant liabilitas jurnal == Σ poin aktif
 * x nilai tukar.
 */
class PointsMembershipTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, MembershipLevelSeeder::class]);
        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        app(InventoryService::class)
            ->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 50, 'test_seed', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Saldo jurnal akun liabilitas poin (kredit − debit). */
    private function liability(): string
    {
        $lines = JournalLine::join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.code', '2-1200');
        $credit = (string) (clone $lines)->sum('credit');
        $debit = (string) (clone $lines)->sum('debit');

        return Money::fromDecimal($credit)->sub(Money::fromDecimal($debit))->toDecimalString();
    }

    /** Invariant gerbang: liabilitas == Σ poin aktif x nilai tukar. */
    private function assertLiabilityMatchesPoints(): void
    {
        $points = (int) PointsLedger::sum('points');
        $expected = app(PointsService::class)->pointValue()->mul($points)->toDecimalString();
        $this->assertSame($expected, $this->liability(), 'liabilitas poin jurnal harus sama dengan poin aktif x nilai tukar');
    }

    /** Penjualan eksklusif: total 555.000 = pokok 500.000 + PPN 55.000. */
    private function exclusiveSale(?Customer $customer = null): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => ($customer ?? $this->customer)->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'exclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '555000'],
        ]);
    }

    public function test_earn_uses_net_of_tax_basis_and_posts_liability(): void
    {
        $this->exclusiveSale();

        // Basis 500.000 / 10.000 = 50 poin x pengali Reguler 1.00.
        $earn = PointsLedger::where('type', 'earn')->firstOrFail();
        $this->assertSame(50, $earn->points);
        $this->assertSame(50, app(PointsService::class)->balance($this->customer));
        $this->assertSame('2027-10-18', $earn->expires_at->toDateString());
        // Liabilitas: 50 poin x Rp100.
        $this->assertSame('5000.00', $this->liability());
        $this->assertLiabilityMatchesPoints();
    }

    public function test_level_up_applies_multiplier_on_next_earn(): void
    {
        MembershipLevel::where('name', 'Silver')->update(['min_accumulation' => '500000']);
        $points = app(PointsService::class);

        $this->exclusiveSale(); // akumulasi 555.000 -> naik Silver
        $this->customer->refresh();
        $this->assertSame('Silver', $this->customer->level?->name);

        $this->exclusiveSale(); // basis 500.000 -> 50 x 1.25 = 62 poin
        $earns = PointsLedger::where('type', 'earn')->orderBy('id')->pluck('points')->all();
        $this->assertSame([50, 62], $earns);
        $this->assertSame(112, $points->balance($this->customer));
        $this->assertLiabilityMatchesPoints();
    }

    public function test_redeem_via_additional_payment_spends_points(): void
    {
        $points = app(PointsService::class);
        $this->exclusiveSale(); // +50 poin

        // Penjualan kedua 100.000 dibayar sebagian dengan poin:
        // 50 poin x Rp100 = Rp5.000.
        $sale2 = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '100000'],
        ], [
            ['method' => 'cash', 'amount' => '95000'],
        ]);
        // Penjualan kedua inklusif memberi earn sendiri: total 100.000
        // mengandung PPN 9.909,91 -> basis 90.090,09 -> 9 poin.
        $this->assertSame(59, $points->balance($this->customer));

        app(SalesService::class)->addPayment($this->owner, $sale2, 'points', '5000');
        $this->assertSame(9, $points->balance($this->customer));
        $redeem = PointsLedger::where('type', 'redeem')->firstOrFail();
        $this->assertSame(-50, $redeem->points);
        $this->assertSame('paid', $sale2->refresh()->paymentStatus());
        $this->assertLiabilityMatchesPoints();
    }

    public function test_redeem_at_creation_and_guards(): void
    {
        $points = app(PointsService::class);
        $points->adjust($this->owner, $this->customer, 100, 'modal uji');

        // Bayar penuh 10.000 dengan 100 poin saat pembuatan.
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '10000'],
        ], [
            ['method' => 'points', 'amount' => '10000'],
        ]);
        $this->assertSame('paid', $sale->paymentStatus());
        // Earn penjualan ini: inklusif 10.000 -> basis di luar pajak
        // 9.009,01 -> 0 poin. Saldo: 100 - 100 + 0.
        $this->assertSame(0, $points->balance($this->customer));
        $this->assertLiabilityMatchesPoints();

        // Nominal bukan kelipatan nilai tukar -> ditolak.
        try {
            app(SalesService::class)->addPayment($this->owner, $this->exclusiveSale(), 'points', '5050');
            $this->fail('nominal bukan kelipatan harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        // Saldo tidak cukup -> ditolak.
        try {
            app(SalesService::class)->addPayment($this->owner, $this->exclusiveSale(), 'points', '100000');
            $this->fail('saldo kurang harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_void_reverses_earn_and_restores_redemption(): void
    {
        $points = app(PointsService::class);
        $sale = $this->exclusiveSale(); // earn 50
        $this->assertSame(50, $points->balance($this->customer));

        app(SalesService::class)->voidSale($this->owner, $sale, 'uji batal');
        $this->assertSame(0, $points->balance($this->customer));
        $this->assertSame('0.00', $this->liability());
        $this->assertSame(1, PointsLedger::where('type', 'reversal')->count());

        // Penjualan dibayar poin lalu di-void -> poin kembali.
        $points->adjust($this->owner, $this->customer, 100, 'modal');
        $paidByPoints = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1, 'unit_price' => '10000'],
        ], [
            ['method' => 'points', 'amount' => '10000'],
        ]);
        $balanceBeforeVoid = $points->balance($this->customer);
        app(SalesService::class)->voidSale($this->owner, $paidByPoints, 'uji batal 2');
        // Redeem dipulihkan (+100); penjualan itu tidak menghasilkan
        // earn (basis di bawah tarif) sehingga tidak ada yang dibalik.
        $this->assertSame($balanceBeforeVoid + 100, $points->balance($this->customer));
        $this->assertLiabilityMatchesPoints();
    }

    public function test_expire_consumes_oldest_lots_fifo(): void
    {
        $points = app(PointsService::class);
        // Lot A: 50 poin kedaluwarsa 2026-01-10; Lot B: 30 poin berlaku s.d. 2027.
        PointsLedger::create(['customer_id' => $this->customer->id, 'type' => 'earn', 'points' => 50, 'reference_type' => 'manual_adjustment', 'expires_at' => '2026-01-10', 'created_at' => '2025-01-10 00:00:00']);
        PointsLedger::create(['customer_id' => $this->customer->id, 'type' => 'earn', 'points' => 30, 'reference_type' => 'manual_adjustment', 'expires_at' => '2027-06-01', 'created_at' => '2025-06-01 00:00:00']);
        // Penukaran 20 poin memakan lot A (FIFO) -> sisa A 30.
        PointsLedger::create(['customer_id' => $this->customer->id, 'type' => 'redeem', 'points' => -20, 'reference_type' => 'sale_payment', 'reference_id' => 999]);

        $created = $points->expireRun(Carbon::parse('2026-10-18'));
        $this->assertSame(1, $created);
        $expire = PointsLedger::where('type', 'expire')->firstOrFail();
        $this->assertSame(-30, $expire->points);
        $this->assertSame(30, $points->balance($this->customer));

        // Idempoten: jalan lagi tidak membuat baris baru.
        $this->assertSame(0, $points->expireRun(Carbon::parse('2026-10-18')));

        // Perintah artisan berjalan.
        $this->artisan('lentera:poin-kedaluwarsa')->assertSuccessful();
    }

    public function test_manual_adjust_requires_reason_and_balance(): void
    {
        $points = app(PointsService::class);
        $points->adjust($this->owner, $this->customer, 40, 'bonus pembukaan');
        $this->assertSame(40, $points->balance($this->customer));
        $points->adjust($this->owner, $this->customer, -15, 'koreksi kasir');
        $this->assertSame(25, $points->balance($this->customer));
        $this->assertLiabilityMatchesPoints();

        try {
            $points->adjust($this->owner, $this->customer, -100, 'melebihi saldo');
            $this->fail('pengurangan melebihi saldo harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $points->adjust($this->owner, $this->customer, 5, '  ');
            $this->fail('alasan kosong harus ditolak');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_membership_http_permissions_and_settings(): void
    {
        $this->actingAs($this->owner)->get('/member')->assertOk();
        $this->actingAs($this->owner)->get('/member/level')->assertOk();
        $this->actingAs($this->owner)->get('/member/follow-up')->assertOk();

        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $this->actingAs($gudang)->get('/member')->assertForbidden();
        $this->actingAs($gudang)->get('/member/'.$this->customer->id.'/sesuaikan')->assertForbidden();

        // Penyesuaian via HTTP oleh Owner.
        $this->actingAs($this->owner)
            ->post('/member/'.$this->customer->id.'/sesuaikan', ['points' => 25, 'reason' => 'promo'])
            ->assertRedirect();
        $this->assertSame(25, app(PointsService::class)->balance($this->customer));

        // Pengaturan poin via HTTP mengubah tarif perolehan.
        $this->actingAs($this->owner)
            ->put('/member/pengaturan-poin', ['earn_amount' => 5000, 'redeem_value' => 100, 'expiry_months' => 12])
            ->assertRedirect();
        $this->assertSame('5000', (string) Setting::value('points.earn_amount'));
        $this->exclusiveSale(); // basis 500.000 / 5.000 = 100 poin
        $this->assertSame(125, app(PointsService::class)->balance($this->customer));
        $this->assertLiabilityMatchesPoints();
    }
}
