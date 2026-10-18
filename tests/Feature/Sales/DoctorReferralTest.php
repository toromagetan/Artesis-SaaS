<?php

namespace Tests\Feature\Sales;

use App\Modules\Accounting\Models\Commission;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Doctor;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F3.3 Dokter perujuk di formulir transaksi: rantai ujung-ke-ujung
 * lewat HTTP — kasir memilih dokter di formulir penjualan, komisi
 * terakru otomatis (mesin F1.7), dan void menulis baris koreksi
 * penyeimbang. Perilaku service-nya sendiri teruji di
 * PayrollCommissionTest; di sini yang dibuktikan adalah pemakaian
 * dokter dari formulir transaksi nyata.
 */
class DoctorReferralTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private Customer $customer;

    private Product $frame;

    private Doctor $doctor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$branch->id]);

        $this->customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '500000']);
        $this->doctor = Doctor::create(['name' => 'dr. Maya Kirana, Sp.M', 'commission_rate' => '10', 'is_active' => true]);
        app(InventoryService::class)->recordMovement(
            $this->owner, $this->frame, $branch, 'initial_stock', 5, 'test_seed', 1,
        );
    }

    private function postSale(?int $doctorId): Sale
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-18',
            'tax_mode' => 'exclusive',
            'items' => [['product_id' => $this->frame->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '555000', 'reference' => '']],
        ];
        if ($doctorId !== null) {
            $payload['doctor_id'] = $doctorId;
        }
        $this->actingAs($this->kasir)->post(route('sales.store'), $payload)
            ->assertRedirect();

        return Sale::latest('id')->firstOrFail();
    }

    public function test_doctor_picked_in_form_accrues_commission(): void
    {
        $sale = $this->postSale($this->doctor->id);

        $this->assertSame($this->doctor->id, $sale->doctor_id);
        $row = Commission::where('source_sale_id', $sale->id)->first();
        $this->assertNotNull($row, 'penjualan ber-dokter dari formulir harus mengakru komisi');
        $this->assertSame('doctor', $row->type);
        $this->assertSame($this->doctor->id, $row->beneficiary_doctor_id);
        $this->assertSame('500000.00', (string) $row->base_amount); // total 555rb − PPN eksklusif 55rb
        $this->assertSame('10.00', (string) $row->rate);
        $this->assertSame('50000.00', (string) $row->amount);
        $this->assertSame('2026-10', $row->period);
        $this->assertSame('accrued', $row->status);
    }

    public function test_sale_without_doctor_accrues_nothing(): void
    {
        $sale = $this->postSale(null);
        $this->assertNull($sale->doctor_id);
        $this->assertSame(0, Commission::where('source_sale_id', $sale->id)->count());
    }

    public function test_void_writes_balancing_correction(): void
    {
        $sale = $this->postSale($this->doctor->id);
        $original = Commission::where('source_sale_id', $sale->id)->firstOrFail();

        $this->actingAs($this->owner)->post(route('sales.void.store', $sale), [
            'reason' => 'pelanggan batal',
        ])->assertRedirect(route('sales.show', $sale));

        $correction = Commission::where('corrects_id', $original->id)->first();
        $this->assertNotNull($correction, 'void harus menulis baris koreksi komisi');
        $this->assertSame('-50000.00', (string) $correction->amount);
        $this->assertSame('corrected', $correction->status);
        $net = Commission::where('source_sale_id', $sale->id)->sum('amount');
        $this->assertSame('0.00', number_format((float) $net, 2, '.', ''));
    }
}
