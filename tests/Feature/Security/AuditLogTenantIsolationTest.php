<?php

namespace Tests\Feature\Security;

use App\Modules\Identity\Models\Permission;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\AuditLogger;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L-01 — Audit log per tenant (audit menyeluruh A-04).
 *
 * Membuktikan: (1) AuditLogger mencap tenant_id dari konteks
 * tervalidasi aktor efektif — parameter aktor mengalahkan sesi
 * ambient, aktor platform/standalone/sistem ber-cap NULL;
 * (2) aktor tenant melihat log tenantnya SENDIRI termasuk yang
 * tanpa cabang (kasus products.create yang dulu tak terlihat),
 * dan tidak pernah log tenant lain — di daftar maupun detail;
 * (3) baris warisan tenant_id NULL hanya terjangkau lewat cabang
 * terkelola aktor; (4) admin tenant terpagar cabang melihat log
 * tanpa cabang tenantnya tetapi tidak log cabang lain.
 */
class AuditLogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
    }

    /** @return array{0: Tenant, 1: Branch, 2: User} */
    private function tenantAdmin(string $tag): array
    {
        $tenant = Tenant::create(['name' => 'Optik '.$tag, 'slug' => 'optik-'.strtolower($tag), 'email' => strtolower($tag).'@uji.id']);
        $platform = User::where('username', 'platform')->firstOrFail();
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', 'best_offer')->firstOrFail(),
            null,
            $platform,
        );
        $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Pusat '.$tag, 'code' => 'PST-'.$tag]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->roles()->sync([Role::where('name', 'Owner')->firstOrFail()->id]);
        $user->branches()->sync([$branch->id]);

        return [$tenant, $branch, $user];
    }

    public function test_logger_stamps_tenant_from_the_validated_actor_context(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');
        [, , $adminB] = $this->tenantAdmin('B');
        $logger = app(AuditLogger::class);

        $logger->log(action: 'audit-a04.produk-a', subjectType: 'product', actor: $adminA);
        $this->assertSame($tenantA->id, AuditLog::where('action', 'audit-a04.produk-a')->firstOrFail()->tenant_id);

        // Parameter aktor mengalahkan sesi ambient.
        $this->actingAs($adminB);
        $logger->log(action: 'audit-a04.ambient', subjectType: 'product', actor: $adminA);
        $this->assertSame($tenantA->id, AuditLog::where('action', 'audit-a04.ambient')->firstOrFail()->tenant_id);

        // Aktor platform & standalone: cap NULL.
        $platform = User::where('username', 'platform')->firstOrFail();
        $logger->log(action: 'audit-a04.platform', subjectType: 'tenant', actor: $platform);
        $this->assertNull(AuditLog::where('action', 'audit-a04.platform')->firstOrFail()->tenant_id);

        $owner = User::where('username', 'owner')->firstOrFail();
        $logger->log(action: 'audit-a04.mandiri', subjectType: 'product', actor: $owner);
        $this->assertNull(AuditLog::where('action', 'audit-a04.mandiri')->firstOrFail()->tenant_id);
    }

    public function test_real_product_creation_log_is_stamped_and_visible_to_its_tenant(): void
    {
        [$tenantA, , $adminA] = $this->tenantAdmin('A');

        // Jalur bisnis nyata: products.create ditulis TANPA
        // branch_id — persis catatan yang hilang dari pandangan
        // tenant sebelum L-01.
        $this->actingAs($adminA)->post('/katalog/frame', [
            'sku' => 'FR-L01', 'name' => 'Frame L01',
            'cost_price' => '100000', 'sale_price' => '200000',
        ])->assertRedirect();

        $log = AuditLog::where('action', 'products.create')->firstOrFail();
        $this->assertSame($tenantA->id, $log->tenant_id);
        $this->assertNull($log->branch_id);

        $this->actingAs($adminA)->get(route('audit-logs.index'))
            ->assertOk()->assertSee('products.create');
        $this->actingAs($adminA)->get(route('audit-logs.show', $log))->assertOk();
    }

    /** @return array<string, AuditLog> */
    private function seedTwoTenantLogs(): array
    {
        [$tenantA, $branchA, $adminA] = $this->tenantAdmin('A');
        [$tenantB, $branchB, $adminB] = $this->tenantAdmin('B');
        $logger = app(AuditLogger::class);

        $logs = [];
        $logs['produkA'] = $logger->log(action: 'audit-a04.produk-a', subjectType: 'product', actor: $adminA);
        $logs['jualA'] = $logger->log(action: 'audit-a04.jual-a', subjectType: 'sale', branchId: $branchA->id, actor: $adminA);
        $logs['produkB'] = $logger->log(action: 'audit-a04.produk-b', subjectType: 'product', actor: $adminB);
        $logs['jualB'] = $logger->log(action: 'audit-a04.jual-b', subjectType: 'sale', branchId: $branchB->id, actor: $adminB);
        // Baris warisan pra-L-01: tenant_id NULL, cabang milik A.
        $logs['warisanA'] = AuditLog::create([
            'user_id' => $adminA->id, 'action' => 'audit-a04.warisan-a', 'subject_type' => 'sale',
            'subject_id' => 1, 'branch_id' => $branchA->id, 'new_values' => [],
        ]);

        return $logs + ['adminA' => $adminA, 'adminB' => $adminB, 'tenantA' => $tenantA, 'branchA' => $branchA];
    }

    public function test_tenant_owner_sees_own_logs_including_branchless_and_never_other_tenants(): void
    {
        $f = $this->seedTwoTenantLogs();

        $this->actingAs($f['adminA'])->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('audit-a04.produk-a')
            ->assertSee('audit-a04.jual-a')
            ->assertSee('audit-a04.warisan-a')
            ->assertDontSee('audit-a04.produk-b')
            ->assertDontSee('audit-a04.jual-b');

        $this->actingAs($f['adminB'])->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('audit-a04.produk-b')
            ->assertSee('audit-a04.jual-b')
            ->assertDontSee('audit-a04.produk-a')
            ->assertDontSee('audit-a04.jual-a')
            ->assertDontSee('audit-a04.warisan-a');
    }

    public function test_log_detail_is_fenced_per_tenant(): void
    {
        $f = $this->seedTwoTenantLogs();

        $this->actingAs($f['adminA'])->get(route('audit-logs.show', $f['produkA']))->assertOk();
        $this->actingAs($f['adminA'])->get(route('audit-logs.show', $f['warisanA']))->assertOk();
        $this->actingAs($f['adminA'])->get(route('audit-logs.show', $f['produkB']))->assertForbidden();
        $this->actingAs($f['adminA'])->get(route('audit-logs.show', $f['jualB']))->assertForbidden();
        $this->actingAs($f['adminB'])->get(route('audit-logs.show', $f['warisanA']))->assertForbidden();
    }

    public function test_branch_scoped_tenant_admin_sees_branchless_own_logs_within_scope(): void
    {
        $f = $this->seedTwoTenantLogs();
        $tenantA = $f['tenantA'];
        $branchA2 = Branch::create(['tenant_id' => $tenantA->id, 'name' => 'Kedua A', 'code' => 'KDA']);
        app(AuditLogger::class)->log(action: 'audit-a04.jual-a2', subjectType: 'sale', branchId: $branchA2->id, actor: $f['adminA']);
        $logA2 = AuditLog::where('action', 'audit-a04.jual-a2')->firstOrFail();

        $role = Role::create(['name' => 'PengaturanCabangL01', 'label' => 'Pengaturan Cabang L01']);
        $role->permissions()->sync(Permission::whereIn('name', ['dashboard.view', 'settings.manage'])->pluck('id'));
        $scoped = User::factory()->create(['tenant_id' => $tenantA->id]);
        $scoped->roles()->sync([$role->id]);
        $scoped->branches()->sync([$f['branchA']->id]);

        $this->actingAs($scoped)->get(route('audit-logs.index'))
            ->assertOk()
            ->assertSee('audit-a04.produk-a')
            ->assertSee('audit-a04.jual-a')
            ->assertDontSee('audit-a04.jual-a2')
            ->assertDontSee('audit-a04.produk-b');

        $this->actingAs($scoped)->get(route('audit-logs.show', $logA2))->assertForbidden();
        $this->actingAs($scoped)->get(route('audit-logs.show', $f['produkA']))->assertOk();
    }
}
