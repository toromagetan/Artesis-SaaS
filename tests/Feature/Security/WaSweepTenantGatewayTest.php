<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Audit W-01 W1-6: sapu antrean WA mendispatch ulang pesan
 * menggantung bila ADA gateway aktif milik siapa pun. Sebelumnya
 * gerbangnya hanya gateway global (tenant_id NULL), sehingga pesan
 * tenant di instalasi tanpa gateway global tidak pernah disapu.
 */
class WaSweepTenantGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
    }

    public function test_sweep_redispatches_with_tenant_gateway_only(): void
    {
        Queue::fake();
        $platform = User::where('username', 'platform')->firstOrFail();
        $tenant = Tenant::create(['name' => 'Optik SW', 'slug' => 'optik-sw', 'email' => 'sw@uji.id']);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $platform,
        );

        $message = WaMessage::create([
            'tenant_id' => $tenant->id, 'phone' => '628120000041', 'body' => 'Invoice lama',
            'direction' => 'out', 'status' => 'queued', 'trigger' => 'invoice',
        ]);
        $message->forceFill(['created_at' => now()->subHour()])->save();

        // Tanpa gateway apa pun: tidak ada dispatch ulang.
        Artisan::call('lentera:wa-sapu');
        Queue::assertNotPushed(SendWhatsAppMessage::class);

        // Hanya gateway milik tenant (tanpa gateway global):
        // pesan tenant tetap didispatch ulang.
        WaGateway::create([
            'tenant_id' => $tenant->id, 'provider' => 'meta_cloud_api',
            'sender_number' => '628120000041', 'is_active' => true,
            'credentials_encrypted' => [
                'phone_number_id' => 'PNID-SW', 'access_token' => 'TOKEN-SW', 'api_version' => 'v21.0',
            ],
        ]);
        Artisan::call('lentera:wa-sapu');
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }
}
