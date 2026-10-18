<?php

namespace Tests\Feature\Comms;

use App\Modules\Catalog\Models\Product;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Lab\Models\LensProcess;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\LensProcessService;
use App\Modules\Lab\Services\QcCheckService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\SalesService;
use App\Support\Money;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\QcPointTemplateSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * F3.5 Otomasi pesan: invoice dari transaksi, pengingat pengambilan
 * sesudah Complete, purnajual H+7 & ulang tahun terjadwal — semua
 * tercatat di log; kegagalan gateway tidak menggagalkan transaksi.
 */
class WaAutomationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $lens;

    private Product $frame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, QcPointTemplateSeeder::class, WaTemplateSeeder::class]);
        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Sinta Maharani', 'phone' => '0813-9876-5432',
        ]);
        $this->lens = Product::factory()->ofType('lens')->create(['sale_price' => '350000']);
        $this->frame = Product::factory()->ofType('frame')->create(['sale_price' => '400000']);
        $inventory = app(InventoryService::class);
        $inventory->recordMovement($this->owner, $this->lens, $this->branch, 'initial_stock', 5, 'test_seed', 1);
        $inventory->recordMovement($this->owner, $this->frame, $this->branch, 'initial_stock', 5, 'test_seed', 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function activeGateway(): void
    {
        WaGateway::create([
            'provider' => 'meta_cloud_api',
            'sender_number' => '6281234567890',
            'credentials_encrypted' => [
                'phone_number_id' => '1234567890', 'waba_id' => '1',
                'access_token' => 'EAAB-token', 'verify_token' => 'x', 'api_version' => 'v21.0',
            ],
            'is_active' => true,
        ]);
    }

    private function lensSale(): Sale
    {
        return app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->lens->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '350000'],
        ]);
    }

    /** Jalankan rantai lab sampai Complete untuk satu penjualan lensa stok. */
    private function completeProcess(Sale $sale): LensProcess
    {
        $technician = User::factory()->create();
        $technician->roles()->sync([Role::where('name', 'Teknisi')->firstOrFail()->id]);
        $technician->branches()->sync([$this->branch->id]);

        $process = LensProcess::where('sale_id', $sale->id)->firstOrFail();
        $lab = app(LensProcessService::class);
        $lab->assignTechnician($this->owner, $process, $technician->id, 2);
        $lab->transition($this->owner, $process, 'edging');
        $lab->transition($this->owner, $process, 'qc');
        $qc = app(QcCheckService::class);
        foreach (QcPointTemplate::activeOrdered()->get() as $point) {
            $qc->record($technician, $process, $point->id, 'pass');
        }
        $lab->transition($this->owner, $process, 'ready');
        $lab->complete($this->owner, $process);

        return $process->refresh();
    }

    public function test_invoice_message_is_logged_for_every_sale(): void
    {
        $sale = $this->lensSale();

        $message = WaMessage::where('template_key', 'invoice')->where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($message, 'setiap penjualan harus mengantrekan invoice WA');
        $this->assertSame('invoice', $message->trigger);
        $this->assertSame('queued', $message->status); // belum ada gateway aktif
        $this->assertStringContainsString($sale->number, $message->body);
        $this->assertStringContainsString('Sinta Maharani', $message->body);
        $this->assertStringContainsString(Money::fromDecimal('350000')->format(), $message->body);
    }

    public function test_invoice_is_sent_when_gateway_active(): void
    {
        $this->activeGateway();
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.AUTO1']]], 200)]);

        $this->lensSale();

        $message = WaMessage::where('template_key', 'invoice')->firstOrFail();
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('wamid.AUTO1', $message->fresh()->provider_message_id);
    }

    public function test_gateway_failure_never_blocks_the_sale(): void
    {
        $this->activeGateway();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $this->customer->id, 'sale_date' => '2026-10-18', 'tax_mode' => 'inclusive',
        ], [
            ['product_id' => $this->frame->id, 'qty' => 1],
        ], [
            ['method' => 'cash', 'amount' => '400000'],
        ]);

        $this->assertSame('paid', $sale->paymentStatus());
        $message = WaMessage::where('template_key', 'invoice')->where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('failed', $message->fresh()->status);
    }

    public function test_pickup_reminder_fires_on_completion(): void
    {
        $sale = $this->lensSale();
        $this->assertSame(0, WaMessage::where('template_key', 'pickup_reminder')->count());

        $this->completeProcess($sale);

        $message = WaMessage::where('template_key', 'pickup_reminder')->where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($message, 'Complete harus memicu pengingat pengambilan');
        $this->assertSame('reminder', $message->trigger);
        $this->assertStringContainsString($sale->number, $message->body);
    }

    public function test_after_service_command_sends_once_at_h_plus_7(): void
    {
        $sale = $this->lensSale();
        $process = $this->completeProcess($sale);
        // Geser waktu selesai ke 7 hari lalu (H+7 jatuh hari ini).
        DB::table('lens_processes')->where('id', $process->id)
            ->update(['completed_at' => '2026-10-11 10:00:00']);

        $this->artisan('lentera:wa-otomatis')->assertSuccessful();
        $rows = WaMessage::where('template_key', 'after_service')->where('customer_id', $this->customer->id)->get();
        $this->assertCount(1, $rows);
        $this->assertStringContainsString($sale->number, $rows[0]->body);

        // Jalan lagi -> tetap satu (anti-ganda).
        $this->artisan('lentera:wa-otomatis')->assertSuccessful();
        $this->assertSame(1, WaMessage::where('template_key', 'after_service')->count());
    }

    public function test_birthday_command_greets_only_todays_birthdays_once(): void
    {
        $birthday = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Ultah Hari Ini', 'phone' => '081200001111',
            'birth_date' => '1990-10-18',
        ]);
        Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Ultah Besok', 'phone' => '081200002222',
            'birth_date' => '1990-10-19',
        ]);

        $this->artisan('lentera:wa-otomatis')->assertSuccessful();

        $rows = WaMessage::where('template_key', 'birthday')->get();
        $this->assertCount(1, $rows);
        $this->assertSame($birthday->id, $rows[0]->customer_id);
        $this->assertSame('birthday', $rows[0]->trigger);

        $this->artisan('lentera:wa-otomatis')->assertSuccessful();
        $this->assertSame(1, WaMessage::where('template_key', 'birthday')->count());
    }
}
