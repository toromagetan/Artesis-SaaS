<?php

namespace Tests\Feature\Security;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchasePayment;
use App\Modules\Purchasing\Services\CollectivePaymentService;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Modul perbaikan payments — cabang standalone dari pagar dokumen
 * pembayaran: pada instalasi tanpa tenant, aktor instalasi membuka
 * print + result pembayaran milik instalasinya sendiri, tetapi
 * tetap tidak dapat membuka pembayaran yang supplier-nya mengaku
 * milik tenant (tenant_id terisi). Melengkapi
 * PaymentAccessTenantIsolationTest yang membuktikan mode tenant.
 */
class PaymentStandaloneAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private PurchasePayment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $supplier = Supplier::create(['name' => 'PT Standalone Uji', 'is_active' => true]);
        $frame = Product::factory()->ofType('frame')->create(['cost_price' => '100000']);

        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $branch->id, [
            ['product_id' => $frame->id, 'qty' => 1, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($this->owner, $po);
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => 1],
        ], '2026-11-01');

        $this->payment = app(CollectivePaymentService::class)
            ->process($this->owner, $supplier, '100000', 'transfer');
    }

    public function test_standalone_opens_own_result_and_print(): void
    {
        $this->actingAs($this->owner)->get(route('payments.collective.result', $this->payment))
            ->assertOk();
        $this->actingAs($this->owner)->get(route('payments.print', $this->payment))
            ->assertOk()->assertSee($this->payment->number);
    }

    public function test_standalone_cannot_open_tenant_claimed_payment(): void
    {
        // Supplier mengaku milik tenant (tenant_id terisi, tanpa
        // baris tenant — instalasi tetap standalone) → pagar menolak.
        // Baris yatim seperti ini tidak dapat dibuat lewat alur
        // normal (FK suppliers.tenant_id), jadi fixture menonaktifkan
        // pemeriksaan FK sesaat khusus untuk menyimulasikannya.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $foreignSupplier = Supplier::create(['name' => 'Supplier Asing', 'is_active' => true, 'tenant_id' => 999]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $foreignPayment = PurchasePayment::create([
            'number' => 'PAY-ASING-1', 'supplier_id' => $foreignSupplier->id,
            'method' => 'transfer', 'amount' => '100000',
            'payment_date' => '2026-11-01', 'paid_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->get(route('payments.collective.result', $foreignPayment))
            ->assertNotFound();
        $this->actingAs($this->owner)->get(route('payments.print', $foreignPayment))
            ->assertNotFound();
    }
}
