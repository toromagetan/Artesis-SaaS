<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kedaluwarsa antrean WA (keputusan owner 17 Nov 2026, audit
 * lanjutan WA2-2 opsi B): pesan keluar yang masih 'queued' sesudah
 * 24 jam ditandai 'failed' dengan error 'expired' dan TIDAK dikirim
 * — termasuk pesan tertahan suspensi dan pesan tanpa gateway.
 * Penegakan ganda: job pengiriman (setiap dispatch) dan sapu per jam
 * (massal, juga saat belum ada gateway aktif sama sekali).
 */
class WaMessageExpiryTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->tenantA = $this->makeTenant('a', withGateway: true);
        $this->tenantB = $this->makeTenant('b', withGateway: false);
    }

    private function makeTenant(string $tag, bool $withGateway): Tenant
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
            'email_verified_at' => now(), 'name' => 'Admin '.$tag, 'username' => 'admin-'.$tag,
            'email' => 'admin-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        if ($withGateway) {
            WaGateway::create([
                'tenant_id' => $tenant->id,
                'provider' => 'meta_cloud_api',
                'sender_number' => '628110000000'.$tag,
                'credentials_encrypted' => [
                    'phone_number_id' => 'PN-'.strtoupper($tag),
                    'access_token' => 'TOKEN-'.$tag,
                    'verify_token' => 'verify-'.$tag,
                    'app_secret' => 'secret-'.$tag,
                    'api_version' => 'v21.0',
                ],
                'is_active' => true,
            ]);
        }

        return $tenant;
    }

    private function queuedMessage(?int $tenantId, int $ageHours): WaMessage
    {
        $message = WaMessage::create([
            'tenant_id' => $tenantId,
            'customer_id' => null,
            'phone' => '6281200000000',
            'template_key' => 'pickup_reminder',
            'body' => 'Kacamata siap diambil',
            'direction' => 'out',
            'status' => 'queued',
            'trigger' => 'reminder',
        ]);
        $message->forceFill(['created_at' => now()->subHours($ageHours)])->save();

        return $message;
    }

    private function runJob(WaMessage $message): void
    {
        (new SendWhatsAppMessage($message->id))->handle(app(GatewayFactory::class));
    }

    public function test_job_expires_message_older_than_24_hours(): void
    {
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.X']]], 200));
        $message = $this->queuedMessage($this->tenantA->id, 25);

        $this->runJob($message);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('expired', $message->error);
        Http::assertNothingSent();
    }

    public function test_job_still_sends_message_younger_than_24_hours(): void
    {
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.MUDA']]], 200));
        $message = $this->queuedMessage($this->tenantA->id, 23);

        $this->runJob($message);

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.MUDA', $message->provider_message_id);
        Http::assertSentCount(1);
    }

    public function test_job_expires_suspended_tenant_message_instead_of_holding_it(): void
    {
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.X']]], 200));
        $this->tenantA->update(['is_active' => false]);
        $message = $this->queuedMessage($this->tenantA->id, 25);

        $this->runJob($message);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('expired', $message->error);
        Http::assertNothingSent();
    }

    public function test_job_expires_message_when_tenant_has_no_gateway(): void
    {
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.X']]], 200));
        $message = $this->queuedMessage($this->tenantB->id, 25);

        $this->runJob($message);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('expired', $message->error);
        Http::assertNothingSent();
    }

    public function test_sweep_expires_old_messages_even_without_any_active_gateway(): void
    {
        WaGateway::query()->update(['is_active' => false]);
        $old = $this->queuedMessage(null, 25);
        $fresh = $this->queuedMessage(null, 1);

        Artisan::call('lentera:wa-sapu');

        $this->assertSame('failed', $old->refresh()->status);
        $this->assertSame('expired', $old->error);
        $this->assertSame('queued', $fresh->refresh()->status);
    }

    public function test_sweep_sends_fresh_stale_message_and_expires_old_one(): void
    {
        Http::fake(fn () => Http::response(['messages' => [['id' => 'wamid.SAPU']]], 200));
        $old = $this->queuedMessage($this->tenantA->id, 30);
        $fresh = $this->queuedMessage($this->tenantA->id, 1);

        Artisan::call('lentera:wa-sapu');

        $this->assertSame('failed', $old->refresh()->status);
        $this->assertSame('expired', $old->error);
        $this->assertSame('sent', $fresh->refresh()->status);
        Http::assertSentCount(1);
    }
}
