<?php

namespace Tests\Feature\Lab;

use App\Modules\Accounting\Models\Journal;
use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\LabOrder;
use App\Modules\Lab\Services\LabOrderService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F2.3 — Order lab: nomor LO berurutan, status HANYA maju
 * (waiting → ordered → received), dan saat received biaya lab
 * mengalir ke HPP lewat jurnal (Dr HPP / Cr Utang Supplier).
 */
class LabOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $lensRx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->lensRx = Product::factory()->ofType('rx')->create(['sale_price' => '300000']);
    }

    private function lensSale(): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id,
            'sale_date' => '2026-10-18',
            'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->lensRx->id, 'qty' => 1, 'prescription_ref' => 'pair'],
        ]);
    }

    public function test_create_numbering_and_forward_only_transitions(): void
    {
        $service = app(LabOrderService::class);
        $order = $service->create($this->owner, $this->lensSale(), 'Lab Sentral Jakarta', '50000');
        $this->assertMatchesRegularExpression('/^LO-\d{6}-\d{4}$/', $order->number);
        $this->assertSame('waiting', $order->status);
        $this->assertNull($order->sent_at);

        // Melompat langsung ke received → ditolak.
        try {
            $service->transition($this->owner, $order, 'received');
            $this->fail('Lompatan waiting → received seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        $ordered = $service->transition($this->owner, $order, 'ordered');
        $this->assertNotNull($ordered->sent_at);

        // Mundur → ditolak.
        try {
            $service->transition($this->owner, $order, 'waiting');
            $this->fail('Transisi mundur seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Received dengan biaya final menimpa estimasi awal.
        $received = $service->transition($this->owner, $order, 'received', '75000');
        $this->assertNotNull($received->received_at);
        $this->assertSame('75000.00', (string) $received->lab_cost);

        // Status akhir tidak bisa bergerak lagi.
        try {
            $service->transition($this->owner, $order, 'ordered');
            $this->fail('Order received seharusnya terkunci.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_received_posts_lab_cost_to_hpp_journal(): void
    {
        $service = app(LabOrderService::class);
        $sale = $this->lensSale();
        $order = $service->create($this->owner, $sale, 'Lab Sentral', '0');
        $service->transition($this->owner, $order, 'ordered');
        $service->transition($this->owner, $order, 'received', '75000');

        $journal = Journal::where('source_type', 'lab_order')->where('source_id', $order->id)->firstOrFail();
        $lines = $journal->lines()->with('account')->get();
        $hpp = $lines->firstWhere('account.code', '5-1100');
        $payable = $lines->firstWhere('account.code', '2-1100');
        $this->assertNotNull($hpp);
        $this->assertSame('75000.00', (string) $hpp->debit);
        $this->assertSame((int) $sale->branch_id, (int) $hpp->branch_id);
        $this->assertNotNull($payable);
        $this->assertSame('75000.00', (string) $payable->credit);

        // Biaya nol: tidak ada jurnal.
        $free = $service->create($this->owner, $this->lensSale(), 'Lab Internal', '0');
        $service->transition($this->owner, $free, 'ordered');
        $service->transition($this->owner, $free, 'received');
        $this->assertNull(Journal::where('source_type', 'lab_order')->where('source_id', $free->id)->first());
    }

    public function test_permission_and_active_sale_guards(): void
    {
        $service = app(LabOrderService::class);
        $sale = $this->lensSale();

        $teknisi = User::create(['name' => 'Teknisi Uji', 'username' => 'teknisiuji', 'password' => 'password', 'is_active' => true]);
        $teknisi->roles()->attach(Role::where('name', 'Teknisi')->firstOrFail());
        try {
            $service->create($teknisi, $sale, 'Lab X');
            $this->fail('Teknisi tanpa lab.order.manage seharusnya ditolak.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        $this->actingAs($teknisi)->get(route('lab-orders.index'))->assertForbidden();

        // Penjualan void tidak dapat diberi order lab. (Konteks aktor
        // dikembalikan ke owner — panggilan service langsung memakai
        // konteks pengguna aktif terakhir di proses yang sama.)
        $this->actingAs($this->owner);
        app(SalesService::class)->voidSale($this->owner, $sale, 'Uji batal');
        try {
            $service->create($this->owner, $sale, 'Lab X');
            $this->fail('Order lab untuk penjualan void seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sale', $e->errors());
        }
    }

    public function test_http_flow_create_and_advance(): void
    {
        $sale = $this->lensSale();

        $response = $this->actingAs($this->owner)->post(route('lab-orders.store'), [
            'sale_id' => $sale->id, 'lab_name' => 'Lab Optik Timur', 'lab_cost' => '25000',
        ]);
        $order = LabOrder::firstOrFail();
        $response->assertRedirect(route('lab-orders.show', $order));

        $this->actingAs($this->owner)->get(route('lab-orders.index'))->assertOk()->assertSee($order->number);
        $this->actingAs($this->owner)->post(route('lab-orders.transition', $order), ['to' => 'ordered'])->assertRedirect();
        $this->assertSame('ordered', $order->refresh()->status);
        $this->actingAs($this->owner)->get(route('lab.show', $sale->lensProcess))->assertOk()->assertSee($order->number);
    }
}
