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
 * Pintu masuk "Tambah Resep" dari daftar resep: tombol pada /resep membuka
 * halaman pilih pelanggan (resep tetap lahir dari konteks pelanggan lewat
 * rute bersarang), dari sana formulir resep yang sudah ada terbuka, dan
 * pelanggan baru dapat dibuat dari halaman yang sama. Tergerbang
 * prescriptions.create; peran lihat-saja (Kasir) tidak melihat tombolnya.
 */
class PrescriptionCreateEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Customer $customerA;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customerA = Customer::factory()->create([
            'name' => 'Sari Pilihan',
            'phone' => '0812-1111-2222',
            'branch_id' => $this->branchA->id,
        ]);
        $this->customerB = Customer::factory()->create([
            'name' => 'Joko Lainnya',
            'phone' => '0813-9999-0000',
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

    public function test_index_shows_add_button_for_users_who_can_create(): void
    {
        $this->actingAs($this->owner)->get('/resep')
            ->assertOk()
            ->assertSee(route('prescriptions.pick-customer'))
            ->assertSee('Tambah Resep');
    }

    public function test_index_hides_add_button_for_view_only_role(): void
    {
        $kasir = $this->userWithRole('Kasir');

        $this->actingAs($kasir)->get('/resep')
            ->assertOk()
            ->assertDontSee(route('prescriptions.pick-customer'));
    }

    public function test_picker_lists_customers_and_searches_by_name_and_phone(): void
    {
        $this->actingAs($this->owner)->get('/resep/baru')
            ->assertOk()
            ->assertSee('Sari Pilihan')
            ->assertSee('Joko Lainnya');

        $this->actingAs($this->owner)->get('/resep/baru?q=Sari')
            ->assertOk()
            ->assertSee('Sari Pilihan')
            ->assertDontSee('Joko Lainnya');

        $this->actingAs($this->owner)->get('/resep/baru?q=9999')
            ->assertOk()
            ->assertSee('Joko Lainnya')
            ->assertDontSee('Sari Pilihan');
    }

    public function test_picker_row_opens_the_existing_create_form(): void
    {
        $this->actingAs($this->owner)->get('/pelanggan/'.$this->customerA->id.'/resep/baru')
            ->assertOk()
            ->assertSee('Sari Pilihan');
    }

    public function test_picker_requires_create_permission(): void
    {
        $kasir = $this->userWithRole('Kasir');

        $this->actingAs($kasir)->get('/resep/baru')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/resep/baru')->assertRedirect('/login');
    }

    public function test_end_to_end_prescription_created_for_picked_customer(): void
    {
        $this->actingAs($this->owner)->post('/pelanggan/'.$this->customerB->id.'/resep', [
            'examined_at' => '2026-10-30',
            'od_sph' => '-1.50', 'od_cyl' => '-0.50', 'od_axis' => '180', 'od_pdr' => '31.0',
            'os_sph' => '-1.25', 'os_cyl' => '-0.25', 'os_axis' => '175', 'os_pdl' => '31.5',
            'pd_total' => '62.5',
        ])->assertRedirect();

        $rx = Prescription::firstOrFail();
        $this->assertSame($this->customerB->id, $rx->customer_id);
    }
}
