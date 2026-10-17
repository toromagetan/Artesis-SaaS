<?php

namespace Tests\Feature\Platform;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Purchasing\Services\PurchaseRequestService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.11 Dashboard operasional: kartu per izin dengan angka terpagar
 * cabang; peran tanpa izin terkait tidak melihat kartunya.
 */
class DashboardTest extends TestCase
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
        Setting::where('key', 'tax.rate_default')->update(['value' => '0']);
    }

    public function test_dashboard_cards_show_scoped_numbers(): void
    {
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '50000', 'sale_price' => '100000']);
        $lens = Product::factory()->ofType('lens')->create(['cost_price' => '70000', 'sale_price' => '150000']);
        $stock = app(InventoryService::class);
        $stock->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 3, 'test_seed', 1); // menipis ≤5
        $stock->recordMovement($this->owner, $lens, $this->branch, 'initial_stock', 10, 'test_seed', 1);

        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id,
            'branch_id' => $this->branch->id,
            'sale_date' => now()->format('Y-m-d'),
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $lens->id, 'qty' => 1, 'unit_price' => '150000'],
        ], [['method' => 'cash', 'amount' => '100000']]); // piutang 50rb + proses lab waiting

        $pr = app(PurchaseRequestService::class)->create($this->owner, $this->branch->id, [
            ['product_id' => $frame->id, 'qty' => 2],
        ]);
        app(PurchaseRequestService::class)->submit($this->owner, $pr);

        $response = $this->actingAs($this->owner)->get('/dashboard');
        $response->assertOk();
        $response->assertSee('Rp150.000');       // penjualan hari ini
        $response->assertSee('Rp50.000');        // piutang beredar
        $response->assertSee(__('ui.dashboard.lab_queue'));
        $response->assertSee(__('ui.dashboard.pr_pending'));
    }

    public function test_dashboard_hides_cards_without_permission(): void
    {
        // Teknisi: hanya lab.* — tidak melihat kartu penjualan/utang/PR.
        $teknisi = User::factory()->create();
        $teknisi->roles()->sync([Role::where('name', 'teknisi')->firstOrFail()->id]);
        $teknisi->branches()->sync([$this->branch->id]);
        $response = $this->actingAs($teknisi)->get('/dashboard');
        $response->assertOk();
        $response->assertSee(__('ui.dashboard.lab_queue'));
        $response->assertDontSee(__('ui.dashboard.sales_today'));
        $response->assertDontSee(__('ui.dashboard.payables'));
        $response->assertDontSee(__('ui.dashboard.pr_pending'));

        // Refraksionis tanpa kartu apa pun → keadaan kosong yang sopan.
        $refraksionis = User::factory()->create();
        $refraksionis->roles()->sync([Role::where('name', 'refraksionis')->firstOrFail()->id]);
        $this->actingAs($refraksionis)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('ui.dashboard.empty'));
    }
}
