<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Services\BranchContext;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BranchContext (Blueprint D.1/F0.3): sumber kebenaran tunggal wilayah cabang.
 */
class BranchContextTest extends TestCase
{
    use RefreshDatabase;

    private Branch $pusat;

    private Branch $timur;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->pusat = Branch::where('name', 'Pusat')->firstOrFail();
        $this->timur = Branch::create(['name' => 'Timur']);
    }

    private function userWithRole(string $role, array $branchIds = []): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());
        $user->branches()->attach($branchIds);

        return $user;
    }

    public function test_owner_has_global_access_to_all_branches(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();
        $context = BranchContext::for($owner);

        $this->assertTrue($context->hasGlobalAccess());
        $this->assertEqualsCanonicalizing(
            [$this->pusat->id, $this->timur->id],
            $context->managedBranchIds(),
        );
        $this->assertTrue($context->allowsBranch($this->timur->id));
    }

    public function test_staff_limited_to_managed_branches(): void
    {
        $kasir = $this->userWithRole('Kasir', [$this->pusat->id]);
        $context = BranchContext::for($kasir);

        $this->assertFalse($context->hasGlobalAccess());
        $this->assertSame([$this->pusat->id], $context->managedBranchIds());
        $this->assertTrue($context->allowsBranch($this->pusat->id));
        $this->assertFalse($context->allowsBranch($this->timur->id));
        $this->assertSame($this->pusat->id, $context->currentBranchId());
    }

    public function test_assert_can_access_throws_for_foreign_branch(): void
    {
        $kasir = $this->userWithRole('Kasir', [$this->pusat->id]);

        $this->expectException(AuthorizationException::class);
        BranchContext::for($kasir)->assertCanAccess($this->timur->id);
    }

    public function test_platform_actor_has_no_business_branches(): void
    {
        $platform = User::where('username', 'platform')->firstOrFail();
        $context = BranchContext::for($platform);

        $this->assertFalse($context->hasGlobalAccess());
        $this->assertSame([], $context->managedBranchIds());
        $this->assertFalse($context->allowsBranch($this->pusat->id));
    }

    public function test_guest_context_is_empty(): void
    {
        $context = BranchContext::for(null);

        $this->assertFalse($context->hasGlobalAccess());
        $this->assertSame([], $context->managedBranchIds());
        $this->assertNull($context->currentBranchId());
    }
}
