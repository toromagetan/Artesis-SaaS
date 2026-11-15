<?php

namespace Tests\Feature\Documents;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unduh PDF dokumen (keputusan owner 15 Nov 2026): invoice, resep,
 * dan PO dapat diunduh sebagai berkas PDF — tampilan yang sama
 * dengan halaman cetak — untuk dilampirkan manual pada WhatsApp.
 * Gerbang izin sama dengan cetaknya; isolasi tenant berlaku.
 */
class DocumentPdfDownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'phone' => '081234567890']);
        $frame = FrameDetail::factory()->create(['sale_price' => '400000', 'cost_price' => '300000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 5, 'test_seed', 1);
        $this->sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '400000'],
        ]);
    }

    public function test_invoice_downloads_as_pdf(): void
    {
        $response = $this->actingAs($this->owner)->get(route('sales.download-invoice', $this->sale));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('invoice-'.$this->sale->number.'.pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_order_and_prescription_download_as_pdf(): void
    {
        $this->actingAs($this->owner)->get(route('sales.download-order', $this->sale))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $prescription = Prescription::factory()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branch->id,
        ]);
        $this->actingAs($this->owner)->get(route('prescriptions.download', $prescription))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_purchase_order_downloads_as_pdf(): void
    {
        $supplier = Supplier::create(['name' => 'PT Lensa Uji', 'phone' => '081155500011', 'is_active' => true]);
        $frame = FrameDetail::factory()->create(['cost_price' => '400000']);
        $po = app(PurchaseOrderService::class)->create($this->owner, $supplier->id, $this->branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $frame->id, 'qty' => 5, 'unit_cost' => '400000'],
        ]);

        $response = $this->actingAs($this->owner)->get(route('po.download', $po));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertDownload('po-'.$po->number.'.pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_download_requires_the_same_permission_as_print(): void
    {
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $gudang->branches()->sync([$this->branch->id]);

        $this->actingAs($gudang)->get(route('sales.download-invoice', $this->sale))
            ->assertForbidden();
    }

    public function test_tenant_cannot_download_another_tenants_invoice(): void
    {
        $this->seed(SaasSeeder::class);
        $platform = User::where('username', 'platform')->firstOrFail();
        $tenant = Tenant::create(['name' => 'Optik Lain', 'slug' => 'optik-lain', 'email' => 'lain@uji.id']);
        app(PlanService::class)->assignPlan($tenant, Plan::where('key', 'best_offer')->firstOrFail(), null, $platform);
        $branchB = Branch::create(['name' => 'Cabang Lain', 'tenant_id' => $tenant->id]);
        $adminB = User::create([
            'email_verified_at' => now(),
            'name' => 'Admin Lain', 'username' => 'admin-lain',
            'email' => 'admin-lain@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $adminB->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $adminB->branches()->attach($branchB);

        // Invoice milik instalasi mandiri (tenant_id null) tidak
        // terlihat oleh aktor tenant mana pun → 404.
        $this->actingAs($adminB)->get(route('sales.download-invoice', $this->sale))
            ->assertNotFound();
    }
}
