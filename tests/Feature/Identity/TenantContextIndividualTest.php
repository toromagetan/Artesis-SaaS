<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\TenantContext;
use App\Modules\Platform\Models\Tenant;
use Database\Seeders\IdentitySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-3 (perbaikan audit) — sisi SEBALIKNYA dari klasifikasi akun
 * tanpa tenant: pada instalasi yang BENAR-BENAR individual (belum
 * ada satu pun tenant terdaftar), akun Owner tanpa tenant adalah
 * MODE_STANDALONE. Kontrasnya — akun yang sama pada instalasi
 * bersama — dibuktikan MODE_UNASSIGNED di TenantContextTest.
 */
class TenantContextIndividualTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        // SENGAJA tanpa SaasSeeder & tanpa pendaftaran tenant:
        // instalasi individual murni.
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    public function test_owner_is_standalone_when_no_tenant_registered(): void
    {
        $this->assertSame(0, Tenant::count());

        $ctx = TenantContext::for($this->owner);
        $this->assertSame(TenantContext::MODE_STANDALONE, $ctx->mode());
        $this->assertTrue($ctx->isStandalone());
        $this->assertFalse($ctx->isUnassigned());
        $this->assertFalse($ctx->isPlatform());
        $this->assertNull($ctx->tenant());
        $this->assertNull($ctx->tenantId());
    }

    public function test_standalone_context_grants_no_tenant_identity(): void
    {
        $ctx = TenantContext::for($this->owner);

        $this->expectException(AuthorizationException::class);
        $ctx->requireTenant();
    }

    public function test_standalone_flows_still_work(): void
    {
        $this->get('/login')->assertOk();
        $this->actingAs($this->owner)->get('/dashboard')->assertOk();
    }
}
