<?php

namespace Tests\Feature\Purchasing;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\Receipt;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\PurchasePaymentService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Supplier (PRD-Detail 6.7 — modul yang sebelumnya hilang):
 * CRUD sepagar suppliers.manage + aktif/nonaktif tanpa hapus + audit,
 * daftar berisi jumlah PO & utang berjalan, detail berisi riwayat
 * PO/pembayaran — seluruh angka transaksional terpagar cabang aktor.
 */
class SupplierMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $kasir;

    private Branch $pusat;

    private Branch $lain;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $this->lain = Branch::create(['name' => 'Cabang Lain', 'is_active' => true]);
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '100000']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', 'admin')->firstOrFail()->id]);
        $this->admin->branches()->sync([$this->pusat->id]);

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->pusat->id]);
    }

    private function makeSupplier(string $name): Supplier
    {
        return Supplier::create(['name' => $name, 'is_active' => true]);
    }

    private function makeReceipt(User $actor, Supplier $supplier, Branch $branch, int $qty, string $unitCost): Receipt
    {
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($actor, $supplier->id, $branch->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_cost' => $unitCost],
        ]);
        $orders->markSent($actor, $po);

        return app(ReceiptService::class)->store($actor, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty],
        ], '2026-10-10');
    }

    public function test_guest_redirected_and_kasir_forbidden(): void
    {
        $supplier = $this->makeSupplier('PT Terlarang');

        $this->get(route('suppliers.index'))->assertRedirect(route('login'));
        $this->actingAs($this->kasir)->get(route('suppliers.index'))->assertForbidden();
        $this->actingAs($this->kasir)->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($this->kasir)->get(route('suppliers.show', $supplier))->assertForbidden();
        $this->actingAs($this->kasir)->post(route('suppliers.store'), ['name' => 'PT X'])->assertForbidden();
        $this->actingAs($this->kasir)->post(route('suppliers.toggle-active', $supplier))->assertForbidden();
        $this->assertSame(1, Supplier::count());
        $this->assertTrue($supplier->refresh()->is_active);
    }

    public function test_owner_full_crud_toggle_and_audit(): void
    {
        $response = $this->actingAs($this->owner)->post(route('suppliers.store'), [
            'name' => 'PT Optik Jaya',
            'contact_person' => 'Sari',
            'phone' => '0812-1111',
            'email' => 'order@optikjaya.test',
            'address' => 'Jl. Kenanga 1, Surabaya',
        ]);
        $supplier = Supplier::where('name', 'PT Optik Jaya')->firstOrFail();
        $response->assertRedirect(route('suppliers.index'));
        $this->assertTrue($supplier->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.created', 'subject_id' => $supplier->id]);

        $this->actingAs($this->owner)->put(route('suppliers.update', $supplier), [
            'name' => 'PT Optik Jaya Baru',
            'contact_person' => 'Sari',
            'phone' => '0812-2222',
            'email' => 'order@optikjaya.test',
            'address' => 'Jl. Kenanga 2, Surabaya',
        ])->assertRedirect(route('suppliers.index'));
        $this->assertSame('PT Optik Jaya Baru', $supplier->refresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.updated', 'subject_id' => $supplier->id]);

        $this->actingAs($this->owner)->post(route('suppliers.toggle-active', $supplier))
            ->assertRedirect(route('suppliers.index'));
        $this->assertFalse($supplier->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.toggled', 'subject_id' => $supplier->id]);

        // Tanpa hapus: tidak ada rute destroy untuk supplier.
        $this->actingAs($this->owner)->delete('/supplier/'.$supplier->id)->assertStatus(405);
        $this->assertSame(1, Supplier::count());
    }

    public function test_validation_rejects_missing_name_and_bad_email(): void
    {
        $this->actingAs($this->owner)->post(route('suppliers.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->actingAs($this->owner)->post(route('suppliers.store'), [
            'name' => 'PT Surel Rusak', 'email' => 'bukan-email',
        ])->assertSessionHasErrors('email');
        $this->assertSame(0, Supplier::count());
    }

    public function test_index_and_show_reflect_pos_payments_and_branch_fence(): void
    {
        $a = $this->makeSupplier('PT Sumber Lensa');
        $b = $this->makeSupplier('PT Jauh Cabang');

        // Supplier A di Pusat: terima 2 x 100.000 = 200.000, bayar 120.000 -> sisa 80.000.
        $receiptA = $this->makeReceipt($this->owner, $a, $this->pusat, 2, '100000');
        $payment = app(PurchasePaymentService::class)
            ->paySingle($this->owner, $receiptA, '120000', 'transfer', '2026-10-12');
        // Supplier B hanya bertransaksi di cabang lain.
        $receiptB = $this->makeReceipt($this->owner, $b, $this->lain, 1, '50000');
        $poB = $receiptB->purchaseOrder;

        // Owner (global): daftar memuat utang berjalan A; detail A memuat PO + pembayaran + sisa.
        $this->actingAs($this->owner)->get(route('suppliers.index'))
            ->assertOk()
            ->assertSee('PT Sumber Lensa')
            ->assertSee('Rp80.000,00');
        $showA = $this->actingAs($this->owner)->get(route('suppliers.show', $a))->assertOk();
        $showA->assertSee($receiptA->purchaseOrder->number)
            ->assertSee($payment->number)
            ->assertSee($receiptA->number)
            ->assertSee('Rp80.000,00');

        // Admin hanya mengelola Pusat: master B tetap terlihat (master global),
        // tetapi angka transaksional B terpagar — utang Rp0 dan PO B tidak tampil.
        $showB = $this->actingAs($this->admin)->get(route('suppliers.show', $b))->assertOk();
        $showB->assertSee('PT Jauh Cabang')
            ->assertSee('Rp0,00')
            ->assertDontSee($poB->number)
            ->assertDontSee($receiptB->number);

        // Admin melihat angka A seutuhnya karena A berada di cabang kelolaannya.
        $this->actingAs($this->admin)->get(route('suppliers.show', $a))
            ->assertOk()
            ->assertSee('Rp80.000,00');
    }
}
