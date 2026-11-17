<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit W-01 W1-5: pembaruan status webhook hanya berlaku untuk
 * pesan ember gateway pemilik callback. Sebelumnya applyStatus
 * mencocokkan provider_message_id se-instalasi, sehingga kelompok
 * payload satu gateway dapat mengubah status pesan tenant lain.
 */
class WaWebhookStatusScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    private function tenant(string $tag): Tenant
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

        return $tenant;
    }

    private function gateway(Tenant $tenant, string $pnid, string $secret): WaGateway
    {
        return WaGateway::create([
            'tenant_id' => $tenant->id, 'provider' => 'meta_cloud_api',
            'sender_number' => '6281200000000', 'is_active' => true,
            'credentials_encrypted' => [
                'phone_number_id' => $pnid, 'waba_id' => 'W'.$pnid, 'access_token' => 'TOKEN-'.$pnid,
                'verify_token' => 'verify-'.$pnid, 'app_secret' => $secret, 'api_version' => 'v21.0',
            ],
        ]);
    }

    private function sentMessage(Tenant $tenant, string $providerId): WaMessage
    {
        return WaMessage::create([
            'tenant_id' => $tenant->id, 'phone' => '628120000031', 'body' => 'Halo',
            'direction' => 'out', 'status' => 'sent', 'trigger' => 'invoice',
            'provider_message_id' => $providerId, 'sent_at' => now(),
        ]);
    }

    public function test_status_update_applies_only_within_gateway_owner_bucket(): void
    {
        $tenantA = $this->tenant('wha');
        $tenantB = $this->tenant('whb');
        $this->gateway($tenantA, 'PNID-WH-A', 'secret-a');
        $this->gateway($tenantB, 'PNID-WH-B', 'secret-b');
        $msgA = $this->sentMessage($tenantA, 'wamid.MILIK-A');
        $msgB = $this->sentMessage($tenantB, 'wamid.MILIK-B');

        // Kelompok payload gateway A (tanda tangan A sah) membawa
        // status untuk ID pesan A dan — menyusup — ID pesan B.
        $payload = json_encode([
            'entry' => [['changes' => [['value' => [
                'metadata' => ['phone_number_id' => 'PNID-WH-A'],
                'statuses' => [
                    ['id' => 'wamid.MILIK-A', 'status' => 'delivered'],
                    ['id' => 'wamid.MILIK-B', 'status' => 'delivered'],
                ],
            ]]]]],
        ]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'secret-a');
        $this->call('POST', route('wa-webhook.handle'), [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature, 'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertOk();

        $this->assertSame('delivered', $msgA->fresh()->status);
        $this->assertSame('sent', $msgB->fresh()->status);
    }
}
