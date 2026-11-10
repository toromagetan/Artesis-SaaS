<?php

namespace Tests\Feature;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Plan;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\ApiTokenService;
use App\Modules\Platform\Services\PlanGate;
use App\Modules\Platform\Services\PlanService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * S-04 — PlanGate berpangkal pada klasifikasi TenantContext:
 * hanya aktor tenant nyata yang ditegakkan paketnya, hanya
 * standalone yang bebas; unassigned, tenant menggantung, dan
 * aktor platform pada jalur bisnis GAGAL-TUTUP (fitur mati,
 * batas 0) — bukan lagi lolos sebagai "tanpa tenant".
 */
class PlanGateTenantContextTest extends TestCase
{
    use RefreshDatabase;

    private PlanGate $gate;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->gate = app(PlanGate::class);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    private function makeUser(?int $tenantId, string $tag): User
    {
        $user = User::create([
            'name' => 'Uji '.$tag, 'username' => 'uji-'.$tag,
            'email' => 'uji-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenantId,
        ]);
        $user->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        return $user;
    }

    private function makeTenant(string $planKey, string $tag): Tenant
    {
        $tenant = Tenant::create([
            'name' => 'Optik '.$tag, 'slug' => 'optik-'.$tag, 'email' => $tag.'@uji.id',
        ]);
        app(PlanService::class)->assignPlan(
            $tenant,
            Plan::where('key', $planKey)->firstOrFail(),
            null,
            $this->platform,
        );

        return $tenant;
    }

    public function test_standalone_actor_remains_unrestricted(): void
    {
        // Tidak ada tenant terdaftar sama sekali → instalasi mandiri.
        $this->assertSame(0, Tenant::count());
        $owner = User::where('username', 'owner')->firstOrFail();

        $this->assertTrue($this->gate->enabled($owner, 'whatsapp'));
        $this->assertNull($this->gate->limit($owner, 'products_max'));
        $this->gate->assertEnabled($owner, 'whatsapp');
        $this->gate->assertWithinLimit($owner, 'products_max');
        $this->addToAssertionCount(2);
    }

    public function test_unassigned_actor_is_denied(): void
    {
        $this->makeTenant('team', 'penanda');
        $unassigned = $this->makeUser(null, 'yatim');

        $this->assertFalse($this->gate->enabled($unassigned, 'whatsapp'));
        $this->assertFalse($this->gate->enabled($unassigned, 'api_access'));
        $this->assertSame(0, $this->gate->limit($unassigned, 'products_max'));
        $this->assertNull($this->gate->tenantFor($unassigned));

        try {
            $this->gate->assertEnabled($unassigned, 'whatsapp');
            $this->fail('assertEnabled seharusnya melempar 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->expectException(ValidationException::class);
        $this->gate->assertWithinLimit($unassigned, 'products_max');
    }

    public function test_dangling_tenant_actor_is_denied(): void
    {
        // tenant_id menggantung hanya dapat lahir dari penghapusan
        // baris tenant di luar jalur aplikasi (FK users ON DELETE
        // SET NULL menormalkan jalur biasa) — simulasikan dengan
        // mematikan pemeriksaan FK sesaat.
        $tenant = $this->makeTenant('team', 'akan-hilang');
        $ghost = $this->makeUser($tenant->id, 'hantu');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('tenants')->where('id', $tenant->id)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->assertFalse($this->gate->enabled($ghost, 'whatsapp'));
        $this->assertSame(0, $this->gate->limit($ghost, 'users_max'));
        $this->assertNull($this->gate->tenantFor($ghost));
    }

    public function test_platform_actor_is_denied_business_features(): void
    {
        $this->assertFalse($this->gate->enabled($this->platform, 'whatsapp'));
        $this->assertFalse($this->gate->enabled($this->platform, 'api_access'));
        $this->assertSame(0, $this->gate->limit($this->platform, 'branches_max'));
    }

    public function test_tenant_actor_still_uses_own_plan(): void
    {
        $free = $this->makeTenant('free', 'gratis');
        $freeAdmin = $this->makeUser($free->id, 'gratis');
        $team = $this->makeTenant('team', 'tim');
        $teamAdmin = $this->makeUser($team->id, 'tim');
        $best = $this->makeTenant('best_offer', 'terbaik');
        $bestAdmin = $this->makeUser($best->id, 'terbaik');

        $this->assertFalse($this->gate->enabled($freeAdmin, 'whatsapp'));
        $this->assertSame(500, $this->gate->limit($freeAdmin, 'products_max'));
        $this->assertTrue($this->gate->enabled($teamAdmin, 'purchasing_full'));
        $this->assertTrue($this->gate->enabled($bestAdmin, 'whatsapp'));
        $this->assertSame($free->id, $this->gate->tenantFor($freeAdmin)?->id);
    }

    public function test_unassigned_actor_gets_403_on_feature_route(): void
    {
        $this->makeTenant('team', 'penanda-http');
        $unassigned = $this->makeUser(null, 'yatim-http');

        $this->actingAs($unassigned)->get('/pembelian/pr')->assertForbidden();
    }

    public function test_api_token_of_unassigned_creator_is_rejected(): void
    {
        $this->makeTenant('team', 'penanda-api');
        $unassigned = $this->makeUser(null, 'yatim-api');

        $issued = app(ApiTokenService::class)->issue($unassigned, 'Uji Yatim', ['products.read']);

        $this->withHeader('Authorization', 'Bearer '.$issued['plain'])
            ->getJson('/api/v1/products')
            ->assertForbidden();
    }
}
