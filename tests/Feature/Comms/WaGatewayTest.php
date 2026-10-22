<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Exceptions\GatewayException;
use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\GatewayFactory;
use App\Modules\Comms\Services\MessagingService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * F3.4 Gateway WA: pengaturan kredensial terenkripsi via UI, uji
 * kirim, antarmuka MessagingGateway + Meta Cloud API, log 100%,
 * opt-out dihormati, webhook verifikasi & status.
 */
class WaGatewayTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $kasir;

    private User $gudang;

    private Branch $branch;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, WaTemplateSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();

        $this->kasir = User::factory()->create();
        $this->kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $this->kasir->branches()->sync([$this->branch->id]);

        $this->gudang = User::factory()->create();
        $this->gudang->roles()->sync([Role::where('name', 'gudang')->firstOrFail()->id]);
        $this->gudang->branches()->sync([$this->branch->id]);

        $this->customer = Customer::factory()->create([
            'branch_id' => $this->branch->id, 'name' => 'Budi Waluyo', 'phone' => '0812-3456-7890',
        ]);
    }

    private function activeGateway(array $credentials = []): WaGateway
    {
        return WaGateway::create([
            'provider' => 'meta_cloud_api',
            'sender_number' => '6281234567890',
            'credentials_encrypted' => array_merge([
                'phone_number_id' => '1234567890',
                'waba_id' => '99887766',
                'access_token' => 'EAAB-secret-token-9999',
                'verify_token' => 'lentera-verify',
                'app_secret' => 'test-app-secret',
                'api_version' => 'v21.0',
            ], $credentials),
            'is_active' => true,
        ]);
    }

    public function test_settings_ui_saves_credentials_encrypted_and_masks_token(): void
    {
        $this->actingAs($this->kasir)->get(route('wa-gateway.edit'))->assertForbidden();

        $this->actingAs($this->owner)->put(route('wa-gateway.update'), [
            'provider' => 'meta_cloud_api',
            'sender_number' => '0812-3456-7890',
            'phone_number_id' => '1234567890',
            'waba_id' => '99887766',
            'access_token' => 'EAAB-secret-token-9999',
            'verify_token' => 'lentera-verify',
            'api_version' => 'v21.0',
            'is_active' => '1',
        ])->assertRedirect(route('wa-gateway.edit'));

        $raw = (string) DB::table('wa_gateways')->value('credentials_encrypted');
        $this->assertStringNotContainsString('EAAB-secret-token-9999', $raw);
        $gateway = WaGateway::firstOrFail();
        $this->assertSame('6281234567890', $gateway->sender_number);
        $this->assertSame('EAAB-secret-token-9999', $gateway->credential('access_token'));
        $this->assertTrue($gateway->is_active);

        // Halaman pengaturan: token hanya tersamar, tidak pernah penuh.
        $this->actingAs($this->owner)->get(route('wa-gateway.edit'))
            ->assertOk()
            ->assertSee('••••9999')
            ->assertDontSee('EAAB-secret-token-9999');

        // Simpan ulang tanpa token -> token lama dipertahankan.
        $this->actingAs($this->owner)->put(route('wa-gateway.update'), [
            'provider' => 'meta_cloud_api',
            'sender_number' => '6281234567890',
            'phone_number_id' => '1234567890',
            'access_token' => '',
            'is_active' => '1',
        ])->assertRedirect(route('wa-gateway.edit'));
        $this->assertSame('EAAB-secret-token-9999', WaGateway::firstOrFail()->credential('access_token'));
    }

    public function test_test_send_goes_through_meta_api_and_is_logged(): void
    {
        $this->activeGateway();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST1']]], 200),
        ]);

        $this->actingAs($this->owner)->post(route('wa-gateway.test'), [
            'test_phone' => '0857-1111-2222',
        ])->assertRedirect();

        $message = WaMessage::where('trigger', 'test')->firstOrFail();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.TEST1', $message->provider_message_id);
        $this->assertSame('6285711112222', $message->phone);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v21.0/1234567890/messages')
            && $request->hasHeader('Authorization', 'Bearer EAAB-secret-token-9999'));
    }

    public function test_test_send_failure_is_logged_as_failed(): void
    {
        $this->activeGateway();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401),
        ]);

        $this->actingAs($this->owner)->post(route('wa-gateway.test'), ['test_phone' => '085711112222'])
            ->assertRedirect()
            ->assertSessionHas('test_result');

        $message = WaMessage::where('trigger', 'test')->firstOrFail();
        $this->assertSame('failed', $message->status);
        $this->assertStringContainsString('Invalid OAuth', (string) $message->error);
    }

    public function test_messaging_service_renders_template_and_job_delivers(): void
    {
        $this->activeGateway();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.INV1']]], 200),
        ]);

        $message = app(MessagingService::class)->send('invoice', $this->customer, [
            'nomor' => 'INV-202610-0001', 'total' => 'Rp2.386.500', 'cabang' => 'Pusat',
        ], 'invoice', $this->owner);

        $this->assertSame('queued', $message->status);
        $this->assertSame('6281234567890', $message->phone);
        $this->assertStringContainsString('Budi Waluyo', $message->body);
        $this->assertStringContainsString('INV-202610-0001', $message->body);

        (new SendWhatsAppMessage($message->id))->handle(app(GatewayFactory::class));

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.INV1', $message->provider_message_id);
        $this->assertNotNull($message->sent_at);
    }

    public function test_without_active_gateway_message_stays_queued_and_nothing_is_sent(): void
    {
        Http::fake();
        $message = app(MessagingService::class)->send('invoice', $this->customer, ['nomor' => 'INV-1'], 'invoice');

        (new SendWhatsAppMessage($message->id))->handle(app(GatewayFactory::class));

        $this->assertSame('queued', $message->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_opt_out_customer_is_never_sent_but_is_logged(): void
    {
        $this->activeGateway();
        Http::fake();
        $this->customer->update(['wa_opt_out' => true]);

        $message = app(MessagingService::class)->send('invoice', $this->customer, ['nomor' => 'INV-2'], 'invoice');

        $this->assertSame('failed', $message->status);
        $this->assertSame('opt_out', $message->error);
        Http::assertNothingSent();
    }

    public function test_gateway_failure_records_error_retries_then_fails_without_throwing_to_caller(): void
    {
        $this->activeGateway();
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Service unavailable']], 503),
        ]);

        // (1) Pemanggil tidak pernah dilempar: send() kembali normal,
        // siklus antrean berjalan (driver sync di test mengeksekusi
        // seketika) sampai percobaan terakhir dan tercatat 'failed'.
        $message = app(MessagingService::class)->send('invoice', $this->customer, ['nomor' => 'INV-3'], 'invoice');
        $this->assertSame('failed', $message->fresh()->status);
        $this->assertStringContainsString('Service unavailable', (string) $message->fresh()->error);

        // (2) Kontrak job per percobaan atas pesan 'queued' yang belum
        // diproses antrean: error dicatat, status tetap 'queued',
        // exception dilempar agar antrean mencoba lagi; failed() menutup.
        $queued = WaMessage::create([
            'customer_id' => $this->customer->id, 'phone' => '6281234567890', 'template_key' => 'invoice',
            'body' => 'uji', 'direction' => 'out', 'status' => 'queued', 'trigger' => 'invoice',
        ]);
        $job = new SendWhatsAppMessage($queued->id);
        try {
            $job->handle(app(GatewayFactory::class));
            $this->fail('kegagalan gateway harus melempar GatewayException agar antrean mencoba lagi');
        } catch (GatewayException $e) {
            $this->assertSame('queued', $queued->fresh()->status);
            $this->assertStringContainsString('Service unavailable', (string) $queued->fresh()->error);
            $job->failed($e);
        }
        $this->assertSame('failed', $queued->fresh()->status);
    }

    public function test_templates_ui_and_log_page_permissions(): void
    {
        $this->activeGateway();
        $message = app(MessagingService::class)->sendText($this->customer, 'Halo uji', 'manual', $this->owner);

        // Template: hanya pemegang wa.templates (Owner); kasir tertolak.
        $this->actingAs($this->kasir)->get(route('wa-templates.index'))->assertForbidden();
        $this->actingAs($this->owner)->get(route('wa-templates.index'))->assertOk()->assertSee('invoice');
        $template = WaTemplate::where('key', 'invoice')->firstOrFail();
        $this->actingAs($this->owner)->put(route('wa-templates.update', $template), [
            'body' => 'Halo {nama}, invoice {nomor} siap.',
        ])->assertRedirect(route('wa-templates.index'));
        $this->assertSame('Halo {nama}, invoice {nomor} siap.', $template->fresh()->body);

        // Log: kasir (wa.send) boleh, gudang tidak.
        $this->actingAs($this->kasir)->get(route('wa-log.index'))->assertOk()->assertSee('6281234567890');
        $this->actingAs($this->gudang)->get(route('wa-log.index'))->assertForbidden();
        $this->assertSame('queued', $message->status);
    }

    public function test_webhook_verification_and_delivery_status(): void
    {
        $this->activeGateway();

        $this->get(route('wa-webhook.verify').'?hub_mode=subscribe&hub_verify_token=lentera-verify&hub_challenge=CH-42')
            ->assertOk()->assertSee('CH-42');
        $this->get(route('wa-webhook.verify').'?hub_mode=subscribe&hub_verify_token=salah&hub_challenge=CH-42')
            ->assertForbidden();

        $message = WaMessage::create([
            'customer_id' => $this->customer->id, 'phone' => '6281234567890', 'template_key' => 'invoice',
            'body' => 'uji', 'direction' => 'out', 'status' => 'sent', 'trigger' => 'invoice',
            'provider_message_id' => 'wamid.DELIV1',
        ]);
        $payload = [
            'entry' => [['changes' => [['value' => [
                'statuses' => [['id' => 'wamid.DELIV1', 'status' => 'delivered']],
                'messages' => [[
                    'id' => 'wamid.IN1', 'from' => '6281234567890', 'type' => 'text',
                    'text' => ['body' => 'Terima kasih'],
                ]],
            ]]]]],
        ];
        $this->signedWebhookPost($payload, 'test-app-secret')->assertOk();

        $this->assertSame('delivered', $message->fresh()->status);
        $incoming = WaMessage::where('direction', 'in')->first();
        $this->assertNotNull($incoming);
        $this->assertSame('Terima kasih', $incoming->body);
        $this->assertSame($this->customer->id, $incoming->customer_id);
    }

    /** Audit 23 Okt 2026 (A1): POST webhook tanpa tanda tangan App
     *  Secret yang sah harus ditolak sebelum payload diproses. */
    public function test_webhook_post_rejects_unsigned_forged_and_unconfigured(): void
    {
        $this->activeGateway();
        $payload = [
            'entry' => [['changes' => [['value' => [
                'messages' => [[
                    'id' => 'wamid.FORGED1', 'from' => '6281234567890', 'type' => 'text',
                    'text' => ['body' => 'Pesan palsu'],
                ]],
            ]]]]],
        ];

        // Tanpa tanda tangan sama sekali.
        $this->postJson(route('wa-webhook.handle'), $payload)->assertForbidden();
        // Tanda tangan dengan secret yang salah.
        $this->signedWebhookPost($payload, 'secret-yang-salah')->assertForbidden();
        $this->assertSame(0, WaMessage::where('direction', 'in')->count());

        // Gateway tanpa app_secret → fail-closed walau "ditandatangani".
        WaGateway::query()->delete();
        $this->activeGateway(['app_secret' => '']);
        $this->signedWebhookPost($payload, 'test-app-secret')->assertForbidden();
        $this->assertSame(0, WaMessage::where('direction', 'in')->count());
    }

    /** Kirim POST webhook dengan header X-Hub-Signature-256 yang benar
     *  untuk secret yang diberikan (badan = JSON payload persis). */
    private function signedWebhookPost(array $payload, string $secret): TestResponse
    {
        $json = json_encode($payload);
        $signature = 'sha256='.hash_hmac('sha256', $json, $secret);

        return $this->call('POST', route('wa-webhook.handle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $json);
    }
}
