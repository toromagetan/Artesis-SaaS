<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Http\Controllers\WaLogController;
use App\Modules\Comms\Models\WaMessage;
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
use Tests\TestCase;

/**
 * Audit W-01 W1-2: log pesan WA diemberkan per konteks aktor.
 * Sebelumnya WaLogController menampilkan pesan seluruh instalasi;
 * penahanan satu-satunya adalah middleware wa.api di rute.
 */
class WaLogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    /** @return array{Tenant, User} */
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

        return [$tenant, $owner];
    }

    private function message(?int $tenantId, string $body): WaMessage
    {
        return WaMessage::create([
            'tenant_id' => $tenantId, 'phone' => '628120000099', 'body' => $body,
            'direction' => 'out', 'status' => 'sent', 'trigger' => 'manual',
        ]);
    }

    /** @return array<int, int> */
    private function listedIdsFor(User $user): array
    {
        $request = Request::create('/wa/log');
        $request->setUserResolver(fn () => $user);
        $view = app(WaLogController::class)->index($request);

        return $view->getData()['messages']->pluck('id')->all();
    }

    public function test_tenant_sees_only_own_messages(): void
    {
        [$tenantA, $ownerA] = $this->tenantOwner('wla');
        [$tenantB] = $this->tenantOwner('wlb');
        $own = $this->message($tenantA->id, 'Pesan milik A');
        $this->message($tenantB->id, 'Pesan milik B');
        $this->message(null, 'Pesan global');

        $this->assertSame([$own->id], $this->listedIdsFor($ownerA));
    }

    public function test_platform_sees_all_messages(): void
    {
        [$tenantA] = $this->tenantOwner('wpa');
        [$tenantB] = $this->tenantOwner('wpb');
        $a = $this->message($tenantA->id, 'Pesan A');
        $b = $this->message($tenantB->id, 'Pesan B');
        $g = $this->message(null, 'Pesan global');

        $this->assertEqualsCanonicalizing([$a->id, $b->id, $g->id], $this->listedIdsFor($this->platform));
    }

    public function test_standalone_installation_sees_its_global_bucket(): void
    {
        // Tanpa satu pun tenant terdaftar, pemilik instalasi adalah
        // aktor standalone dan lognya adalah ember NULL.
        $one = $this->message(null, 'Pesan instalasi 1');
        $two = $this->message(null, 'Pesan instalasi 2');

        $standalone = User::where('username', 'owner')->firstOrFail();
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $this->listedIdsFor($standalone));
    }
}
