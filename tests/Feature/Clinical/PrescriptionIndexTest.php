<?php

namespace Tests\Feature\Clinical;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar resep global + menu Resep: halaman /resep adalah daftar penuh
 * dengan rute sendiri (bukan pratinjau), tergerbang prescriptions.view,
 * dapat disaring nama pelanggan & cabang pemeriksaan; menu tampil di
 * grup Pelanggan pada sidebar; daftar pemeriksa pada formulir resep
 * tidak memuat aktor platform.
 */
class PrescriptionIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create([
            'name' => 'Budi Uji Resep',
            'branch_id' => $this->branchA->id,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        $user->branches()->attach($this->branchA);

        return $user;
    }

    public function test_owner_can_open_prescription_list_and_see_entries(): void
    {
        Prescription::factory()->create([
            'customer_id' => $this->customer->id,
            'branch_id' => $this->branchA->id,
            'examiner_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->get('/resep')
            ->assertOk()
            ->assertSee('Daftar Resep')
            ->assertSee('Budi Uji Resep');
    }

    public function test_list_search_filters_by_customer_name(): void
    {
        $other = Customer::factory()->create(['name' => 'Sari Lainnya', 'branch_id' => $this->branchA->id]);
        Prescription::factory()->create(['customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id]);
        Prescription::factory()->create(['customer_id' => $other->id, 'branch_id' => $this->branchA->id]);

        $this->actingAs($this->owner)->get('/resep?q=Budi')
            ->assertOk()
            ->assertSee('Budi Uji Resep')
            ->assertDontSee('Sari Lainnya');
    }

    public function test_user_without_prescriptions_view_is_denied(): void
    {
        $gudang = $this->userWithRole('Gudang');

        $this->actingAs($gudang)->get('/resep')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/resep')->assertRedirect('/login');
    }

    public function test_sidebar_shows_prescription_menu_for_owner(): void
    {
        $this->actingAs($this->owner)->get('/pelanggan')
            ->assertOk()
            ->assertSee('Daftar Resep')
            ->assertSee(route('prescriptions.index'), false);
    }

    public function test_examiner_dropdown_excludes_platform_user(): void
    {
        $html = $this->actingAs($this->owner)
            ->get('/pelanggan/'.$this->customer->id.'/resep/baru')
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Platform Lentera', $html);
        $this->assertStringContainsString('Owner Lentera', $html);
    }
}
