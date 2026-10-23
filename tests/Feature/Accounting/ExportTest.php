<?php

namespace Tests\Feature\Accounting;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Accounting\Models\Payroll;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ekspor Excel Payroll, Komisi, dan Jurnal (audit 23 Okt 2026 B6) —
 * mengikuti pola ekspor Insentif (XlsxWriter, unduhan streaming).
 */
class ExportTest extends TestCase
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

    private function assertXlsx($response): void
    {
        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_payroll_commission_journal_exports_download(): void
    {
        Payroll::create([
            'employee_user_id' => $this->owner->id, 'period' => '2026-10',
            'base_amount' => '3000000', 'allowance_amount' => '250000', 'deduction_amount' => '0',
            'net_amount' => '3250000', 'created_by' => $this->owner->id,
        ]);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '100000']);
        app(InventoryService::class)->recordMovement(
            $this->owner, $frame, $this->branch, 'initial_stock', 5, 'test_seed', 1,
        );
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => '2026-10-05', 'tax_mode' => 'inclusive', 'branch_id' => $this->branch->id,
        ], [['product_id' => $frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
        Commission::create([
            'type' => 'doctor', 'source_sale_id' => $sale->id, 'base_amount' => '100000', 'rate' => '10.00',
            'amount' => '10000', 'period' => '2026-10', 'status' => 'accrued',
        ]);
        app(PostingService::class)->post('manual', null, '2026-10-05', [
            ['account' => '1-1100', 'branch_id' => $this->branch->id, 'debit' => '1000', 'credit' => '0'],
            ['account' => '4-1100', 'branch_id' => $this->branch->id, 'debit' => '0', 'credit' => '1000'],
        ], 'Jurnal ekspor uji', $this->owner);

        $this->assertXlsx($this->actingAs($this->owner)->get(route('payroll.export', ['periode' => '2026-10'])));
        $this->assertXlsx($this->actingAs($this->owner)->get(route('commissions.export', ['periode' => '2026-10'])));
        $this->assertXlsx($this->actingAs($this->owner)->get(route('journals.export', ['periode' => '2026-10'])));
    }

    public function test_exports_require_permissions(): void
    {
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branch->id]);

        $this->actingAs($kasir)->get(route('payroll.export'))->assertForbidden();
        $this->actingAs($kasir)->get(route('commissions.export'))->assertForbidden();
        $this->actingAs($kasir)->get(route('journals.export'))->assertForbidden();
    }
}
