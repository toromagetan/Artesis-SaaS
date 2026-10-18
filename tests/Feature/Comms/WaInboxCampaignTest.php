<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Models\WaCampaign;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\CampaignService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * F3.8 Inbox dua arah + kampanye: percakapan tertaut pelanggan,
 * tandai terbaca, balasan tercatat; kampanye menolak kirim tanpa
 * persetujuan kampanye & template; opt-out selalu dihormati.
 */
class WaInboxCampaignTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, WaTemplateSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->admin = User::factory()->create();
        $this->admin->roles()->sync([Role::where('name', 'Admin')->firstOrFail()->id]);
        $this->admin->branches()->sync([$this->branch->id]);

        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Rani Inbox', 'phone' => '0812-1111-2222',
        ]);

        WaMessage::create([
            'customer_id' => $this->customer->id, 'phone' => '6281211112222', 'body' => 'Halo, kacamata saya sudah bisa diambil?',
            'direction' => 'in', 'status' => 'delivered', 'trigger' => 'inbox', 'provider_message_id' => 'wamid.IN1',
        ]);
        WaMessage::create([
            'customer_id' => $this->customer->id, 'phone' => '6281211112222', 'body' => 'Satu lagi ya kak',
            'direction' => 'in', 'status' => 'delivered', 'trigger' => 'inbox', 'provider_message_id' => 'wamid.IN2',
        ]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->firstOrFail()->id]);
        $user->branches()->sync([$this->branch->id]);

        return $user;
    }

    public function test_inbox_lists_conversations_marks_read_and_replies(): void
    {
        $response = $this->actingAs($this->admin)->get('/wa/inbox');
        $response->assertOk();
        $response->assertSee('Rani Inbox');

        // Buka utas -> pesan masuk bertanda terbaca.
        $this->actingAs($this->admin)->get('/wa/inbox/'.$this->customer->id)->assertOk();
        $this->assertSame(0, WaMessage::where('direction', 'in')->whereNull('read_at')->count());

        // Balas -> baris keluar tercatat (queued, belum ada gateway).
        $this->actingAs($this->admin)
            ->post('/wa/inbox/'.$this->customer->id.'/balas', ['body' => 'Sudah bisa diambil hari ini, Kak.'])
            ->assertRedirect();
        $reply = WaMessage::where('direction', 'out')->where('trigger', 'inbox')->firstOrFail();
        $this->assertSame('queued', $reply->status);
        $this->assertSame($this->customer->id, $reply->customer_id);

        // Kasir tidak punya wa.inbox.
        $kasir = $this->userWithRole('Kasir');
        $this->actingAs($kasir)->get('/wa/inbox')->assertForbidden();
    }

    public function test_campaign_refuses_send_without_approvals_then_sends(): void
    {
        $service = app(CampaignService::class);
        $template = WaTemplate::where('key', 'campaign')->firstOrFail();

        $campaign = $service->create($this->owner, 'Promo Oktober', $template->id, ['type' => 'all']);
        $this->assertSame('draft', $campaign->status);

        // Kirim saat draft -> ditolak.
        try {
            $service->requestSend($this->owner, $campaign);
            $this->fail('kampanye draft tidak boleh dikirim');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        // Admin tidak dapat menyetujui (izin approve hanya Owner).
        try {
            $service->approve($this->admin, $campaign);
            $this->fail('Admin tidak boleh menyetujui kampanye');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $service->approve($this->owner, $campaign);
        $this->assertSame('approved', $campaign->fresh()->status);

        // Template belum disetujui -> tetap ditolak.
        try {
            $service->requestSend($this->owner, $campaign->fresh());
            $this->fail('template belum disetujui tidak boleh dikirim');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, WaMessage::where('trigger', 'campaign')->count());

        // Isi badan template kampanye (seed bawaan hanya placeholder
        // {isi}), setujui via HTTP (Owner), lalu kirim via HTTP.
        $template->update(['body' => 'Halo {nama}! Ada promo khusus bulan ini di {cabang}.']);
        $this->actingAs($this->owner)->post('/wa/template/'.$template->id.'/setujui')->assertRedirect();
        $this->assertNotNull($template->fresh()->approved_by);

        $optOut = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Tidak Mau Promo', 'phone' => '0813-9999-0000', 'wa_opt_out' => true,
        ]);

        $this->actingAs($this->owner)->post('/wa/kampanye/'.$campaign->id.'/kirim')->assertRedirect();

        $campaign->refresh();
        $this->assertSame('done', $campaign->status);
        $this->assertSame(2, $campaign->recipient_count);

        $messages = WaMessage::where('trigger', 'campaign')->where('campaign_id', $campaign->id)->get();
        $this->assertCount(2, $messages);
        $normal = $messages->firstWhere('customer_id', $this->customer->id);
        $this->assertSame('queued', $normal->status);
        $this->assertStringContainsString('Rani Inbox', $normal->body);
        $this->assertStringContainsString('Pusat', $normal->body);
        $skipped = $messages->firstWhere('customer_id', $optOut->id);
        $this->assertSame('failed', $skipped->status);
        $this->assertSame('opt_out', $skipped->error);
    }

    public function test_campaign_http_permissions_and_segments(): void
    {
        // Admin tidak punya wa.campaigns sama sekali.
        $this->actingAs($this->admin)->get('/wa/kampanye')->assertForbidden();

        // Owner membuat kampanye segmen cabang via HTTP.
        $template = WaTemplate::where('key', 'campaign')->firstOrFail();
        $this->actingAs($this->owner)->post('/wa/kampanye', [
            'name' => 'Segmen Pusat', 'template_id' => $template->id,
            'segment_type' => 'branch', 'branch_id' => $this->branch->id,
        ])->assertRedirect();
        $campaign = WaCampaign::where('name', 'Segmen Pusat')->firstOrFail();
        $this->assertSame('branch', $campaign->segment['type']);
        $this->assertSame($this->branch->id, (int) $campaign->segment['branch_id']);

        $recipients = app(CampaignService::class)->resolveRecipients($campaign);
        $this->assertTrue($recipients->contains('id', $this->customer->id));

        // Batalkan dari draft.
        $this->actingAs($this->owner)->post('/wa/kampanye/'.$campaign->id.'/batalkan')->assertRedirect();
        $this->assertSame('cancelled', $campaign->fresh()->status);

        // Persetujuan via HTTP oleh Admin ditolak middleware (403).
        $campaign2 = app(CampaignService::class)->create($this->owner, 'Kedua', $template->id, ['type' => 'all']);
        $this->actingAs($this->admin)->post('/wa/kampanye/'.$campaign2->id.'/setujui')->assertForbidden();
    }
}
