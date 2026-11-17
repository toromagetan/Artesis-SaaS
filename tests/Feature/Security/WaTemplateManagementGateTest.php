<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsureWaTemplateManagement;
use App\Modules\Comms\Models\WaTemplate;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Database\Seeders\WaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Audit W-01 W1-3: pengelolaan template WA (sunting/setujui) hanya
 * untuk platform & instalasi mandiri — lewat gerbang wa.template-admin
 * yang TERPISAH dari wa.api, sehingga tetap menahan aktor tenant
 * bahkan bila permukaan WA API suatu saat dibuka lagi.
 */
class WaTemplateManagementGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class, WaTemplateSeeder::class]);
    }

    private function passesGate(?User $user): bool
    {
        $request = Request::create('/wa/template/1/ubah');
        $request->setUserResolver(fn () => $user);

        try {
            $response = app(EnsureWaTemplateManagement::class)
                ->handle($request, fn () => new Response('OK'));

            return $response->getContent() === 'OK';
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());

            return false;
        }
    }

    public function test_tenant_and_guest_are_rejected_platform_and_standalone_pass(): void
    {
        $tenant = Tenant::create(['name' => 'Optik TG', 'slug' => 'optik-tg', 'email' => 'tg@uji.id']);
        $tenantUser = User::create([
            'email_verified_at' => now(), 'name' => 'Owner TG', 'username' => 'owner-tg',
            'email' => 'owner-tg@uji.id', 'password' => bcrypt('rahasia-uji'),
            'is_active' => true, 'is_platform' => false, 'tenant_id' => $tenant->id,
        ]);
        $tenantUser->roles()->attach(Role::where('name', 'Owner')->firstOrFail());

        $this->assertFalse($this->passesGate($tenantUser));
        $this->assertFalse($this->passesGate(null));

        $platform = User::where('username', 'platform')->firstOrFail();
        $this->assertTrue($this->passesGate($platform));
    }

    public function test_standalone_owner_can_still_open_template_edit_page(): void
    {
        // Tanpa tenant terdaftar, pemilik instalasi mandiri lolos
        // ketiga gerbang rute (izin + wa.api + wa.template-admin).
        $standalone = User::where('username', 'owner')->firstOrFail();
        $template = WaTemplate::where('key', 'invoice')->firstOrFail();

        $this->actingAs($standalone)
            ->get(route('wa-templates.edit', $template))
            ->assertOk();

        $this->actingAs($standalone)
            ->put(route('wa-templates.update', $template), ['body' => 'Halo {nama}, invoice {nomor}.'])
            ->assertRedirect(route('wa-templates.index'));
        $this->assertSame('Halo {nama}, invoice {nomor}.', $template->fresh()->body);
    }
}
