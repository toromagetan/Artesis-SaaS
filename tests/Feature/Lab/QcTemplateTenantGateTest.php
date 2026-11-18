<?php

namespace Tests\Feature\Lab;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Lab\Models\QcPointTemplate;
use App\Modules\Lab\Services\QcTemplateService;
use App\Modules\Master\Models\Branch;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ISO-3 (audit isolasi menyeluruh 18 Nov 2026): template titik QC
 * adalah rekaman bersama se-instalasi — aktor tenant TIDAK PERNAH
 * boleh membuat/mengubah/menonaktifkannya (satu tenant tidak boleh
 * mengubah daftar periksa lab tenant lain). Pengelolaan hanya
 * untuk aktor platform dan instalasi mandiri. Aktor tenant tetap
 * membaca daftar titik dan memakai titik aktif di formulir QC.
 */
class QcTemplateTenantGateTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{0: Tenant, 1: User} */
    private function makeTenantOwner(string $tag): array
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
        $owner = User::forceCreate([
            'name' => 'Owner '.$tag, 'username' => 'owner'.$tag,
            'email' => 'owner'.$tag.'@uji.id', 'password' => bcrypt('password'),
            'tenant_id' => $tenant->id, 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $owner->branches()->attach($branch);

        return [$tenant, $owner];
    }

    public function test_tenant_owner_cannot_manage_qc_templates_over_http(): void
    {
        [, $ownerB] = $this->makeTenantOwner('b');
        $tpl = QcPointTemplate::forceCreate([
            'name' => 'Periksa Tepi Lensa', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($ownerB)->get(route('qc-templates.create'))->assertForbidden();
        $this->actingAs($ownerB)->post(route('qc-templates.store'), [
            'name' => 'Titik Buatan Tenant B', 'is_required' => 1,
        ])->assertForbidden();
        $this->actingAs($ownerB)->get(route('qc-templates.edit', $tpl))->assertForbidden();
        $this->actingAs($ownerB)->put(route('qc-templates.update', $tpl), [
            'name' => 'Diubah Tenant B', 'is_required' => 1, 'sort_order' => 1,
        ])->assertForbidden();
        $this->actingAs($ownerB)->post(route('qc-templates.toggle', $tpl))->assertForbidden();

        $this->assertSame(1, QcPointTemplate::count());
        $this->assertSame('Periksa Tepi Lensa', $tpl->fresh()->name);
        $this->assertTrue($tpl->fresh()->is_active);
    }

    public function test_tenant_owner_index_is_read_only(): void
    {
        [, $ownerB] = $this->makeTenantOwner('b');
        QcPointTemplate::forceCreate([
            'name' => 'Periksa Tepi Lensa', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($ownerB)->get(route('qc-templates.index'))
            ->assertOk()
            ->assertSee('Periksa Tepi Lensa')
            ->assertDontSee(route('qc-templates.create'));
    }

    public function test_tenant_service_calls_are_refused(): void
    {
        [, $ownerB] = $this->makeTenantOwner('b');
        $tpl = QcPointTemplate::forceCreate([
            'name' => 'Periksa Tepi Lensa', 'is_required' => true, 'is_active' => true, 'sort_order' => 1,
        ]);
        $service = app(QcTemplateService::class);

        foreach ([
            fn () => $service->create($ownerB, 'Titik B', true),
            fn () => $service->update($ownerB, $tpl, 'Diubah B', true, 1),
            fn () => $service->setActive($ownerB, $tpl, false),
        ] as $call) {
            try {
                $call();
                $this->fail('Pemanggilan service oleh aktor tenant harus ditolak.');
            } catch (AuthorizationException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('Periksa Tepi Lensa', $tpl->fresh()->name);
        $this->assertTrue($tpl->fresh()->is_active);
    }

    public function test_standalone_owner_can_still_manage_templates(): void
    {
        // Tanpa tenant sama sekali: konteks instalasi mandiri.
        $owner = User::forceCreate([
            'name' => 'Pemilik Mandiri', 'username' => 'mandiri',
            'email' => 'mandiri@uji.id', 'password' => bcrypt('password'),
            'tenant_id' => null, 'is_active' => true, 'email_verified_at' => now(),
        ]);
        $owner->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->actingAs($owner)->post(route('qc-templates.store'), [
            'name' => 'Titik Mandiri', 'is_required' => 1, 'sort_order' => 3,
        ])->assertRedirect(route('qc-templates.index'));

        $tpl = QcPointTemplate::where('name', 'Titik Mandiri')->firstOrFail();

        $this->actingAs($owner)->put(route('qc-templates.update', $tpl), [
            'name' => 'Titik Mandiri Baru', 'is_required' => 0, 'sort_order' => 4,
        ])->assertRedirect(route('qc-templates.index'));
        $this->assertSame('Titik Mandiri Baru', $tpl->fresh()->name);

        $this->actingAs($owner)->post(route('qc-templates.toggle', $tpl))
            ->assertRedirect(route('qc-templates.index'));
        $this->assertFalse($tpl->fresh()->is_active);
    }

    public function test_platform_actor_passes_service_gate(): void
    {
        $this->makeTenantOwner('a');

        $tpl = app(QcTemplateService::class)->create($this->platform, 'Titik Platform', true, 1);

        $this->assertSame('Titik Platform', $tpl->name);
    }
}
