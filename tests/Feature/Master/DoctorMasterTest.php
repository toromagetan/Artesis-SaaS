<?php

namespace Tests\Feature\Master;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit 23 Okt 2026 (B1): master Dokter Perujuk kini terkelola penuh
 * (PRD-DETAIL §12.4) — CRUD + aktif/nonaktif berpagar doctors.manage,
 * detail halaman penuh dengan total rujukan & komisi.
 */
class DoctorMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $kasir;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::set('tax.rate_default', '0');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->admin = $this->userWithRole('Admin');
        $this->kasir = $this->userWithRole('Kasir');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);
        $user->branches()->sync([$this->branch->id]);

        return $user;
    }

    public function test_doctor_master_is_permission_gated(): void
    {
        $this->actingAs($this->kasir)->get(route('doctors.index'))->assertForbidden();
        $this->actingAs($this->kasir)->post(route('doctors.store'), [
            'name' => 'dr. Liar', 'commission_rate' => 5,
        ])->assertForbidden();

        $this->actingAs($this->admin)->get(route('doctors.index'))->assertOk();
    }

    public function test_admin_manages_doctor_lifecycle_with_audit(): void
    {
        $this->actingAs($this->admin)->post(route('doctors.store'), [
            'name' => 'dr. Sari Wulandari', 'clinic' => 'Klinik Mata Sehat',
            'phone' => '081234567890', 'commission_rate' => 7.5,
        ])->assertRedirect(route('doctors.index'));
        $doctor = Doctor::where('name', 'dr. Sari Wulandari')->firstOrFail();
        $this->assertTrue($doctor->is_active);
        $this->assertSame('7.50', (string) $doctor->commission_rate);
        $this->assertTrue(AuditLog::where('action', 'doctor.created')
            ->where('subject_id', $doctor->id)->exists());

        $this->actingAs($this->admin)->put(route('doctors.update', $doctor), [
            'name' => 'dr. Sari Wulandari, Sp.M', 'clinic' => 'Klinik Mata Sehat',
            'phone' => '081234567890', 'commission_rate' => 10,
        ])->assertRedirect(route('doctors.index'));
        $this->assertSame('10.00', (string) $doctor->fresh()->commission_rate);

        $this->actingAs($this->admin)->post(route('doctors.toggle-active', $doctor))
            ->assertRedirect();
        $this->assertFalse($doctor->fresh()->is_active);
    }

    public function test_doctor_detail_shows_referral_and_commission_totals(): void
    {
        $doctor = Doctor::create([
            'name' => 'dr. Rujukan Uji', 'clinic' => 'Praktik Bersama',
            'phone' => '081200000000', 'commission_rate' => 10, 'is_active' => true,
        ]);
        $supplier = Supplier::create(['name' => 'PT Uji', 'is_active' => true]);
        $product = Product::factory()->ofType('frame')->create([
            'sku' => 'DR-1', 'name' => 'Frame Uji Dokter', 'cost_price' => '40000', 'sale_price' => '100000',
        ]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $product->id, 'qty' => 3, 'unit_cost' => '40000'],
        ]);
        $orders->markSent($this->owner, $po);
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 3, 'unit_cost_actual' => '40000'],
        ]);
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => now()->toDateString(),
            'tax_mode' => 'exclusive', 'branch_id' => $this->branch->id,
            'doctor_id' => $doctor->id,
        ], [['product_id' => $product->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
        Commission::create([
            'type' => 'doctor', 'beneficiary_doctor_id' => $doctor->id,
            'source_sale_id' => $sale->id, 'base_amount' => '100000', 'rate' => '10.00',
            'amount' => '10000', 'period' => now()->format('Y-m'), 'status' => 'accrued',
        ]);

        $this->actingAs($this->admin)->get(route('doctors.show', $doctor))
            ->assertOk()
            ->assertSee('dr. Rujukan Uji')
            ->assertSee($sale->number)
            ->assertSee('10.000');
        $this->actingAs($this->admin)->get(route('doctors.index'))
            ->assertOk()
            ->assertSee('dr. Rujukan Uji');
    }
}
