<?php

namespace Tests\Feature\Inventory;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockOpname;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\OpnameService;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F4.1 Opname terjadwal: siklus penuh jadwal → snapshot → hitung →
 * review → approval (gerakan selisih + jurnal Beban Selisih
 * Opname), kunci mutasi cabang selama counting, pembatalan hanya
 * Owner, dan pagar izin/cabang di HTTP.
 */
class OpnameTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $gudang;

    private User $kasir;

    private Branch $branch;

    private Product $frame;

    private Product $lens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->admin = $this->userWithRole('Admin');
        $this->gudang = $this->userWithRole('Gudang');
        $this->kasir = $this->userWithRole('Kasir');

        $this->frame = Product::factory()->ofType('frame')->create([
            'sku' => 'FR-OPN-1', 'cost_price' => '50000',
        ]);
        $this->lens = Product::factory()->ofType('lens')->create([
            'sku' => 'LN-OPN-1', 'cost_price' => '10000',
        ]);

        $inventory = app(InventoryService::class);
        $inventory->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 10, 'test', 1);
        $inventory->recordMovement($this->owner, $this->lens, $this->branch, 'initial_stock', 5, 'test', 1);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);
        $user->branches()->sync([$this->branch->id]);

        return $user;
    }

    public function test_full_cycle_posts_difference_movements_and_journal(): void
    {
        $service = app(OpnameService::class);
        $opname = $service->schedule($this->gudang, $this->branch->id, now()->toDateString());
        $this->assertSame('scheduled', $opname->status);

        $opname = $service->startCounting($this->gudang, $opname);
        $this->assertSame('counting', $opname->status);
        $items = $opname->items()->get()->keyBy('product_id');
        $this->assertSame(10, $items[$this->frame->id]->system_qty);
        $this->assertSame(5, $items[$this->lens->id]->system_qty);

        // Review ditolak selama ada baris belum terhitung.
        $service->saveCounts($this->gudang, $opname, [$items[$this->frame->id]->id => 8]);
        try {
            $service->submitReview($this->gudang, $opname);
            $this->fail('Review seharusnya ditolak: satu baris belum terhitung.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $service->saveCounts($this->gudang, $opname, [$items[$this->lens->id]->id => 7]);
        $opname = $service->submitReview($this->gudang, $opname);
        $this->assertSame('review', $opname->status);

        $opname = $service->approve($this->admin, $opname);
        $this->assertSame('approved', $opname->status);
        $this->assertSame($this->admin->id, (int) $opname->approved_by);

        $movements = StockMovement::withoutGlobalScopes()
            ->where('reason', 'opname')
            ->where('reference_type', 'stock_opname')
            ->where('reference_id', $opname->id)
            ->get()->keyBy('product_id');
        $this->assertCount(2, $movements);
        $this->assertSame(-2, $movements[$this->frame->id]->qty);
        $this->assertSame(2, $movements[$this->lens->id]->qty);

        $inventory = app(InventoryService::class);
        $this->assertSame(8, $inventory->currentStock($this->frame->id, $this->branch->id));
        $this->assertSame(7, $inventory->currentStock($this->lens->id, $this->branch->id));

        // Neto: −2 × 50.000 + 2 × 10.000 = −80.000 →
        // Dr Beban Selisih Opname 80.000 / Cr Persediaan 80.000.
        $journal = Journal::where('source_type', 'stock_opname')->where('source_id', $opname->id)->firstOrFail();
        $lines = $journal->lines()->with('account')->get()
            ->mapWithKeys(fn ($line) => [$line->account->code => $line]);
        $this->assertSame('80000.00', (string) $lines['5-1200']->debit);
        $this->assertSame('80000.00', (string) $lines['1-1400']->credit);
    }

    public function test_branch_is_locked_for_non_owner_during_counting(): void
    {
        $service = app(OpnameService::class);
        $opname = $service->schedule($this->gudang, $this->branch->id, now()->toDateString());
        $service->startCounting($this->gudang, $opname);

        $inventory = app(InventoryService::class);
        try {
            $inventory->recordMovement($this->gudang, $this->frame, $this->branch, 'correction', -1, 'test', 2);
            $this->fail('Mutasi non-Owner seharusnya terkunci selama opname berjalan.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // Owner tetap boleh bermutasi (pagar PRD 7.4).
        $movement = $inventory->recordMovement($this->owner, $this->frame, $this->branch, 'correction', -1, 'test', 2);
        $this->assertSame(9, $movement->balance_after);
    }

    public function test_cancel_only_by_owner_and_only_from_counting(): void
    {
        $service = app(OpnameService::class);
        $opname = $service->schedule($this->gudang, $this->branch->id, now()->toDateString());
        $service->startCounting($this->gudang, $opname);

        try {
            $service->cancel($this->admin, $opname);
            $this->fail('Pembatalan oleh Admin seharusnya ditolak (Owner saja).');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $opname = $service->cancel($this->owner, $opname);
        $this->assertSame('cancelled', $opname->status);
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->where('reason', 'opname')->count());
    }

    public function test_second_active_opname_for_same_branch_rejected(): void
    {
        $service = app(OpnameService::class);
        $first = $service->schedule($this->gudang, $this->branch->id, now()->toDateString());
        $service->startCounting($this->gudang, $first);

        $this->expectException(ValidationException::class);
        $service->schedule($this->gudang, $this->branch->id, now()->addDay()->toDateString());
    }

    public function test_http_gate_and_flow(): void
    {
        $this->actingAs($this->kasir)->get(route('opnames.index'))->assertForbidden();

        $this->actingAs($this->gudang)->get(route('opnames.index'))->assertOk();
        $response = $this->actingAs($this->gudang)->post(route('opnames.store'), [
            'branch_id' => $this->branch->id,
            'scheduled_date' => now()->toDateString(),
        ]);
        $opname = StockOpname::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect(route('opnames.show', $opname));

        $this->actingAs($this->gudang)->get(route('opnames.show', $opname))->assertOk();
        $this->actingAs($this->gudang)
            ->post(route('opnames.start', $opname))
            ->assertRedirect();
        $this->assertSame('counting', $opname->fresh()->status);
    }
}
