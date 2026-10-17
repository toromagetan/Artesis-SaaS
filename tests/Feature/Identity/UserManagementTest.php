<?php

namespace Tests\Feature\Identity;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gerbang pengelolaan Identity (Blueprint D.1 + matriks G.1.2 sebagian):
 * hanya pemegang izin yang lolos; perubahan izin berlaku request berikutnya;
 * penonaktifan diri ditolak.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        $user->branches()->attach(Branch::where('name', 'Pusat')->firstOrFail());

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/pengguna')->assertRedirect('/login');
    }

    public function test_owner_can_open_user_management(): void
    {
        $this->actingAs($this->owner)->get('/pengguna')->assertOk();
        $this->actingAs($this->owner)->get('/peran')->assertOk();
        $this->actingAs($this->owner)->get('/cabang')->assertOk();
    }

    public function test_cashier_is_denied_user_management(): void
    {
        $kasir = $this->userWithRole('Kasir');

        $this->actingAs($kasir)->get('/pengguna')->assertForbidden();
        $this->actingAs($kasir)->get('/peran')->assertForbidden();
        $this->actingAs($kasir)->get('/cabang')->assertForbidden();
    }

    public function test_owner_can_create_user_who_can_then_login(): void
    {
        $this->actingAs($this->owner)->post('/pengguna', [
            'name' => 'Kasir Satu',
            'username' => 'kasir1',
            'email' => 'kasir1@lentera.local',
            'password' => 'rahasia123',
            'roles' => ['Kasir'],
            'branches' => [Branch::where('name', 'Pusat')->firstOrFail()->id],
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['username' => 'kasir1']);

        $this->post('/login', ['login' => 'kasir1', 'password' => 'rahasia123']);
        $this->assertAuthenticated();
    }

    public function test_user_cannot_deactivate_themselves(): void
    {
        $this->actingAs($this->owner)
            ->post(route('users.toggle-active', $this->owner))
            ->assertSessionHasErrors('user');

        $this->assertTrue($this->owner->refresh()->is_active);
    }

    public function test_permission_change_applies_on_next_request_and_is_audited(): void
    {
        $kasir = $this->userWithRole('Kasir');
        $kasirRole = Role::where('name', 'Kasir')->firstOrFail();

        // Sebelum: kasir tidak dapat membuka pengelolaan pengguna.
        $this->actingAs($kasir)->get('/pengguna')->assertForbidden();

        // Owner menambahkan izin users.manage ke peran Kasir.
        $granted = $kasirRole->permissions()->pluck('name')->all();
        $granted[] = 'users.manage';
        $this->actingAs($this->owner)
            ->put(route('roles.update', $kasirRole), ['permissions' => $granted])
            ->assertRedirect(route('roles.index'));

        // Sesudah: request berikutnya langsung lolos.
        $this->actingAs($kasir)->get('/pengguna')->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'roles.sync_permissions',
            'subject_type' => 'role',
            'subject_id' => $kasirRole->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_role_holder_cannot_change_own_role_permissions(): void
    {
        // Owner menyandang peran Owner — mengubah izin Owner = mengubah izinnya sendiri.
        $ownerRole = Role::where('name', 'Owner')->firstOrFail();

        $this->actingAs($this->owner)
            ->put(route('roles.update', $ownerRole), ['permissions' => ['dashboard.view']])
            ->assertSessionHasErrors('permissions');
    }

    public function test_owner_can_create_branch(): void
    {
        $this->actingAs($this->owner)->post('/cabang', [
            'name' => 'Cabang Timur',
            'phone' => '031-555123',
        ])->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', ['name' => 'Cabang Timur']);
    }
}
