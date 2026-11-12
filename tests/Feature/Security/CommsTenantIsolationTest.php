<?php

namespace Tests\Feature\Security;

use App\Modules\Comms\Models\WaMessage;
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
use Tests\TestCase;

/**
 * D-02 GAP-5 — Permukaan komunikasi (WhatsApp) lintas tenant.
 * Inbox menampilkan percakapan per pelanggan; daftar, halaman
 * percakapan, dan aksi balas untuk pelanggan tenant lain tidak
 * boleh dapat dijangkau aktor tenant sebelah, sementara percakapan
 * tenant sendiri tampil normal (kontrol positif).
 */
class CommsTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $adminA;

    private User $adminB;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();

        [$tenantA, $this->adminA, $branchA] = $this->tenantAdmin('a');
        [$tenantB, $this->adminB, $branchB] = $this->tenantAdmin('b');

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
            'name' => 'Admin WA '.$tag, 'username' => 'admin-wa-'.$tag,
            'email' => 'admin-wa-'.$tag.'@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $admin->roles()->attach(Role::where('name', 'Owner')->firstOrFail());
        $admin->branches()->attach($branch);

        return [$tenant, $admin, $branch];
    }

    public function test_inbox_list_shows_only_own_tenant_conversations(): void
    {
        $this->actingAs($this->adminA)->get('/wa/inbox')
            ->assertOk()
            ->assertSee('Pelanggan WA Alpha')
            ->assertDontSee('Pelanggan WA Beta')
            ->assertDontSee('Pesan rahasia tenant Beta');

        $this->actingAs($this->adminB)->get('/wa/inbox')
            ->assertOk()
            ->assertSee('Pelanggan WA Beta')
            ->assertDontSee('Pelanggan WA Alpha')
            ->assertDontSee('Pesan rahasia tenant Alpha');
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

    public function test_own_conversation_view_works_control(): void
    {
        $this->actingAs($this->adminA)->get('/wa/inbox/'.$this->customerA->id)
            ->assertOk()
            ->assertSee('Pesan rahasia tenant Alpha');
    }
}
