<?php

namespace Tests\Feature\Comms;

use App\Modules\Comms\Jobs\SendWhatsAppMessage;
use App\Modules\Comms\Models\WaGateway;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Comms\Services\CampaignService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ketahanan antrean WA (audit 23 Okt 2026 C1+C2): sapu antrean
 * mendispatch ulang pesan queued yang menggantung hanya bila
 * gateway aktif; pengiriman kampanye dapat dilanjutkan tanpa
 * mengirim ganda ke penerima yang sudah terpesan.
 */
class WaQueueResilienceTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, WaTemplateSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branch = Branch::where('name', 'Pusat')->firstOrFail();
    }

    private function staleQueuedMessage(Customer $customer): WaMessage
    {
        $message = WaMessage::create([
            'customer_id' => $customer->id, 'phone' => '6281234567890', 'template_key' => 'invoice',
            'body' => 'Invoice lama', 'direction' => 'out', 'status' => 'queued', 'trigger' => 'invoice',
        ]);
        $message->forceFill(['created_at' => now()->subHour()])->save();

        return $message;
    }

    public function test_sweep_redispatches_only_when_gateway_active(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create(['branch_id' => $this->branch->id, 'phone' => '081234567890']);
        $this->staleQueuedMessage($customer);

        // Tanpa gateway: sapu tidak mendispatch apa pun (pesan tetap
        // tercatat queued menunggu konfigurasi).
        Artisan::call('lentera:wa-sapu');
        Queue::assertNotPushed(SendWhatsAppMessage::class);

        WaGateway::create([
            'provider' => 'meta_cloud_api', 'sender_number' => '6281234567890', 'is_active' => true,
            'credentials_encrypted' => ['phone_number_id' => '1', 'access_token' => 'x', 'api_version' => 'v21.0'],
        ]);
        Artisan::call('lentera:wa-sapu');
        Queue::assertPushed(SendWhatsAppMessage::class, 1);
    }

    public function test_campaign_delivery_resumes_without_duplicates(): void
    {
        $service = app(CampaignService::class);
        $template = WaTemplate::where('key', 'campaign')->firstOrFail();
        $template->forceFill(['approved_by' => $this->owner->id])->save();
        $customers = Customer::factory()->count(3)->create(['branch_id' => $this->branch->id, 'phone' => '081234567890']);
        $campaign = $service->create($this->owner, 'Promo Uji', $template->id, ['type' => 'all']);
        $campaign->forceFill(['status' => 'sending', 'approved_by' => $this->owner->id])->save();

        // Simulasi job pertama mati sesudah penerima pertama.
        WaMessage::create([
            'customer_id' => $customers[0]->id, 'phone' => '6281234567890', 'template_key' => 'campaign',
            'body' => 'Promo', 'direction' => 'out', 'status' => 'queued', 'trigger' => 'campaign',
            'campaign_id' => $campaign->id,
        ]);

        $service->deliver($campaign);

        $campaign->refresh();
        $this->assertSame('done', $campaign->status);
        $this->assertSame(3, WaMessage::where('campaign_id', $campaign->id)->count());
        $this->assertSame(3, $campaign->recipient_count);
        // Jalan ulang sesudah selesai: tidak ada pesan baru.
        $campaign->forceFill(['status' => 'sending'])->save();
        $service->deliver($campaign);
        $this->assertSame(3, WaMessage::where('campaign_id', $campaign->id)->count());
    }
}
