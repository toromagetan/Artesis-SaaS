<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\CampaignService;
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
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * D-02 GAP-5 — Permukaan komunikasi (WhatsApp) lintas tenant.
 * Tiga permukaan dibuktikan: (1) inbox — sejak skema wa.me (keputusan
 * owner 15 Nov 2026) seluruh permukaan WA API tertutup untuk aktor
 * tenant, dan data percakapan tetap teratribusi per tenant;
 * (2) audiens kampanye — resolusi penerima dan pengiriman di tingkat
 * layanan hanya menjangkau pelanggan tenant pemilik kampanye,
 * termasuk saat segmen menunjuk cabang tenant lain;
 * (3) otomasi — perintah terjadwal menyapa setiap pelanggan atas
 * nama tenantnya sendiri tanpa atribusi silang.
 */
class CommsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    private Customer $customerA;

    private Customer $customerB;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, WaTemplateSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $this->adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $this->adminB, $this->branchB] = $this->tenantAdmin('b');
        $branchB = $this->branchB;

        $this->customerA = Customer::create([
            'name' => 'Pelanggan WA Alpha', 'phone' => '0812-0001', 'branch_id' => $branchA->id,
            'tenant_id' => $tenantA->id,
        ]);
        $this->customerB = Customer::create([
            'name' => 'Pelanggan WA Beta', 'phone' => '0812-0002', 'branch_id' => $branchB->id,
            'tenant_id' => $tenantB->id,
        ]);

        WaMessage::create([
            'customer_id' => $this->customerA->id, 'phone' => '628120001', 'body' => 'Pesan rahasia tenant Alpha',
            'direction' => 'in', 'status' => 'delivered', 'trigger' => 'inbox', 'provider_message_id' => 'wamid.D02A1',
        ]);
        WaMessage::create([
            'customer_id' => $this->customerB->id, 'phone' => '628120002', 'body' => 'Pesan rahasia tenant Beta',
            'direction' => 'in', 'status' => 'delivered', 'trigger' => 'inbox', 'provider_message_id' => 'wamid.D02B1',
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
            'email_verified_at' => now(),            'name' => 'Admin WA '.$tag, 'username' => 'admin-wa-'.$tag,
            'email' => 'admin-wa-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    public function test_inbox_is_closed_for_tenants_and_messages_stay_attributed(): void
    {
        // Skema wa.me (keputusan owner 15 Nov 2026): Inbox WA API ditutup
        // untuk aktor tenant — tidak ada daftar percakapan yang dapat
        // dijangkau aktor tenant mana pun.
        $this->actingAs($this->adminA)->get('/wa/inbox')
            ->assertForbidden()
            ->assertDontSee('Pesan rahasia tenant Beta');

        $this->actingAs($this->adminB)->get('/wa/inbox')
            ->assertForbidden()
            ->assertDontSee('Pesan rahasia tenant Alpha');

        // Data percakapan tetap teratribusi ke tenant pemiliknya.
        $this->assertSame(1, WaMessage::where('customer_id', $this->customerA->id)->count());
        $this->assertSame(1, WaMessage::where('customer_id', $this->customerB->id)->count());
    }

    public function test_conversation_view_and_reply_denied_for_other_tenant(): void
    {
        $view = $this->actingAs($this->adminB)->get('/wa/inbox/'.$this->customerA->id);
        $this->assertContains($view->status(), [403, 404]);
        $view->assertDontSee('Pesan rahasia tenant Alpha');

        $reply = $this->actingAs($this->adminB)->post('/wa/inbox/'.$this->customerA->id.'/balas', [
            'body' => 'Balasan lintas tenant yang harus ditolak',
        ]);
        $this->assertContains($reply->status(), [403, 404, 302]);
        $this->assertSame(
            0,
            WaMessage::where('customer_id', $this->customerA->id)
                ->where('body', 'Balasan lintas tenant yang harus ditolak')
                ->count(),
        );
    }

    public function test_own_conversation_view_is_also_closed_for_tenants(): void
    {
        // Kontrol terbalik sejak skema wa.me (15 Nov 2026): bahkan
        // percakapan tenant sendiri tidak lagi dapat dibuka aktor tenant
        // lewat permukaan WA API — penutupan berlaku untuk semua tenant.
        $this->actingAs($this->adminA)->get('/wa/inbox/'.$this->customerA->id)
            ->assertForbidden()
            ->assertDontSee('Pesan rahasia tenant Alpha');
    }

    public function test_campaign_audience_and_send_never_reach_other_tenant(): void
    {
        $service = app(CampaignService::class);
        $template = WaTemplate::where('key', 'campaign')->firstOrFail();

        $campaign = $service->create($this->adminA, 'Kampanye Alpha', $template->id, ['type' => 'all']);

        // Resolusi audiens dalam konteks aktor A: hanya pelanggan A.
        $this->actingAs($this->adminA);
        $recipients = $service->resolveRecipients($campaign);
        $this->assertTrue($recipients->contains('id', $this->customerA->id));
        $this->assertFalse($recipients->contains('id', $this->customerB->id));

        // Segmen yang menunjuk cabang tenant B tidak menghasilkan
        // satu pun penerima dari tenant B.
        $foreign = $service->create($this->adminA, 'Kampanye Segmen Asing', $template->id, [
            'type' => 'branch', 'branch_id' => $this->branchB->id,
        ]);
        $foreignRecipients = $service->resolveRecipients($foreign);
        $this->assertFalse($foreignRecipients->contains('id', $this->customerB->id));
        $this->assertCount(0, $foreignRecipients);

        // Pengiriman penuh di tingkat layanan: pesan kampanye hanya
        // dibuat untuk pelanggan tenant pemilik kampanye.
        $service->approve($this->adminA, $campaign);
        $template->update([
            'body' => 'Halo {nama}! Ada promo khusus bulan ini di {cabang}.',
            'approved_by' => $this->adminA->id,
        ]);
        $service->requestSend($this->adminA, $campaign);
        $service->deliver($campaign);

        // Permukaan HTTP kampanye tertutup untuk aktor tenant sejak
        // skema wa.me (keputusan owner 15 Nov 2026).
        $this->actingAs($this->adminA)->post('/wa/kampanye/'.$campaign->id.'/kirim')->assertForbidden();

        $messages = WaMessage::where('trigger', 'campaign')->where('campaign_id', $campaign->id)->get();
        $this->assertCount(1, $messages);
        $this->assertSame($this->customerA->id, $messages[0]->customer_id);
        $this->assertStringContainsString('Pelanggan WA Alpha', $messages[0]->body);
        $this->assertSame(1, $campaign->fresh()->recipient_count);
    }

    public function test_birthday_automation_greets_each_tenant_only_its_own_customers(): void
    {
        Carbon::setTestNow('2026-10-18 09:00:00');
        $this->customerA->update(['birth_date' => '1990-10-18']);
        $this->customerB->update(['birth_date' => '1990-10-18']);

        $this->artisan('lentera:wa-otomatis')->assertSuccessful();

        $rows = WaMessage::where('template_key', 'birthday')->get();
        $this->assertCount(2, $rows);

        $forA = $rows->firstWhere('customer_id', $this->customerA->id);
        $this->assertNotNull($forA);
        $this->assertStringContainsString('Pelanggan WA Alpha', $forA->body);
        $this->assertStringNotContainsString('Pelanggan WA Beta', $forA->body);

        $forB = $rows->firstWhere('customer_id', $this->customerB->id);
        $this->assertNotNull($forB);
        $this->assertStringContainsString('Pelanggan WA Beta', $forB->body);
        $this->assertStringNotContainsString('Pelanggan WA Alpha', $forB->body);
    }
}
