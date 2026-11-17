<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit keamanan 17 Nov 2026, temuan F-2: halaman label stok sempat
 * memuat 50 penerimaan terakhir seluruh instalasi dan mencetak label
 * penerimaan by-id tanpa pagar. Receipt tidak ber-scope, jadi
 * LabelController wajib memagar eksplisit mengikuti cabang PO —
 * pola yang sama dengan ReceiptController::assertVisible().
 */
class LabelReceiptPerimeterTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, AccountingSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantOwner(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $owner = User::create([
            'email_verified_at' => now(), 'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner, $branch];
    }

    private function receiptWithItem(User $owner, Branch $branch, string $sku): Receipt
    {
        $supplier = Supplier::forceCreate([
            'name' => 'Supplier '.$sku, 'tenant_id' => $owner->tenant_id, 'is_active' => true,
        ]);
        $master = Product::factory()->forType('frame')->create(['name' => 'Frame '.$sku, 'tenant_id' => $owner->tenant_id]);
        $detail = FrameDetail::factory()->create([
            'product_id' => $master->id, 'sku' => $sku, 'sale_price' => '100000', 'tenant_id' => $owner->tenant_id,
        ]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($owner, $supplier->id, $branch->id, [
            ['detail_type' => 'frame', 'detail_id' => $detail->id, 'qty' => 2, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($owner, $po);

        return app(ReceiptService::class)->store($owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 2, 'unit_cost_actual' => '50000'],
        ]);
    }

    public function test_label_index_lists_only_accessible_branch_receipts(): void
    {
        [, $ownerA, $branchA] = $this->tenantOwner('lia');
        [, $ownerB, $branchB] = $this->tenantOwner('lib');
        $receiptA = $this->receiptWithItem($ownerA, $branchA, 'FR-LBL-A');
        $receiptB = $this->receiptWithItem($ownerB, $branchB, 'FR-LBL-B');

        $response = $this->actingAs($ownerA)->get(route('labels.index'));
        $response->assertOk();
        $response->assertSee($receiptA->number);
        $response->assertDontSee($receiptB->number);
    }

    public function test_label_print_rejects_other_tenant_receipt(): void
    {
        [, $ownerA] = $this->tenantOwner('lpa');
        [, $ownerB, $branchB] = $this->tenantOwner('lpb');
        $receiptB = $this->receiptWithItem($ownerB, $branchB, 'FR-LBL-C');

        // Kontrol: pemilik sah mencetak label penerimaannya sendiri.
        $own = $this->actingAs($ownerB)->post(route('labels.print'), [
            'mode' => 'receipt', 'receipt_id' => $receiptB->id, 'multiplier' => 1,
        ]);
        $own->assertOk();
        $own->assertSee('FR-LBL-C');

        $other = $this->actingAs($ownerA)->post(route('labels.print'), [
            'mode' => 'receipt', 'receipt_id' => $receiptB->id, 'multiplier' => 1,
        ]);
        $other->assertNotFound();
        $other->assertDontSee('FR-LBL-C');
    }

    public function test_label_print_rejects_other_branch_within_same_tenant(): void
    {
        [$tenant, $owner, $branch1] = $this->tenantOwner('lcb');
        $branch2 = Branch::create(['name' => 'Cabang Kedua', 'tenant_id' => $tenant->id]);
        $owner->branches()->attach($branch2);
        $receipt2 = $this->receiptWithItem($owner, $branch2, 'FR-LBL-D');

        // Staf hanya berwilayah cabang 1, hanya berizin stock.view.
        $role = Role::create(['name' => 'StokSatu', 'label' => 'Stok Satu', 'tenant_id' => $tenant->id]);
        $role->permissions()->attach(Permission::where('name', 'stock.view')->firstOrFail());
        $staff = User::create([
            'email_verified_at' => now(), 'name' => 'Staf Satu', 'username' => 'staf-lcb',
            'email' => 'staf-lcb@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $staff->roles()->attach($role);
        $staff->branches()->attach($branch1);

        $response = $this->actingAs($staff)->post(route('labels.print'), [
            'mode' => 'receipt', 'receipt_id' => $receipt2->id, 'multiplier' => 1,
        ]);
        $response->assertNotFound();
        $response->assertDontSee('FR-LBL-D');
    }
}
