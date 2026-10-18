<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Master\Models\Setting;
use App\Modules\Master\Models\Supplier;
use App\Modules\Purchasing\Services\PurchaseOrderService;
use App\Modules\Purchasing\Services\ReceiptService;
use App\Modules\Sales\Models\OfflineDevice;
use App\Modules\Sales\Models\OfflineException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Services\OfflineDeviceService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5.3 fondasi server kasir offline — autentikasi perangkat,
 * sinkronisasi idempoten lewat jalur penjualan asli, antrean
 * pengecualian (stok ketat & non-tunai), resolusi coba-lagi/buang,
 * dan halaman admin terpagar.
 */
class OfflineSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    private Customer $customer;

    private Product $frame;

    private string $token;

    private int $deviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        Setting::set('tax.rate_default', '0');
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branch->id]);
        $this->frame = Product::factory()->ofType('frame')->create(['sku' => 'OFF-F', 'cost_price' => '50000', 'sale_price' => '100000']);
        $this->stockIn(2);

        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branch->id]);

        $issued = app(OfflineDeviceService::class)->register($this->owner, 'KSR1', $this->branch->id, $kasir->id);
        $this->token = $issued['plain_token'];
        $this->deviceId = $issued['device']->id;
    }

    private function stockIn(int $qty): void
    {
        $supplier = Supplier::create(['name' => 'PT Off', 'is_active' => true]);
        $orders = app(PurchaseOrderService::class);
        $po = $orders->create($this->owner, $supplier->id, $this->branch->id, [
            ['product_id' => $this->frame->id, 'qty' => $qty, 'unit_cost' => '50000'],
        ]);
        $orders->markSent($this->owner, $po);
        app(ReceiptService::class)->store($this->owner, $po, [
            ['purchase_order_item_id' => $po->items->first()->id, 'qty' => $qty, 'unit_cost_actual' => '50000'],
        ]);
    }

    /** @param array<int, array<string, mixed>> $intents */
    private function syncPayload(array $intents): array
    {
        return ['intents' => $intents];
    }

    private function intent(string $uuid, int $qty, string $method = 'cash', string $amount = '100000'): array
    {
        return ['client_uuid' => $uuid, 'local_no' => '000'.$qty, 'offline_at' => '2026-10-18 10:00:00',
            'customer_id' => $this->customer->id, 'tax_mode' => 'exclusive',
            'items' => [['product_id' => $this->frame->id, 'qty' => $qty]],
            'payments' => [['method' => $method, 'amount' => $amount]]];
    }

    public function test_sync_requires_device_token_and_revoked_device_is_rejected(): void
    {
        $this->postJson('/api/v1/offline/sync', $this->syncPayload([$this->intent('a-1', 1)]))->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer salah')
            ->postJson('/api/v1/offline/sync', $this->syncPayload([$this->intent('a-1', 1)]))->assertUnauthorized();

        $device = OfflineDevice::findOrFail($this->deviceId);
        app(OfflineDeviceService::class)->revoke($this->owner, $device);
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/offline/sync', $this->syncPayload([$this->intent('a-1', 1)]))->assertUnauthorized();
    }

    public function test_sync_creates_sales_idempotently_and_routes_conflicts_to_exceptions(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $payload = $this->syncPayload([
            $this->intent('b-1', 2, 'cash', '200000'),
            $this->intent('b-2', 2, 'cash', '200000'), // stok ketat habis -> pengecualian
            $this->intent('b-3', 1, 'qris', '100000'), // non-tunai -> pengecualian
        ]);

        $response = $this->withHeaders($headers)->postJson('/api/v1/offline/sync', $payload)->assertOk();
        $results = collect($response->json('results'))->keyBy('client_uuid');
        $this->assertSame('synced', $results['b-1']['status']);
        $this->assertSame('exception', $results['b-2']['status']);
        $this->assertSame('non_cash_offline', $results['b-3']['reason'] ?? $results['b-3']['status']);
        $this->assertSame(1, Sale::count());
        $this->assertSame(2, OfflineException::where('status', 'pending')->count());
        $sale = Sale::first();
        $this->assertSame('b-1', $sale->client_uuid);
        $this->assertStringContainsString('offline:KSR1/', (string) $sale->notes);

        // Sinkron ulang payload yang sama: duplikat + pengecualian lama, nol penjualan baru.
        $again = $this->withHeaders($headers)->postJson('/api/v1/offline/sync', $payload)->assertOk();
        $results2 = collect($again->json('results'))->keyBy('client_uuid');
        $this->assertSame('duplicate', $results2['b-1']['status']);
        $this->assertSame(1, Sale::count());
        $this->assertSame(2, OfflineException::count());
    }

    public function test_snapshot_returns_branch_stock_and_retry_resolves_after_restock(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];
        $snap = $this->withHeaders($headers)->getJson('/api/v1/offline/snapshot')->assertOk();
        $row = collect($snap->json('products'))->firstWhere('id', $this->frame->id);
        $this->assertSame(2, $row['stock']);

        $this->withHeaders($headers)->postJson('/api/v1/offline/sync', $this->syncPayload([
            $this->intent('c-1', 2, 'cash', '200000'), $this->intent('c-2', 1, 'cash', '100000'),
        ]))->assertOk();
        $exception = OfflineException::where('client_uuid', 'c-2')->firstOrFail();
        $this->assertSame('pending', $exception->status);

        // Stok datang -> Coba Lagi dari halaman admin berhasil.
        $this->stockIn(3);
        $this->actingAs($this->owner)
            ->post(route('offline-exceptions.retry', $exception))
            ->assertRedirect(route('offline-exceptions.index'));
        $this->assertSame('resolved', $exception->fresh()->status);
        $this->assertSame(2, Sale::count());

        // Buang: hanya Owner/Admin; kasir ditolak.
        $this->withHeaders($headers)->postJson('/api/v1/offline/sync', $this->syncPayload([
            $this->intent('c-3', 1, 'qris', '100000'),
        ]))->assertOk();
        $ex2 = OfflineException::where('client_uuid', 'c-3')->firstOrFail();
        $this->actingAs($this->owner)
            ->post(route('offline-exceptions.discard', $ex2), ['note' => 'Pelanggan batal.'])
            ->assertRedirect(route('offline-exceptions.index'));
        $this->assertSame('discarded', $ex2->fresh()->status);
    }

    public function test_admin_pages_are_permission_fenced(): void
    {
        $this->actingAs($this->owner)->get(route('offline-devices.index'))->assertOk();
        $this->actingAs($this->owner)->get(route('offline-exceptions.index'))->assertOk();

        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'Kasir')->firstOrFail()->id]);
        $this->actingAs($kasir)->get(route('offline-devices.index'))->assertForbidden();
    }
}
