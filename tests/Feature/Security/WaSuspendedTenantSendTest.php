<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit W-01 W1-4: tenant tersuspensi tidak mengirim WhatsApp —
 * tidak lewat job antrean, tidak lewat otomasi terjadwal. Sebelumnya
 * jalur kirim hanya memeriksa fitur paket, bukan status aktif
 * tenant, sehingga suspensi tidak menghentikan kiriman apa pun.
 */
class WaSuspendedTenantSendTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, WaTemplateSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, Branch} */
    private function tenantWithBranch(string $tag): array
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
        $owner = User::create([
            'email_verified_at' => now(), 'name' => 'Owner '.$tag, 'username' => 'owner-'.$tag,
            'email' => 'owner-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $branch];
    }

    private function gateway(Tenant $tenant, string $pnid): WaGateway
    {
        return WaGateway::create([
            'tenant_id' => $tenant->id, 'provider' => 'meta_cloud_api',
            'sender_number' => '6281200000000', 'is_active' => true,
            'credentials_encrypted' => [
                'phone_number_id' => $pnid, 'waba_id' => 'W'.$pnid, 'access_token' => 'TOKEN-'.$pnid,
                'verify_token' => 'verify-'.$pnid, 'app_secret' => 'secret-'.$pnid, 'api_version' => 'v21.0',
            ],
        ]);
    }

    public function test_queued_message_of_suspended_tenant_is_not_sent_but_active_tenant_sends(): void
    {
        [$suspended, $branchS] = $this->tenantWithBranch('wsa');
        [$active, $branchA] = $this->tenantWithBranch('wsb');
        $this->gateway($suspended, 'PNID-WS-A');
        $this->gateway($active, 'PNID-WS-B');
        $custS = Customer::forceCreate([
            'name' => 'Pelanggan S', 'phone' => '08120000021', 'tenant_id' => $suspended->id,
            'branch_id' => $branchS->id, 'is_active' => true, 'wa_opt_out' => false,
        ]);
        $custA = Customer::forceCreate([
            'name' => 'Pelanggan A', 'phone' => '08120000022', 'tenant_id' => $active->id,
            'branch_id' => $branchA->id, 'is_active' => true, 'wa_opt_out' => false,
        ]);
        $suspended->update(['is_active' => false]);

        Http::fake(['*' => Http::response(['messages' => [['id' => 'wamid.WS']]], 200)]);
        $factory = app(GatewayFactory::class);

        $msgS = WaMessage::create([
            'tenant_id' => $suspended->id, 'customer_id' => $custS->id, 'phone' => '628120000021',
            'body' => 'Halo S', 'direction' => 'out', 'status' => 'queued', 'trigger' => 'invoice',
        ]);
        (new SendWhatsAppMessage($msgS->id))->handle($factory);
        $this->assertSame('queued', $msgS->fresh()->status);

        $msgA = WaMessage::create([
            'tenant_id' => $active->id, 'customer_id' => $custA->id, 'phone' => '628120000022',
            'body' => 'Halo A', 'direction' => 'out', 'status' => 'queued', 'trigger' => 'invoice',
        ]);
        (new SendWhatsAppMessage($msgA->id))->handle($factory);
        $this->assertSame('sent', $msgA->fresh()->status);
    }

    public function test_birthday_automation_skips_suspended_tenant(): void
    {
        [$suspended, $branchS] = $this->tenantWithBranch('wba');
        [$active, $branchA] = $this->tenantWithBranch('wbb');
        $today = now();
        Customer::forceCreate([
            'name' => 'Ultah S', 'phone' => '08120000023', 'tenant_id' => $suspended->id,
            'branch_id' => $branchS->id, 'is_active' => true, 'wa_opt_out' => false,
            'birth_date' => $today->copy()->subYears(30)->toDateString(),
        ]);
        $custA = Customer::forceCreate([
            'name' => 'Ultah A', 'phone' => '08120000024', 'tenant_id' => $active->id,
            'branch_id' => $branchA->id, 'is_active' => true, 'wa_opt_out' => false,
            'birth_date' => $today->copy()->subYears(25)->toDateString(),
        ]);
        $suspended->update(['is_active' => false]);

        $sent = app(WaAutomationService::class)->runBirthdays();

        $this->assertSame(1, $sent);
        $this->assertSame(1, WaMessage::where('template_key', 'birthday')->count());
        $this->assertSame($custA->id, WaMessage::where('template_key', 'birthday')->first()->customer_id);
    }
}
