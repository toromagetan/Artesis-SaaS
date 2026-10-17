<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Inventory\Services\TransferService;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M.7 Transfer distribusi (D.8 + E.3 + PRD 7.3): sisa dari PO =
 * diterima − terkirim; stok berpindah saat received; terima dua kali
 * ditolak; batal mengembalikan stok asal; tujuan sama ditolak.
 */
class TransferTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $pusat;

    private Branch $cabangB;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $this->cabangB = Branch::create(['name' => 'Cabang B']);
        $this->frame = Product::factory()->ofType('frame')->create(['cost_price' => '100000']);
    }

    private function stock(): InventoryService
    {
        return app(InventoryService::class);
    }

    /** PO ke Pusat, terkirim, diterima penuh $qty unit. */
    private function receivedPo(int $qty): PurchaseOrder
    {
        $supplier = Supplier::create(['name' => 'PT Transfer Uji', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->pusat->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_cost' => '100000'],
        ]);
        $orders->markSent($this->owner, $po);
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty],
        ]);

        return $po->refresh();
    }

    public function test_po_distribution_flow_and_remainder(): void
    {
        $po = $this->receivedPo(10);
        $service = app(TransferService::class);
        $this->assertSame(10, $service->distributableFromPo($po)[$this->frame->id]);

        $transfer = $service->sendFromPo($this->owner, $po, $this->cabangB->id, [
            ['product_id' => $this->frame->id, 'qty' => 4],
        ]);
        $this->assertMatchesRegularExpression('/^TRF-\d{6}-0001$/', $transfer->number);
        // Asal berkurang saat sent; tujuan BELUM bertambah (dalam perjalanan).
        $this->assertSame(6, $this->stock()->currentStock($this->frame->id, $this->pusat->id));
        $this->assertSame(0, $this->stock()->currentStock($this->frame->id, $this->cabangB->id));
        $this->assertSame(6, $service->distributableFromPo($po->refresh())[$this->frame->id]);

        // Melebihi sisa distribusi ditolak.
        try {
            $service->sendFromPo($this->owner, $po->refresh(), $this->cabangB->id, [
                ['product_id' => $this->frame->id, 'qty' => 7],
            ]);
            $this->fail('Transfer melebihi sisa PO harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(1, StockTransfer::count());
        }

        $service->receive($this->owner, $transfer);
        $this->assertSame('received', $transfer->refresh()->status);
        $this->assertSame(4, $this->stock()->currentStock($this->frame->id, $this->cabangB->id));

        // Terima dua kali ditolak (idempotensi).
        $this->expectException(ValidationException::class);
        $service->receive($this->owner, $transfer->refresh());
    }

    public function test_cancel_returns_origin_stock_and_locks_after_receive(): void
    {
        $this->stock()->recordMovement($this->owner, $this->frame, $this->pusat, 'initial_stock', 5, 'test_seed', 1);
        $service = app(TransferService::class);

        $transfer = $service->sendFromBranchStock($this->owner, $this->pusat->id, $this->cabangB->id, [
            ['product_id' => $this->frame->id, 'qty' => 2],
        ]);
        $this->assertSame(3, $this->stock()->currentStock($this->frame->id, $this->pusat->id));

        $service->cancel($this->owner, $transfer);
        $this->assertSame('cancelled', $transfer->refresh()->status);
        $this->assertSame(5, $this->stock()->currentStock($this->frame->id, $this->pusat->id));
        // Transfer batal tidak mengurangi sisa distribusi/saldo mana pun lagi.
        $this->assertSame(0, $this->stock()->currentStock($this->frame->id, $this->cabangB->id));

        // Sesudah diterima, batal ditolak.
        $transfer2 = $service->sendFromBranchStock($this->owner, $this->pusat->id, $this->cabangB->id, [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);
        $service->receive($this->owner, $transfer2);
        $this->expectException(ValidationException::class);
        $service->cancel($this->owner, $transfer2->refresh());
    }

    public function test_same_branch_and_stock_limits_rejected(): void
    {
        $service = app(TransferService::class);
        try {
            $service->sendFromBranchStock($this->owner, $this->pusat->id, $this->pusat->id, [
                ['product_id' => $this->frame->id, 'qty' => 1],
            ]);
            $this->fail('Transfer ke cabang yang sama harus ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, StockTransfer::count());
        }

        // Stok cabang bebas: tidak boleh melebihi saldo (semua tipe).
        $this->expectException(ValidationException::class);
        $service->sendFromBranchStock($this->owner, $this->pusat->id, $this->cabangB->id, [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ]);
    }

    public function test_http_receive_requires_destination_branch_access(): void
    {
        $po = $this->receivedPo(3);
        $transfer = app(TransferService::class)->sendFromPo($this->owner, $po, $this->cabangB->id, [
            ['product_id' => $this->frame->id, 'qty' => 3],
        ]);

        // Matriks nyata: Kasir tidak memegang stock.* sama sekali → 403.
        $kasirA = User::factory()->create();
        $kasirA->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $kasirA->branches()->sync([$this->pusat->id]);
        $this->actingAs($kasirA)->get('/transfer/'.$transfer->id)->assertForbidden();

        // Gudang cabang ASAL: dapat melihat (asal terlihat) tetapi aksi
        // terima DITOLAK pagar cabang di service (bukan cabang tujuan).
        $gudangA = User::factory()->create();
        $gudangA->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $gudangA->branches()->sync([$this->pusat->id]);
        $this->actingAs($gudangA)->get('/transfer/'.$transfer->id)->assertOk();
        $this->actingAs($gudangA)->post('/transfer/'.$transfer->id.'/terima')->assertForbidden();
        $this->assertSame('sent', $transfer->refresh()->status);

        // Gudang cabang B menerima.
        $gudangB = User::factory()->create();
        $gudangB->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $gudangB->branches()->sync([$this->cabangB->id]);
        $this->actingAs($gudangB)->post('/transfer/'.$transfer->id.'/terima')->assertRedirect();
        $this->assertSame('received', $transfer->refresh()->status);
        $this->assertSame(3, $this->stock()->currentStock($this->frame->id, $this->cabangB->id));

        // Pengguna cabang ketiga yang tak terkait: 404.
        $branchC = Branch::create(['name' => 'Cabang C']);
        $asing = User::factory()->create();
        $asing->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $asing->branches()->sync([$branchC->id]);
        $this->actingAs($asing)->get('/transfer/'.$transfer->id)->assertNotFound();
    }
}
