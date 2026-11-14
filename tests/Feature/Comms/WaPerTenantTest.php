<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * WA per tenant (keputusan owner 2026-11-14, menutup OD-4): setiap
 * tenant memakai gateway WhatsApp MILIKNYA — pengaturan, pengiriman,
 * dan atribusi webhook terpagar tenant pemilik nomor.
 */
class WaPerTenantTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$tag, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'email_verified_at' => now(),
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function gatewayFor(Tenant $tenant, string $phoneNumberId, string $token, string $secret, string $sender): WaGateway
    {
        return WaGateway::create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta_cloud_api',
            'sender_number' => $sender,
            'credentials_encrypted' => [
                'phone_number_id' => $phoneNumberId,
                'waba_id' => '11223344',
                'access_token' => $token,
                'verify_token' => 'verify-'.$phoneNumberId,
                'app_secret' => $secret,
                'api_version' => 'v21.0',
            ],
            'is_active' => true,
        ]);
    }

    private function customerOf(Tenant $tenant, Branch $branch, string $name, string $phone): Customer
    {
        return Customer::create([
            'name' => $name, 'phone' => $phone,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
    }

    public function test_gateway_settings_are_scoped_to_own_tenant(): void
    {
        [$tenantA, $adminA] = $this->tenantAdmin('a');
        [$tenantB] = $this->tenantAdmin('b');
        $this->gatewayFor($tenantA, 'PN-A', 'token-aaaa-1111', 'secret-a', '628110000001');
        $this->gatewayFor($tenantB, 'PN-B', 'token-bbbb-2222', 'secret-b', '628110000002');

        // Halaman pengaturan A menampilkan nomor A, bukan B.
        $this->actingAs($adminA)->get(route('wa-gateway.edit'))
            ->assertOk()
            ->assertSee('628110000001')
            ->assertDontSee('628110000002');

        // Simpan sebagai A hanya mengubah baris A.
        $this->actingAs($adminA)->put(route('wa-gateway.update'), [
            'provider' => 'meta_cloud_api',
            'sender_number' => '0811-777-001',
            'phone_number_id' => 'PN-A',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('62811777001', WaGateway::forTenant($tenantA->id)->sender_number);
        $this->assertSame('628110000002', WaGateway::forTenant($tenantB->id)->sender_number);
    }

    public function test_outbound_uses_the_customers_own_tenant_gateway(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        [$tenantB, , $branchB] = $this->tenantAdmin('b');
        $this->gatewayFor($tenantA, 'PN-A', 'token-aaaa-1111', 'secret-a', '628110000001');
        $this->gatewayFor($tenantB, 'PN-B', 'token-bbbb-2222', 'secret-b', '628110000002');
        $customerA = $this->customerOf($tenantA, $branchA, 'Pelanggan A', '0812-0000-0010');
        $customerB = $this->customerOf($tenantB, $branchB, 'Pelanggan B', '0812-0000-0020');

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TENANT']]], 200),
        ]);

        $service = app(MessagingService::class);
        $messageA = $service->sendText($customerA, 'Halo dari A');
        $messageB = $service->sendText($customerB, 'Halo dari B');

        $this->assertSame($tenantA->id, $messageA->tenant_id);
        $this->assertSame($tenantB->id, $messageB->tenant_id);
        $this->assertSame('sent', $messageA->fresh()->status);
        $this->assertSame('sent', $messageB->fresh()->status);

        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/PN-A/messages')
            && $r->hasHeader('Authorization', 'Bearer token-aaaa-1111'));
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/PN-B/messages')
            && $r->hasHeader('Authorization', 'Bearer token-bbbb-2222'));
    }

    public function test_tenant_without_own_gateway_stays_queued(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        $this->gatewayFor($tenantA, 'PN-A', 'token-aaaa-1111', 'secret-a', '628110000001');
        [$tenantC, , $branchC] = $this->tenantAdmin('c');
        $customerC = $this->customerOf($tenantC, $branchC, 'Pelanggan C', '0812-0000-0030');

        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.X']]], 200)]);

        // Tenant C tanpa gateway: pesan TIDAK menumpang gateway A.
        $message = app(MessagingService::class)->sendText($customerC, 'Halo dari C');

        $this->assertSame('queued', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_webhook_attributes_by_receiving_number_to_its_tenant(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        [$tenantB, , $branchB] = $this->tenantAdmin('b');
        $this->gatewayFor($tenantA, 'PN-A', 'token-aaaa-1111', 'secret-a', '628110000001');
        $this->gatewayFor($tenantB, 'PN-B', 'token-bbbb-2222', 'secret-b', '628110000002');
        // Nomor pengirim yang SAMA tercatat di kedua tenant.
        $customerA = $this->customerOf($tenantA, $branchA, 'Pelanggan A', '0812-3456-7890');
        $customerB = $this->customerOf($tenantB, $branchB, 'Pelanggan B', '0812-3456-7890');

        // Callback masuk ke nomor B → teratribusi ke pelanggan B.
        $this->signedWebhookPost($this->incomingPayload('PN-B', '6281234567890', 'wamid.MASUK-B', 'Halo B'), 'secret-b')
            ->assertOk();
        $incoming = WaMessage::where('provider_message_id', 'wamid.MASUK-B')->firstOrFail();
        $this->assertSame($customerB->id, $incoming->customer_id);
        $this->assertSame($tenantB->id, $incoming->tenant_id);

        // Callback ke nomor A dengan tanda tangan B → ditolak.
        $this->signedWebhookPost($this->incomingPayload('PN-A', '6281234567890', 'wamid.PALSU', 'Palsu'), 'secret-b')
            ->assertForbidden();
        $this->assertSame(0, WaMessage::where('provider_message_id', 'wamid.PALSU')->count());

        // Callback ke nomor A bertanda tangan A → pelanggan A.
        $this->signedWebhookPost($this->incomingPayload('PN-A', '6281234567890', 'wamid.MASUK-A', 'Halo A'), 'secret-a')
            ->assertOk();
        $incomingA = WaMessage::where('provider_message_id', 'wamid.MASUK-A')->firstOrFail();
        $this->assertSame($customerA->id, $incomingA->customer_id);
        $this->assertSame($tenantA->id, $incomingA->tenant_id);
    }

    private function signedWebhookPost(array $payload, string $secret): TestResponse
    {
        $json = json_encode($payload);

        return $this->call('POST', route('wa-webhook.handle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, $secret),
        ], $json);
    }

    private function incomingPayload(string $phoneNumberId, string $from, string $providerId, string $body): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'messages' => [[
                'id' => $providerId, 'from' => $from, 'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ]]]]]];
    }
}
