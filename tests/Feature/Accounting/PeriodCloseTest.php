<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Journal;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F1.5 Tutup buku: UI kunci/buka (Owner + alasan + audit untuk
 * buka) dan bukti gerbang Fase 1 — posting bertanggal periode
 * terkunci DITOLAK dari tiga modul berbeda (Penjualan, Pembelian,
 * Jurnal Manual) dan penulisan bisnisnya ikut tergulung.
 */
class PeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
    }

    public function test_lock_unlock_ui_audit_and_journal_flag(): void
    {
        // Jurnal di periode 2026-08 sebelum dikunci.
        $journal = app(PostingService::class)->post('manual', null, '2026-08-05', [
            ['account' => '1-1100', 'debit' => '100000'],
            ['account' => '3-1000', 'credit' => '100000'],
        ], 'sebelum kunci', $this->owner);
        $this->assertFalse((bool) $journal->period_locked);

        $this->actingAs($this->owner)->get(route('periods.index'))->assertOk();
        $this->actingAs($this->owner)->post(route('periods.lock'), ['period' => '2026-08'])
            ->assertRedirect(route('periods.index'));

        $this->assertTrue(app(PostingService::class)->isPeriodLocked('2026-08'));
        $this->assertTrue((bool) $journal->fresh()->period_locked);
        $this->assertDatabaseHas('audit_logs', ['action' => 'periods.lock']);

        // Buka tanpa alasan → ditolak; dengan alasan → berhasil + audit.
        $this->actingAs($this->owner)->post(route('periods.unlock'), ['period' => '2026-08'])
            ->assertSessionHasErrors('reason');
        $this->assertTrue(app(PostingService::class)->isPeriodLocked('2026-08'));

        $this->actingAs($this->owner)->post(route('periods.unlock'), [
            'period' => '2026-08', 'reason' => 'Koreksi jurnal modal',
        ])->assertRedirect(route('periods.index'));
        $this->assertFalse(app(PostingService::class)->isPeriodLocked('2026-08'));
        $this->assertFalse((bool) $journal->fresh()->period_locked);
        $this->assertDatabaseHas('audit_logs', ['action' => 'periods.unlock']);
        $this->assertSame('Koreksi jurnal modal', DB::table('period_locks')->where('period', '2026-08')->value('unlock_reason'));
    }

    public function test_unlock_requires_owner_role_beyond_permission(): void
    {
        app(PostingService::class)->lockPeriod($this->owner, '2026-08');

        // Peran khusus BERIZIN periods.unlock tetapi bukan Owner → 403.
        $role = Role::create(['name' => 'Auditor', 'label' => 'Auditor']);
        $role->permissions()->sync(Permission::whereIn('name', ['periods.unlock', 'dashboard.view'])->pluck('id'));
        $auditor = User::create(['name' => 'Auditor', 'username' => 'auditor-f15', 'password' => bcrypt('password'), 'is_active' => true]);
        $auditor->roles()->attach($role);
        $auditor->branches()->attach($this->branch);

        $this->actingAs($auditor)->post(route('periods.unlock'), [
            'period' => '2026-08', 'reason' => 'coba buka',
        ])->assertForbidden();
        $this->assertTrue(app(PostingService::class)->isPeriodLocked('2026-08'));

        // Admin bahkan tidak memegang izinnya.
        $admin = User::create(['name' => 'Admin', 'username' => 'admin-f15', 'password' => bcrypt('password'), 'is_active' => true]);
        $admin->roles()->attach(Role::where('name', 'Admin')->firstOrFail());
        $admin->branches()->attach($this->branch);
        $this->actingAs($admin)->get(route('periods.index'))->assertForbidden();
        $this->actingAs($admin)->post(route('periods.lock'), ['period' => '2026-09'])->assertForbidden();
    }

    public function test_locked_period_rejects_postings_from_three_modules(): void
    {
        app(PostingService::class)->lockPeriod($this->owner, '2026-08');
        $journalsBefore = Journal::count();

        // (1) PENJUALAN: penjualan bertanggal 2026-08-15 harus gagal total.
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 5, 'test_seed', 1);
        try {
            app(SalesService::class)->createSale($this->owner, [
                'customer_id' => $customer->id, 'sale_date' => '2026-08-15', 'tax_mode' => 'exclusive',
            ], [['product_id' => $frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '555000']]);
            $this->fail('Penjualan ke periode terkunci harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
        $this->assertSame(0, Sale::count());

        // (2) PEMBELIAN: penerimaan bertanggal 2026-08-20 harus gagal total.
        $supplier = Supplier::create(['name' => 'PT Uji F15', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $frame->id, 'qty' => 3, 'unit_cost' => '400000'],
        ]);
        $orders->markSent($this->owner, $po);
        $item = $po->items->first();
        try {
            app(ReceiptService::class)->store($this->owner, $po, [
                ['purchase_order_item_id' => $item->id, 'qty' => 1],
            ], '2026-08-20');
            $this->fail('Penerimaan ke periode terkunci harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('period', $e->errors());
        }
        $this->assertSame(0, $item->refresh()->qty_received);
        $this->assertSame(5, app(InventoryService::class)->currentStock($frame->id, $this->branch->id));

        // (3) JURNAL MANUAL lewat HTTP.
        $kas = Account::where('code', '1-1100')->firstOrFail();
        $modal = Account::where('code', '3-1000')->firstOrFail();
        $this->actingAs($this->owner)->post(route('journals.store'), [
            'journal_date' => '2026-08-10',
            'lines' => [
                ['account_id' => $kas->id, 'debit' => '10000'],
                ['account_id' => $modal->id, 'credit' => '10000'],
            ],
        ])->assertSessionHasErrors('period');

        // Tidak satu pun jurnal baru tercipta dari ketiga percobaan.
        $this->assertSame($journalsBefore, Journal::count());
    }
}
