<?php

namespace Tests\Feature\CRM;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\AccountingSeeder;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.2 CRM (Blueprint D.4): pelanggan terpagar cabang, tanpa hapus,
 * peringatan nomor WA berbagi, pagar izin.
 */
class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Branch $branchB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([IdentitySeeder::class, AccountingSeeder::class]);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->branchB = Branch::create(['name' => 'Cabang B']);
    }

    private function kasirFor(Branch $branch): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $user->branches()->sync([$branch->id]);

        return $user;
    }

    public function test_kasir_creates_customer_in_own_branch(): void
    {
        $kasir = $this->kasirFor($this->branchA);

        $this->actingAs($kasir)->post('/pelanggan', [
            'name' => 'Budi Waluyo',
            'phone' => '081234567890',
            'branch_id' => $this->branchA->id,
        ])->assertRedirect();

        $customer = Customer::where('name', 'Budi Waluyo')->firstOrFail();
        $this->assertSame($this->branchA->id, $customer->branch_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customers.create', 'subject_id' => $customer->id]);
    }

    public function test_create_in_foreign_branch_is_denied(): void
    {
        $kasir = $this->kasirFor($this->branchA);

        $this->actingAs($kasir)->post('/pelanggan', [
            'name' => 'Penyusup',
            'phone' => '089999999999',
            'branch_id' => $this->branchB->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('customers', ['name' => 'Penyusup']);
    }

    public function test_customers_are_global_across_branches(): void
    {
        $customerB = Customer::factory()->create(['branch_id' => $this->branchB->id, 'name' => 'Pelanggan Jauh']);
        $kasirA = $this->kasirFor($this->branchA);

        // ADR-012: pelanggan berlaku GLOBAL — kasir cabang A melihat,
        // membuka, dan menemukan pelanggan asal cabang B; paganya izin.
        $this->actingAs($kasirA)->get('/pelanggan')->assertOk()->assertSee('Pelanggan Jauh');
        $this->actingAs($kasirA)->get('/pelanggan/'.$customerB->id)->assertOk()->assertSee('Pelanggan Jauh');
        $this->actingAs($kasirA)->get('/cari?q=Jauh')->assertOk()->assertSee('Pelanggan Jauh');
    }

    public function test_filter_by_origin_branch_works_for_any_user(): void
    {
        Customer::factory()->create(['branch_id' => $this->branchA->id, 'name' => 'Pelanggan Sendiri']);
        Customer::factory()->create(['branch_id' => $this->branchB->id, 'name' => 'Pelanggan Tetangga']);
        $kasirA = $this->kasirFor($this->branchA);

        // Filter cabang = saringan cabang PENDAFTARAN (atribut asal),
        // bukan pagar: kasir A dapat menyaring pelanggan asal cabang B.
        $this->actingAs($kasirA)->get('/pelanggan?cabang='.$this->branchB->id)
            ->assertOk()
            ->assertSee('Pelanggan Tetangga')
            ->assertDontSee('Pelanggan Sendiri');

        $this->actingAs($kasirA)->get('/pelanggan?cabang='.$this->branchA->id)
            ->assertOk()
            ->assertSee('Pelanggan Sendiri');
    }

    public function test_shared_phone_warns_but_still_saves(): void
    {
        Customer::factory()->create(['branch_id' => $this->branchA->id, 'phone' => '081200000000']);

        $response = $this->actingAs($this->owner)->post('/pelanggan', [
            'name' => 'Anggota Keluarga',
            'phone' => '081200000000',
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertRedirect()->assertSessionHas('warning');
        $this->assertSame(2, Customer::withoutGlobalScopes()->where('phone', '081200000000')->count());
    }

    public function test_deactivate_toggle_and_audit(): void
    {
        $customer = Customer::factory()->create(['branch_id' => $this->branchA->id]);

        $this->actingAs($this->owner)->post('/pelanggan/'.$customer->id.'/alih-aktif')->assertRedirect();
        $this->assertFalse($customer->refresh()->is_active);
        $this->assertDatabaseHas('audit_logs', ['action' => 'customers.deactivate', 'subject_id' => $customer->id]);

        // Tidak ada rute hapus untuk pelanggan.
        $this->actingAs($this->owner)->delete('/pelanggan/'.$customer->id)->assertStatus(405);
    }

    public function test_platform_and_roleless_users_are_denied(): void
    {
        $platform = User::where('username', 'platform')->firstOrFail();
        $this->actingAs($platform)->get('/pelanggan')->assertForbidden();

        $roleless = User::factory()->create();
        $this->actingAs($roleless)->get('/pelanggan')->assertForbidden();
        $this->actingAs($roleless)->post('/pelanggan', ['name' => 'X', 'phone' => '081'])->assertForbidden();
    }
}
