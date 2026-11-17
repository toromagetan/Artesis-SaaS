<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Http\Controllers\WaCampaignController;
use App\Modules\Comms\Models\WaCampaign;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Audit W-01 W1-1: kampanye WA milik tenant pembuatnya. Celah lama:
 * wa_campaigns tanpa tenant_id dan resolusi penerima bergantung pada
 * TenantScope ambien, sehingga job pengiriman (konteks sistem)
 * menjangkau pelanggan tenant lain — test isolasi lama lolos karena
 * deliver() dipanggil dalam konteks aktor, bukan lewat job.
 */
class WaCampaignTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private WaTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->template = WaTemplate::create([
            'key' => 'campaign', 'body' => 'Promo {nama}', 'approved_by' => $this->platform->id,
        ]);
    }

    /** @return array{Tenant, User, Branch} */
    private function tenantOwner(string $tag): array
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

        return [$tenant, $owner, $branch];
    }

    private function customer(Tenant $tenant, Branch $branch, string $name, string $phone): Customer
    {
        return Customer::forceCreate([
            'name' => $name, 'phone' => $phone, 'tenant_id' => $tenant->id,
            'branch_id' => $branch->id, 'is_active' => true, 'wa_opt_out' => false,
        ]);
    }

    public function test_create_stamps_actor_tenant_and_platform_stays_global(): void
    {
        [$tenantA, $ownerA] = $this->tenantOwner('kst');
        $service = app(CampaignService::class);

        $own = $service->create($ownerA, 'Promo A', $this->template->id, ['type' => 'all']);
        $this->assertSame($tenantA->id, $own->tenant_id);

        $global = $service->create($this->platform, 'Pengumuman Platform', $this->template->id, ['type' => 'all']);
        $this->assertNull($global->tenant_id);
    }

    public function test_job_delivery_reaches_only_campaign_tenant_customers(): void
    {
        [, $ownerA, $branchA] = $this->tenantOwner('kja');
        [$tenantB, , $branchB] = $this->tenantOwner('kjb');
        $custA = $this->customer($tenantA = $ownerA->tenant, $branchA, 'Pelanggan A', '08120000011');
        $custB = $this->customer($tenantB, $branchB, 'Pelanggan B', '08120000012');

        $service = app(CampaignService::class);
        $campaign = $service->create($ownerA, 'Promo A', $this->template->id, ['type' => 'all']);
        $service->approve($ownerA, $campaign);
        // Antrean sinkron pada test: SendCampaign berjalan seketika
        // dalam konteks sistem — persis jalur job produksi.
        $service->requestSend($ownerA, $campaign);

        $this->assertTrue(WaMessage::where('campaign_id', $campaign->id)->where('customer_id', $custA->id)->exists());
        $this->assertFalse(WaMessage::where('campaign_id', $campaign->id)->where('customer_id', $custB->id)->exists());
        $this->assertSame(1, WaCampaign::find($campaign->id)->recipient_count);
    }

    public function test_foreign_branch_segment_yields_no_recipients_even_under_system_delivery(): void
    {
        [, $ownerA, $branchA] = $this->tenantOwner('kfa');
        [$tenantB, , $branchB] = $this->tenantOwner('kfb');
        $custB = $this->customer($tenantB, $branchB, 'Pelanggan FB', '08120000013');

        $service = app(CampaignService::class);
        // Kampanye warisan / terbentuk di luar jalur normal yang
        // segmennya menunjuk cabang tenant lain.
        $campaign = $service->create($ownerA, 'Segmen Asing', $this->template->id, [
            'type' => 'branch', 'branch_id' => $branchB->id,
        ]);
        $service->approve($ownerA, $campaign);
        $service->requestSend($ownerA, $campaign);

        $this->assertFalse(WaMessage::where('campaign_id', $campaign->id)->where('customer_id', $custB->id)->exists());
        $this->assertSame(0, WaMessage::where('campaign_id', $campaign->id)->count());
    }

    public function test_platform_campaign_still_reaches_all_tenants_by_design(): void
    {
        [$tenantA, , $branchA] = $this->tenantOwner('kpa');
        [$tenantB, , $branchB] = $this->tenantOwner('kpb');
        $custA = $this->customer($tenantA, $branchA, 'Pelanggan PA', '08120000014');
        $custB = $this->customer($tenantB, $branchB, 'Pelanggan PB', '08120000015');

        $service = app(CampaignService::class);
        $campaign = $service->create($this->platform, 'Pengumuman', $this->template->id, ['type' => 'all']);
        // Persetujuan oleh pemilik instalasi mandiri (peran Owner
        // berizin wa.campaigns.approve; peran Platform memang tidak
        // memegang izin WA secara desain).
        $standalone = User::where('username', 'owner')->firstOrFail();
        $service->approve($standalone, $campaign);
        $service->requestSend($standalone, $campaign);

        $this->assertTrue(WaMessage::where('campaign_id', $campaign->id)->where('customer_id', $custA->id)->exists());
        $this->assertTrue(WaMessage::where('campaign_id', $campaign->id)->where('customer_id', $custB->id)->exists());
    }

    public function test_controller_fences_other_tenant_campaign(): void
    {
        [, $ownerA] = $this->tenantOwner('kca');
        [, $ownerB] = $this->tenantOwner('kcb');
        $service = app(CampaignService::class);
        $foreign = $service->create($ownerB, 'Promo B', $this->template->id, ['type' => 'all']);
        $own = $service->create($ownerA, 'Promo A', $this->template->id, ['type' => 'all']);

        $controller = app(WaCampaignController::class);
        $reqA = Request::create('/');
        $reqA->setUserResolver(fn () => $ownerA);

        $this->assertInstanceOf(View::class, $controller->show($reqA, $own));

        try {
            $controller->show($reqA, $foreign);
            $this->fail('Detail kampanye tenant lain harus 404.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }

        try {
            $controller->approve($reqA, $foreign);
            $this->fail('Setujui kampanye tenant lain harus 404.');
        } catch (NotFoundHttpException) {
            $this->addToAssertionCount(1);
        }

        // Daftar kampanye aktor hanya memuat embernya sendiri.
        $view = $controller->index($reqA);
        $listed = $view->getData()['campaigns'];
        $this->assertTrue($listed->contains('id', $own->id));
        $this->assertFalse($listed->contains('id', $foreign->id));
    }
}
