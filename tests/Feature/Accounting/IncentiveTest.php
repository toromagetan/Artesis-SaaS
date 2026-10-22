<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\IncentiveAdjustment;
use App\Modules\Accounting\Models\IncentiveItem;
use App\Modules\Accounting\Models\IncentivePeriod;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\IncentiveService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\WarrantyCase;
use App\Modules\Sales\Services\SaleReturnService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Insentif berkala — mesin Artesis: hanya penjualan LUNAS oleh
 * kasir berperan Cabang/Admin; baris per item untuk penjual DAN
 * kepala optik (Admin tunggal cabang, fail-closed); basis
 * line_total item; retur pro-rata / batal; approve menolak
 * baris tanpa rule; TANPA jurnal; clawback retur otomatis ke
 * periode berikutnya sesudah periode dibekukan.
 */
class IncentiveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private User $seller;

    private User $admin;

    private Product $frame;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        Setting::set('tax.rate_default', '0');

        $this->seller = $this->makeUser('penjual.cabang', 'Cabang');
        $this->admin = $this->makeUser('kepala.optik', 'Admin');

        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '100000']);
        app(InventoryService::class)->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 50, 'test', 1);
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
    }

    private function makeUser(string $username, string $role): User
    {
        $user = User::factory()->create(['username' => $username]);
        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);
        $user->branches()->sync([$this->branch->id]);

        return $user;
    }

    private function rule(string $role, string $type, string $value, ?int $productId = null): void
    {
        app(IncentiveService::class)->createRule($this->owner, [
            'name' => "Rule {$role}", 'recipient_role' => $role, 'product_id' => $productId,
            'calculation_type' => $type, 'value' => $value,
            'effective_from' => '2026-01-01', 'effective_to' => null, 'is_active' => true,
        ]);
    }

    private function defaultRules(): void
    {
        $this->rule('Cabang', 'percent', '5');
        $this->rule('Admin', 'percent', '2');
    }

    private function sell(User $actor, string $date, int $qty, bool $paid = true): Sale
    {
        return app(SalesService::class)->createSale($actor, [
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
            'sale_date' => $date, 'tax_mode' => 'inclusive',
        ], [['product_id' => $this->frame->id, 'qty' => $qty, 'unit_price' => '100000']],
            $paid ? [['method' => 'cash', 'amount' => (string) (100000 * $qty)]] : []);
    }

    public function test_generate_builds_seller_and_admin_rows_only_for_paid_eligible_sales(): void
    {
        $this->defaultRules();
        $this->sell($this->seller, '2026-09-05', 2);   // A: 200rb
        $this->sell($this->seller, '2026-09-20', 1);   // B: 100rb
        $kasir = $this->makeUser('kasir.biasa', 'Kasir');
        $this->sell($kasir, '2026-09-10', 1);          // peran tak layak → dilewati
        $this->sell($this->seller, '2026-09-15', 1, paid: false); // belum lunas → dilewati

        $service = app(IncentiveService::class);
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);

        $this->assertSame('300000.00', (string) $period->transaction_total);
        // Penjual: 5% × (200rb + 100rb) = 15rb; kepala optik: 2% × 300rb = 6rb.
        $this->assertSame('21000.00', (string) $period->incentive_total);
        $this->assertSame(4, $period->items()->count());
        $this->assertSame(2, $period->items()->where('recipient_user_id', $this->seller->id)->count());
        $this->assertSame(2, $period->items()->where('recipient_user_id', $this->admin->id)->count());
        $this->assertTrue($period->items()->get()->every(fn ($i) => $i->status === 'eligible'));

        // Generate ulang bulan sama → periode sama, hasil identik (idempoten).
        $again = $service->generate($this->owner, $this->branch->id, '2026-09', null);
        $this->assertSame($period->id, $again->id);
        $this->assertSame(4, $again->items()->count());
    }

    public function test_product_override_rule_wins_over_generic(): void
    {
        $this->defaultRules();
        $this->rule('Cabang', 'flat', '7000', $this->frame->id);
        $this->sell($this->seller, '2026-09-05', 2);

        $period = app(IncentiveService::class)->generate($this->owner, $this->branch->id, '2026-09', null);
        $sellerRow = $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail();
        // Override flat per unit menang atas persen kolektif: 7000 × 2.
        $this->assertSame('14000.00', (string) $sellerRow->incentive_amount);
        $this->assertSame('flat', $sellerRow->calculation_type);
    }

    public function test_missing_rule_cancels_rows_and_blocks_approve_until_rule_exists(): void
    {
        $this->rule('Admin', 'percent', '2'); // rule Cabang sengaja tidak ada
        $this->sell($this->seller, '2026-09-05', 1);

        $service = app(IncentiveService::class);
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);
        $sellerRow = $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail();
        $this->assertSame('cancelled', $sellerRow->status);
        $this->assertSame(IncentiveService::REASON_NO_RULE, $sellerRow->cancellation_reason);

        try {
            $service->approve($this->owner, $period->fresh());
            $this->fail('Approve seharusnya menolak periode dengan baris tanpa rule.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }

        // Rule dibuat → approve menghitung ulang lalu lolos; TANPA jurnal.
        $this->rule('Cabang', 'percent', '5');
        $approved = $service->approve($this->owner, $period->fresh());
        $this->assertSame('approved', $approved->status);
        $this->assertSame(0, Journal::whereIn('source_type', ['incentive_period', 'incentive_payment'])->count());

        $paid = $service->markPaid($this->owner, $approved);
        $this->assertSame('paid', $paid->status);
        $this->assertSame(0, Journal::whereIn('source_type', ['incentive_period', 'incentive_payment'])->count());
    }

    public function test_admin_resolution_is_fail_closed(): void
    {
        $this->defaultRules();
        $this->sell($this->seller, '2026-09-05', 1);
        $service = app(IncentiveService::class);

        // Dua Admin aktif di cabang → generate gagal total.
        $admin2 = $this->makeUser('kepala.optik.dua', 'Admin');
        try {
            $service->generate($this->owner, $this->branch->id, '2026-09', null);
            $this->fail('Dua Admin aktif seharusnya menggagalkan generate.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('incentive', $e->errors());
        }
        // Seluruh generate ter-rollback: tidak ada periode/item bocor.
        $this->assertSame(0, IncentivePeriod::count());
        $this->assertSame(0, IncentiveItem::count());

        // Nol Admin aktif → juga gagal.
        $admin2->update(['is_active' => false]);
        $this->admin->update(['is_active' => false]);
        try {
            $service->generate($this->owner, $this->branch->id, '2026-09', null);
            $this->fail('Tanpa Admin aktif seharusnya menggagalkan generate.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('incentive', $e->errors());
        }
    }

    public function test_returns_shrink_partial_and_cancel_full_on_regenerate(): void
    {
        $this->defaultRules();
        $sale = $this->sell($this->seller, '2026-09-05', 2);
        $item = $sale->items()->firstOrFail();
        $service = app(IncentiveService::class);
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);
        $this->assertSame('10000.00', (string) $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail()->incentive_amount);

        // Retur 1 dari 2 (kredit toko: paid tidak berkurang, penjualan
        // tetap Lunas) → basis menyusut pro-rata pada hitung ulang.
        app(SaleReturnService::class)->create($this->owner, $sale->fresh(), [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Sebagian kembali', 'store_credit');
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);
        $row = $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail();
        $this->assertSame(1, $row->quantity);
        $this->assertSame('100000.00', (string) $row->item_final_amount);
        $this->assertSame('5000.00', (string) $row->incentive_amount);

        // Retur sisanya → baris penjual cancelled, baris admin hilang.
        app(SaleReturnService::class)->create($this->owner, $sale->fresh(), [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Sisanya kembali', 'store_credit');
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);
        $row = $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail();
        $this->assertSame('cancelled', $row->status);
        $this->assertSame(IncentiveService::REASON_RETURN, $row->cancellation_reason);
        $this->assertSame(0, $period->items()->where('recipient_user_id', $this->admin->id)->count());
    }

    public function test_warranty_case_cancels_seller_rows_without_admin_rows(): void
    {
        $this->defaultRules();
        $sale = $this->sell($this->seller, '2026-09-05', 1);
        WarrantyCase::create([
            'number' => 'WR-202609-0001', 'sale_id' => $sale->id,
            'sale_item_id' => $sale->items()->firstOrFail()->id,
            'branch_id' => $this->branch->id, 'status' => 'open',
            'reason' => 'Klaim garansi', 'created_by' => $this->owner->id,
        ]);

        $period = app(IncentiveService::class)->generate($this->owner, $this->branch->id, '2026-09', null);
        $row = $period->items()->where('recipient_user_id', $this->seller->id)->firstOrFail();
        $this->assertSame('cancelled', $row->status);
        $this->assertSame(IncentiveService::REASON_WARRANTY, $row->cancellation_reason);
        $this->assertSame(0, $period->items()->where('recipient_user_id', $this->admin->id)->count());
    }

    public function test_return_after_approval_creates_clawback_in_next_period(): void
    {
        $this->defaultRules();
        $sale = $this->sell($this->seller, '2026-09-05', 2);
        $item = $sale->items()->firstOrFail();
        $service = app(IncentiveService::class);
        $period = $service->generate($this->owner, $this->branch->id, '2026-09', null);

        // Retur SEBELUM dibekukan: tidak ada penyesuaian (draft
        // cukup dihitung ulang).
        app(SaleReturnService::class)->create($this->owner, $sale->fresh(), [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Retur awal', 'store_credit');
        $this->assertSame(0, IncentiveAdjustment::count());

        // Bekukan September (hitung ulang di dalam approve memakai
        // basis sisa qty 1: penjual 5000, admin 2000).
        $approved = $service->approve($this->owner, $period->fresh());
        $this->assertSame('7000.00', (string) $approved->incentive_total);

        // Retur 1 unit lagi di Oktober → clawback pro-rata dari
        // snapshot beku (qty historis 1) mendarat di periode
        // Oktober: penjual −5000, admin −2000.
        app(SaleReturnService::class)->create($this->owner, $sale->fresh(), [
            ['sale_item_id' => $item->id, 'qty' => 1, 'condition' => 'resale'],
        ], 'Retur susulan', 'store_credit');

        $october = IncentivePeriod::where('scope_key', 'branch_'.$this->branch->id)
            ->where('period_start', '2026-10-01')->firstOrFail();
        $this->assertSame('draft', $october->status);
        $this->assertSame(2, $october->adjustments()->count());
        // Clawback berjejak audit per penyesuaian (audit 23 Okt 2026 A4).
        $this->assertSame(2, AuditLog::where('action', 'accounting.incentive.adjustment')->count());
        $this->assertSame('-7000.00', $october->adjustmentsTotal()->toDecimalString());
        $this->assertSame('-7000.00', $october->netTotal()->toDecimalString());
        // Item historis September tidak berubah.
        $this->assertSame('7000.00', (string) $approved->fresh()->incentive_total);
    }

    public function test_pages_and_export_are_gated_and_work(): void
    {
        $this->defaultRules();
        $this->sell($this->seller, '2026-09-05', 1);
        $period = app(IncentiveService::class)->generate($this->owner, $this->branch->id, '2026-09', null);

        $this->actingAs($this->owner)->get(route('incentives.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('incentives.show', $period))->assertOk()->assertSee($this->seller->name);
        $export = $this->actingAs($this->owner)->get(route('incentives.export', $period));
        $export->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $export->headers->get('content-type'));

        $kasir = $this->makeUser('kasir.lihat', 'Kasir');
        $this->actingAs($kasir)->get(route('incentives.index'))->assertForbidden();
    }
}
