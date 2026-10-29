<?php

namespace Tests\Feature;

use App\Modules\Catalog\Services\ProductService;
use App\Modules\Comms\Models\WaMessage;
use App\Modules\Comms\Services\WaAutomationService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\UserService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanGate;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * M4 — Penegakan paket di server: fitur boolean digerbang middleware/
 * atribut (403), batas angka digerbang layanan pembuat entitas, ekspor
 * laporan & token API ikut paket, otomasi WA berhenti untuk tenant
 * tanpa fitur, dan halaman Paket Saya merangkum semuanya. Aktor tanpa
 * tenant (instalasi mandiri) TIDAK terpengaruh sama sekali.
 */
class SaasEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $owner;

    private PlanService $plans;

    private PlanGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, WaTemplateSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->plans = app(PlanService::class);
        $this->gate = app(PlanGate::class);
    }

    /** Tenant + cabang + admin (peran Owner) yang terikat tenant itu. */
    private function tenantAdmin(string $planKey): array
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$planKey, 'slug' => 'optik-'.$planKey,
            'email' => $planKey.'@uji.id',
        ]);
        $this->plans->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
            null,
            $this->platform,
        );
        $branch = Branch::create(['name' => 'Cabang '.$planKey, 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin '.$planKey, 'username' => 'admin-'.$planKey,
            'email' => 'admin-'.$planKey.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $branch, $admin];
    }

    public function test_legacy_actor_without_tenant_is_unrestricted(): void
    {
        $this->assertTrue($this->gate->enabled($this->owner, 'whatsapp'));
        $this->assertTrue($this->gate->enabled($this->owner, 'purchasing_full'));
        $this->assertNull($this->gate->limit($this->owner, 'products_max'));
        $this->assertNull($this->gate->limit($this->owner, 'report_history_days'));

        $this->actingAs($this->owner)->get('/pembelian/pr')->assertOk();
        $this->actingAs($this->owner)->get('/pengaturan/wa')->assertOk();
        $this->actingAs($this->owner)->get('/paket-saya')
            ->assertOk()->assertSee('Instalasi Mandiri');
    }

    public function test_free_plan_default_limits_via_gate(): void
    {
        [, , $admin] = $this->tenantAdmin('free');

        $this->assertSame(500, $this->gate->limit($admin, 'products_max'));
        $this->assertSame(1, $this->gate->limit($admin, 'users_max'));
        $this->assertSame(90, $this->gate->limit($admin, 'report_history_days'));
        $this->assertFalse($this->gate->enabled($admin, 'whatsapp'));
    }

    public function test_free_tenant_feature_routes_are_forbidden(): void
    {
        [, , $admin] = $this->tenantAdmin('free');
        $role = Role::where('name', 'Kasir')->firstOrFail();

        $this->actingAs($admin)->get('/pembelian/pr')->assertForbidden();
        $this->actingAs($admin)->get('/pengaturan/wa')->assertForbidden();
        $this->actingAs($admin)->get('/pengaturan/token-api')->assertForbidden();
        $this->actingAs($admin)->get('/member')->assertForbidden();
        $this->actingAs($admin)->get('/peran/'.$role->id.'/ubah')->assertForbidden();
        $this->actingAs($admin)->post('/jurnal', [])->assertForbidden();
        $this->actingAs($admin)->get('/laporan/neraca')->assertForbidden();
    }

    public function test_best_offer_tenant_passes_feature_gates(): void
    {
        [, , $admin] = $this->tenantAdmin('best_offer');

        $this->actingAs($admin)->get('/pembelian/pr')->assertOk();
        $this->actingAs($admin)->get('/pengaturan/wa')->assertOk();
        $this->actingAs($admin)->get('/pengaturan/token-api')->assertOk();
        $this->actingAs($admin)->get('/member')->assertOk();
    }

    public function test_reports_html_open_but_export_locked_and_history_clamped_for_free(): void
    {
        [, , $admin] = $this->tenantAdmin('free');

        $this->actingAs($admin)->get('/laporan/penjualan')->assertOk();
        $this->actingAs($admin)->get('/laporan/penjualan?ekspor=1')->assertForbidden();

        $expectedFrom = now()->subDays(89)->format('Y-m-d');
        $this->actingAs($admin)
            ->get('/laporan/penjualan?dari=2020-01-01&sampai='.now()->format('Y-m-d'))
            ->assertOk()
            ->assertViewHas('from', $expectedFrom);
    }

    public function test_product_limit_enforced_and_products_stamped_with_tenant(): void
    {
        [$tenant, , $admin] = $this->tenantAdmin('free');
        $this->plans->setOverride($tenant, 'products_max', ['limit_value' => 1], null, 'Uji batas', $this->platform);

        $service = app(ProductService::class);
        $product = $service->create($admin, [
            'sku' => 'FR-UJI-1', 'type' => 'frame', 'name' => 'Frame Uji',
            'cost_price' => '100000', 'sale_price' => '150000',
        ]);
        $this->assertSame($tenant->id, $product->tenant_id);
        $this->assertSame(1, $this->gate->usage($admin, 'products_max'));

        $this->expectException(ValidationException::class);
        $service->create($admin, [
            'sku' => 'FR-UJI-2', 'type' => 'frame', 'name' => 'Frame Uji 2',
            'cost_price' => '100000', 'sale_price' => '150000',
        ]);
    }

    public function test_user_limit_enforced_and_created_user_inherits_tenant(): void
    {
        [$tenant, , $admin] = $this->tenantAdmin('free');
        $service = app(UserService::class);

        // Paket Free: users_max=1 dan admin sendiri sudah memakainya.
        try {
            $service->createUser([
                'name' => 'Kedua', 'username' => 'kedua', 'password' => 'rahasia-uji',
            ], $admin);
            $this->fail('Pembuatan akun kedua seharusnya ditolak batas paket.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->plans->setOverride($tenant, 'users_max', ['limit_value' => 3], null, 'Uji waris tenant', $this->platform);
        $created = $service->createUser([
            'name' => 'Kedua', 'username' => 'kedua', 'password' => 'rahasia-uji',
        ], $admin);
        $this->assertSame($tenant->id, $created->tenant_id);
    }

    public function test_branch_limit_enforced_over_http(): void
    {
        [, , $admin] = $this->tenantAdmin('free');

        // Free: branches_max=1 dan cabang tenant sudah ada satu.
        $this->actingAs($admin)
            ->post('/cabang', ['name' => 'Cabang Kedua'])
            ->assertSessionHasErrors('plan');
        $this->assertSame(0, Branch::where('name', 'Cabang Kedua')->count());
    }

    public function test_api_token_rejected_when_plan_lacks_api_access(): void
    {
        [, , $freeAdmin] = $this->tenantAdmin('free');
        [, , $bestAdmin] = $this->tenantAdmin('best_offer');
        $tokens = app(ApiTokenService::class);

        $freeToken = $tokens->issue($freeAdmin, 'Uji Free', ['products.read']);
        $this->withHeaders(['Authorization' => 'Bearer '.$freeToken['plain']])
            ->getJson('/api/v1/products')
            ->assertForbidden();

        $bestToken = $tokens->issue($bestAdmin, 'Uji Best', ['products.read']);
        $this->withHeaders(['Authorization' => 'Bearer '.$bestToken['plain']])
            ->getJson('/api/v1/products')
            ->assertOk();

        $legacyToken = $tokens->issue($this->owner, 'Uji Mandiri', ['products.read']);
        $this->withHeaders(['Authorization' => 'Bearer '.$legacyToken['plain']])
            ->getJson('/api/v1/products')
            ->assertOk();
    }

    public function test_wa_automation_skips_tenant_without_whatsapp_feature(): void
    {
        [, $freeBranch] = $this->tenantAdmin('free');
        Customer::factory()->create([
            'branch_id' => $freeBranch->id, 'name' => 'Ulang Tahun Free',
            'phone' => '081200000001', 'birth_date' => today()->format('Y-m-d'), 'is_active' => true,
        ]);
        $legacyBranch = Branch::where('name', 'Pusat')->firstOrFail();
        Customer::factory()->create([
            'branch_id' => $legacyBranch->id, 'name' => 'Ulang Tahun Mandiri',
            'phone' => '081200000002', 'birth_date' => today()->format('Y-m-d'), 'is_active' => true,
        ]);

        $sent = app(WaAutomationService::class)->runBirthdays();

        $this->assertSame(1, $sent);
        $this->assertSame(1, WaMessage::count());
    }

    public function test_my_plan_page_shows_tenant_plan_and_usage(): void
    {
        [, , $admin] = $this->tenantAdmin('free');

        $this->actingAs($admin)->get('/paket-saya')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('500');
    }
}
