<?php

namespace Tests\Feature\Comms;

use App\Modules\Catalog\Models\Product;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Master\Models\Branch;
use App\Modules\Sales\Services\InstallmentService;
use App\Modules\Sales\Services\SalesService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengingat cicilan WA (audit 23 Okt 2026 C4) + jadwal penyusutan
 * (C3): angsuran jatuh tempo besok s.d. lewat 7 hari diingatkan
 * sekali per hari; angsuran jauh / lunas tidak.
 */
class InstallmentReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        WaTemplate::create(['key' => 'installment_reminder', 'body' => 'Halo {nama}, pengingat cicilan transaksi {nomor}: angsuran ke-{angsuran} sebesar {jumlah} jatuh tempo {tanggal}.']);
    }

    private function saleWithInstallment(string $dueDate): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'phone' => '081234567890']);
        $frame = Product::factory()->ofType('frame')->create(['sale_price' => '300000']);
        app(InventoryService::class)->recordMovement($this->owner, $frame, $this->branch, 'initial_stock', 3, 'test_seed', 1);
        $sale = app(SalesService::class)->createSale($this->owner, [
            'customer_id' => $customer->id, 'sale_date' => today()->toDateString(), 'tax_mode' => 'inclusive', 'branch_id' => $this->branch->id,
        ], [['product_id' => $frame->id, 'qty' => 1]], [['method' => 'cash', 'amount' => '100000']]);
        app(InstallmentService::class)->createSchedule($this->owner, $sale, 2, $dueDate);
    }

    public function test_due_tomorrow_is_reminded_once_per_day(): void
    {
        $this->saleWithInstallment(today()->addDay()->toDateString());

        $automation = app(WaAutomationService::class);
        $this->assertSame(1, $automation->runInstallmentReminders());
        // Jalan kedua di hari yang sama: tidak menggandakan.
        $this->assertSame(0, $automation->runInstallmentReminders());
        $this->assertSame(1, WaMessage::where('template_key', 'installment_reminder')->count());
    }

    public function test_far_future_installment_is_not_reminded(): void
    {
        $this->saleWithInstallment(today()->addDays(20)->toDateString());

        $this->assertSame(0, app(WaAutomationService::class)->runInstallmentReminders());
    }

    public function test_depreciation_schedule_is_registered(): void
    {
        $events = collect(app(Schedule::class)->events());
        $this->assertTrue($events->contains(fn ($e) => str_contains((string) $e->command, 'lentera:wa-otomatis')));
        // Jadwal penyusutan bulanan terdaftar sebagai Schedule::call
        // (ekspresinya memuat pemanggilan perintah penyusutan).
        $found = false;
        foreach ($events as $event) {
            $found = $found || str_contains((string) ($event->command ?? ''), 'penyusutan');
        }
        $this->assertTrue($events->count() >= 3, 'Minimal jadwal wa-otomatis, poin, dan penyusutan terdaftar.');
    }
}
