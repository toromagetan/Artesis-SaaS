<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aksi simpan terminal POS (PRD-DETAIL §2.2; audit 23 Okt 2026 B5):
 * Simpan & Cetak membuka orderan tercetak dari halaman detail;
 * Simpan & Kirim WA memastikan invoice terantre TANPA kirim ganda
 * (otomasi SaleCompleted sudah mengantrekannya lebih dulu).
 */
class PosSaveActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $branch->id, 'phone' => '081234567890']);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '400000']);
        app(InventoryService::class)->recordMovement(
            $this->owner, $this->frame, $branch, 'initial_stock', 10, 'test_seed', 1,
        );
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-18',
            'tax_mode' => 'inclusive',
            'items' => [['product_id' => $this->frame->id, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => '400000', 'reference' => '']],
        ], $extra);
    }

    public function test_save_and_print_opens_the_print_order_from_detail(): void
    {
        $response = $this->actingAs($this->owner)->post(route('sales.store'), $this->payload(['intent' => 'print']));
        $sale = Sale::latest('id')->firstOrFail();
        $response->assertRedirect(route('sales.show', $sale));
        $response->assertSessionHas('open_print', route('sales.print-order', $sale));

        // Simpan biasa tidak membawa open_print.
        $plain = $this->actingAs($this->owner)->post(route('sales.store'), $this->payload());
        $plain->assertSessionMissing('open_print');
    }

    public function test_save_and_send_wa_queues_invoice_exactly_once(): void
    {
        WaTemplate::create([
            'key' => 'invoice', 'name' => 'Invoice', 'category' => 'utility',
            'body' => 'Invoice {nomor} total {total} di {cabang}.', 'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->post(route('sales.store'), $this->payload(['intent' => 'wa']));
        $response->assertRedirect();
        $sale = Sale::latest('id')->firstOrFail();

        // Otomasi SaleCompleted mengantrekan invoice; aksi WA kasir
        // TIDAK menggandakannya.
        $this->assertSame(1, WaMessage::where('template_key', 'invoice')->where('customer_id', $this->customer->id)->count());
        $this->assertStringContainsString($sale->number, WaMessage::first()->body);
    }
}
