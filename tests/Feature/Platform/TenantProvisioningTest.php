<?php

namespace Tests\Feature\Platform;

use App\Modules\Identity\Models\User;
use App\Modules\Platform\Models\Tenant;
use Database\Seeders\IdentitySeeder;
use Database\Seeders\SaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tambah tenant dari panel platform WAJIB memprovisioning akun pemilik:
 * tenant + cabang pertama + pengguna Owner terikat tenant & cabang +
 * langganan Free, dengan kata sandi awal yang ditetapkan admin atau
 * dibuatkan acak — dan kata sandi itu benar-benar dapat dipakai masuk.
 * (Sebelumnya panel hanya membuat baris tenant: tidak ada cabang, tidak
 * ada pengguna, tidak ada kata sandi awal sama sekali.)
 */
class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    private User $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, SaasSeeder::class]);
        $this->platform = User::where('username', 'platform')->firstOrFail();
    }

    public function test_panel_create_provisions_owner_branch_and_free_plan(): void
    {
        $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Terang',
            'email' => 'pemilik@terang.id',
            'phone' => '0812-0000-1111',
            'owner_name' => 'Bapak Terang',
            'branch_name' => 'Terang Pusat',
            'password' => 'sandiawal123',
        ])->assertRedirect();

        $tenant = Tenant::where('slug', 'optik-terang')->firstOrFail();

        $branch = $tenant->branches()->firstOrFail();
        $this->assertSame('Terang Pusat', $branch->name);

        $owner = $tenant->users()->firstOrFail();
        $this->assertSame('Bapak Terang', $owner->name);
        $this->assertSame('pemilik', $owner->username);
        $this->assertTrue($owner->hasRole('Owner'));
        $this->assertTrue($owner->branches->contains($branch));

        $this->assertSame('free', $tenant->currentPlan()?->key);
    }

    public function test_owner_created_from_panel_can_login_with_chosen_password(): void
    {
        $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Terang',
            'email' => 'pemilik@terang.id',
            'password' => 'sandiawal123',
        ])->assertRedirect();

        $this->post('/login', ['login' => 'pemilik', 'password' => 'sandiawal123']);
        $this->assertAuthenticated();
    }

    public function test_password_is_generated_and_flashed_once_when_left_empty(): void
    {
        $response = $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Cerah',
            'email' => 'halo@cerah.id',
        ])->assertRedirect();

        $response->assertSessionHas('initial_password');
        $response->assertSessionHas('owner_username', 'halo');

        $generated = session('initial_password');
        $this->assertNotEmpty($generated);

        $this->post('/login', ['login' => 'halo', 'password' => $generated]);
        $this->assertAuthenticated();
    }

    public function test_defaults_owner_name_and_branch_when_omitted(): void
    {
        $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Simpel',
            'email' => 'halo@simpel.id',
        ])->assertRedirect();

        $tenant = Tenant::where('slug', 'optik-simpel')->firstOrFail();
        $this->assertSame('Optik Simpel', $tenant->users()->firstOrFail()->name);
        $this->assertSame('Pusat', $tenant->branches()->firstOrFail()->name);
    }

    public function test_email_already_used_by_a_user_is_rejected(): void
    {
        $existing = User::where('username', 'owner')->firstOrFail();

        $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Duplikat',
            'email' => $existing->email,
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('tenants', ['name' => 'Optik Duplikat']);
    }

    public function test_short_password_is_rejected(): void
    {
        $this->actingAs($this->platform)->post('/platform/tenant', [
            'name' => 'Optik Pendek',
            'email' => 'halo@pendek.id',
            'password' => 'abc',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('tenants', ['name' => 'Optik Pendek']);
    }
}
