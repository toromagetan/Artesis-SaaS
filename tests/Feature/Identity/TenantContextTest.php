<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use App\Modules\Platform\Services\TenantSignupService;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * R-3 — fondasi TenantContext. Yang dibuktikan adalah HASIL
 * keamanan/otorisasi, bukan sekadar kelas terpanggil:
 * konteks hanya berasal dari pengguna terautentikasi (input
 * request tidak berpengaruh), mode platform/standalone/tamu
 * dibedakan tegas dan tidak pernah menjadi identitas tenant,
 * tenant menggantung/nonaktif ditolak primitif bisnisnya, dan
 * konteks tidak bocor antar-pengguna.
 */
class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    private User $standalone;

    private User $ownerA;

    private User $ownerB;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
        $this->standalone = User::where('username', 'owner')->firstOrFail();

        $signup = app(TenantSignupService::class);
        $payload = fn (string $name, string $email) => [
            'business_name' => $name, 'owner_name' => $name,
            'email' => $email, 'phone' => '081200000000',
            'branch_name' => $name.' Pusat', 'password' => 'rahasia-uji-123',
            'password_confirmation' => 'rahasia-uji-123',
        ];
        $this->ownerA = $signup->register($payload('Optik Alpha', 'alpha@uji.id'), $this->platform);
        $this->ownerB = $signup->register($payload('Optik Beta', 'beta@uji.id'), $this->platform);
        $this->tenantA = $this->ownerA->tenant()->firstOrFail();
        $this->tenantB = $this->ownerB->tenant()->firstOrFail();
    }

    /** Rute probe khusus test: memantulkan konteks tenant request ini. */
    private function registerProbe(): void
    {
        Route::middleware('web')->get('/probe-tenant-context', function () {
            $ctx = TenantContext::current();

            return response()->json([
                'mode' => $ctx->mode(),
                'tenant_id' => $ctx->tenantId(),
                'valid' => $ctx->isValid(),
                'suspended' => $ctx->isSuspended(),
            ]);
        });
    }

    public function test_tenant_user_gets_their_own_tenant_context(): void
    {
        $ctx = TenantContext::for($this->ownerA);
        $this->assertSame(TenantContext::MODE_TENANT, $ctx->mode());
        $this->assertTrue($ctx->isValid());
        $this->assertTrue($ctx->isActive());
        $this->assertSame($this->tenantA->id, $ctx->tenantId());
        $this->assertSame($this->tenantA->id, $ctx->requireTenant()->id);

        $ctxB = TenantContext::for($this->ownerB);
        $this->assertSame($this->tenantB->id, $ctxB->tenantId());
        $this->assertNotSame($ctx->tenantId(), $ctxB->tenantId());
    }

    public function test_request_input_cannot_falsify_tenant_context(): void
    {
        $this->registerProbe();

        // tenant_id tenant B lewat query DAN header: konteks tetap A.
        $this->actingAs($this->ownerA)
            ->withHeaders(['X-Tenant-Id' => (string) $this->tenantB->id])
            ->get('/probe-tenant-context?tenant_id='.$this->tenantB->id)
            ->assertOk()
            ->assertExactJson([
                'mode' => 'tenant',
                'tenant_id' => $this->tenantA->id,
                'valid' => true,
                'suspended' => false,
            ]);
    }

    public function test_assert_tenant_access_rejects_foreign_tenant(): void
    {
        $ctx = TenantContext::for($this->ownerA);
        $ctx->assertTenantAccess($this->tenantA->id);

        $this->expectException(AuthorizationException::class);
        $ctx->assertTenantAccess($this->tenantB->id);
    }

    public function test_platform_user_has_no_tenant_context(): void
    {
        $ctx = TenantContext::for($this->platform);
        $this->assertSame(TenantContext::MODE_PLATFORM, $ctx->mode());
        $this->assertNull($ctx->tenant());
        $this->assertNull($ctx->tenantId());

        // Platform tidak memperoleh identitas bisnis tenant mana pun.
        try {
            $ctx->requireTenant();
            $this->fail('requireTenant platform harus menolak.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $ctx->assertTenantAccess($this->tenantA->id);
            $this->fail('assertTenantAccess platform harus menolak.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_unassigned_account_on_shared_install_is_not_standalone(): void
    {
        // Instalasi bersama: setUp mendaftarkan DUA tenant. Akun
        // legacy 'owner' (tanpa tenant, bukan platform) TIDAK boleh
        // diklasifikasikan standalone (perbaikan audit R-3).
        $this->assertSame(2, Tenant::count());

        $ctx = TenantContext::for($this->standalone);
        $this->assertSame(TenantContext::MODE_UNASSIGNED, $ctx->mode());
        $this->assertTrue($ctx->isUnassigned());
        $this->assertFalse($ctx->isStandalone());
        $this->assertFalse($ctx->isPlatform());
        $this->assertNull($ctx->tenantId());
        $this->assertNull($ctx->tenant());

        try {
            $ctx->requireTenant();
            $this->fail('requireTenant akun unassigned harus menolak.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
        try {
            $ctx->assertTenantAccess($this->tenantA->id);
            $this->fail('assertTenantAccess akun unassigned harus menolak.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    // Kondisi instalasi individual (nol tenant) dibuktikan di
    // TenantContextIndividualTest — setUp kelas ini selalu
    // mendaftarkan dua tenant, jadi klasifikasi standalone sejati
    // tidak dapat dibuktikan dari sini.

    public function test_guest_context_is_safe(): void
    {
        $ctx = TenantContext::for(null);
        $this->assertSame(TenantContext::MODE_GUEST, $ctx->mode());
        $this->assertNull($ctx->tenantId());
        $this->assertFalse($ctx->isValid());
    }

    public function test_dangling_tenant_id_is_invalid_and_rejected(): void
    {
        $ghost = User::factory()->create();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('users')->where('id', $ghost->id)->update(['tenant_id' => 999999]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $ghost->refresh();

        $ctx = TenantContext::for($ghost);
        $this->assertSame(TenantContext::MODE_TENANT, $ctx->mode());
        $this->assertSame(999999, $ctx->claimedTenantId());
        $this->assertFalse($ctx->isValid());
        $this->assertNull($ctx->tenantId());

        $this->expectException(AuthorizationException::class);
        $ctx->requireTenant();
    }

    public function test_suspended_tenant_is_identified_but_not_usable(): void
    {
        $this->tenantA->update(['is_active' => false]);

        $ctx = TenantContext::for($this->ownerA->fresh());
        // Identitas tetap teresolusi untuk keperluan platform/diagnostik…
        $this->assertTrue($ctx->isValid());
        $this->assertSame($this->tenantA->id, $ctx->tenantId());
        $this->assertTrue($ctx->isSuspended());
        $this->assertFalse($ctx->isActive());

        // …tetapi primitif bisnis menolaknya.
        $this->expectException(AuthorizationException::class);
        $ctx->requireTenant();
    }

    public function test_context_does_not_leak_between_users(): void
    {
        $this->registerProbe();

        $this->actingAs($this->ownerA)->get('/probe-tenant-context')
            ->assertOk()->assertJsonPath('tenant_id', $this->tenantA->id);

        $this->actingAs($this->ownerB)->get('/probe-tenant-context')
            ->assertOk()->assertJsonPath('tenant_id', $this->tenantB->id);

        // Berganti ke platform pada aplikasi yang sama: konteks dibangun ulang.
        $this->actingAs($this->platform)->get('/probe-tenant-context')
            ->assertOk()->assertExactJson([
                'mode' => 'platform', 'tenant_id' => null, 'valid' => false, 'suspended' => false,
            ]);

        // Unit: current() mengikuti pengguna auth terakhir, bukan yang pertama.
        $this->actingAs($this->ownerA);
        $this->assertSame($this->tenantA->id, TenantContext::current()->tenantId());
        $this->actingAs($this->ownerB);
        $this->assertSame($this->tenantB->id, TenantContext::current()->tenantId());
    }

    public function test_requests_not_needing_context_still_work(): void
    {
        $this->get('/')->assertOk();
        $this->get('/login')->assertOk();

        // Halaman bisnis standalone tetap dapat dijangkau pemilik individual.
        $this->actingAs($this->standalone)->get('/dashboard')->assertOk();
    }
}
