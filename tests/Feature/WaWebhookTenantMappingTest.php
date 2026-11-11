<?php

namespace Tests\Feature;

use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Koreksi audit PR #33 — pemetaan tenant webhook WhatsApp.
 *
 * Gateway WA bersifat global untuk instalasi (satu nomor; model
 * per tenant adalah keputusan OD-4 yang belum diambil) dan HMAC
 * hanya membuktikan asal callback, bukan tenant pemilik nomor.
 * Aturan yang ditegakkan: nomor UNIK di seluruh instalasi dipetakan
 * ke pelanggannya; nomor yang tercatat pada lebih dari satu tenant
 * TIDAK ditebak — pesan tertangkap sebagai yatim (customer_id
 * NULL) dan tidak tampil di inbox tenant mana pun. Inbox sendiri
 * terpagar kepemilikan pelanggan mengikuti aktor.
 */
class WaWebhookTenantMappingTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        WaGateway::create([
            'provider' => 'meta_cloud_api',
            'sender_number' => '6281234567890',
            'credentials_encrypted' => [
                'phone_number_id' => '1234567890',
                'waba_id' => '99887766',
                'access_token' => 'EAAB-secret-token-9999',
                'verify_token' => 'lentera-verify',
                'app_secret' => 'test-app-secret',
                'api_version' => 'v21.0',
            ],
            'is_active' => true,
        ]);
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
            'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    private function customerOf(Tenant $tenant, Branch $branch, string $name, string $phone): Customer
    {
        return Customer::create([
            'name' => $name, 'phone' => $phone,
            'branch_id' => $branch->id, 'tenant_id' => $tenant->id,
        ]);
    }

    private function signedWebhookPost(array $payload, string $secret): TestResponse
    {
        $json = json_encode($payload);

        return $this->call('POST', route('wa-webhook.handle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, $secret),
        ], $json);
    }

    private function incomingPayload(string $from, string $providerId, string $body): array
    {
        return ['entry' => [['changes' => [['value' => [
            'messages' => [[
                'id' => $providerId, 'from' => $from, 'type' => 'text',
                'text' => ['body' => $body],
            ]],
        ]]]]]];
    }

    public function test_unique_number_maps_to_its_customer(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        $this->tenantAdmin('b');
        $customerA = $this->customerOf($tenantA, $branchA, 'Pelanggan Unik A', '0812-3456-7890');

        $this->signedWebhookPost(
            $this->incomingPayload('6281234567890', 'wamid.UNIK1', 'Halo dari A'),
            'test-app-secret',
        )->assertOk();

        $incoming = WaMessage::where('direction', 'in')->firstOrFail();
        $this->assertSame($customerA->id, (int) $incoming->customer_id);
        $this->assertSame('Halo dari A', $incoming->body);
    }

    public function test_shared_number_is_not_attributed_to_any_tenant(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        [$tenantB, , $branchB] = $this->tenantAdmin('b');
        // Nomor sama tercatat di dua tenant (format penulisan beda,
        // bentuk ternormalisasi sama).
        $this->customerOf($tenantA, $branchA, 'Kembar A', '0857-1111-2222');
        $this->customerOf($tenantB, $branchB, 'Kembar B', '085711112222');

        $this->signedWebhookPost(
            $this->incomingPayload('6285711112222', 'wamid.KEMBAR1', 'Pesan ambigu'),
            'test-app-secret',
        )->assertOk();

        // Pesan tetap tertangkap (tidak hilang), tetapi TIDAK
        // teratribusi ke tenant mana pun.
        $incoming = WaMessage::where('direction', 'in')->firstOrFail();
        $this->assertNull($incoming->customer_id);
        $this->assertSame('Pesan ambigu', $incoming->body);
    }

    public function test_invalid_signature_still_rejected(): void
    {
        [$tenantA, , $branchA] = $this->tenantAdmin('a');
        $this->customerOf($tenantA, $branchA, 'Pelanggan A', '0812-3456-7890');

        $this->signedWebhookPost(
            $this->incomingPayload('6281234567890', 'wamid.PALSU1', 'Pesan palsu'),
            'secret-yang-salah',
        )->assertForbidden();

        $this->assertSame(0, WaMessage::where('direction', 'in')->count());
    }

    public function test_inbox_is_isolated_per_tenant(): void
    {
        [$tenantA, $adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, , $branchB] = $this->tenantAdmin('b');
        $customerA = $this->customerOf($tenantA, $branchA, 'Pelanggan A', '0812-0000-0001');
        $customerB = $this->customerOf($tenantB, $branchB, 'Pelanggan B', '0812-0000-0002');

        $base = ['direction' => 'in', 'status' => 'delivered', 'trigger' => 'inbox', 'sent_at' => now()];
        WaMessage::create($base + [
            'customer_id' => $customerA->id, 'phone' => '6281200000001',
            'body' => 'PESAN-RAHASIA-A', 'provider_message_id' => 'wamid.IA',
        ]);
        WaMessage::create($base + [
            'customer_id' => $customerB->id, 'phone' => '6281200000002',
            'body' => 'PESAN-RAHASIA-B', 'provider_message_id' => 'wamid.IB',
        ]);
        WaMessage::create($base + [
            'customer_id' => null, 'phone' => '6281299999999',
            'body' => 'PESAN-YATIM', 'provider_message_id' => 'wamid.IY',
        ]);

        $response = $this->actingAs($adminA)->get(route('wa-inbox.index'));
        $response->assertOk();
        $response->assertSee('PESAN-RAHASIA-A');
        $response->assertDontSee('PESAN-RAHASIA-B');
        $response->assertDontSee('PESAN-YATIM');
    }
}
