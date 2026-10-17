<?php

namespace Tests\Feature\Clinical;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.2 Clinical (Blueprint D.5 + PRD Detail 3.3): grid OD/OS, aturan AXIS,
 * kunci sesudah terpakai, duplikat, kaitan pelanggan struktural,
 * catatan klinis terpagar medical_notes.view.
 */
class PrescriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Branch $branchA;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $this->branchA = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $this->branchA->id]);
    }

    private function validGrid(array $overrides = []): array
    {
        return array_merge([
            'examined_at' => '2026-10-17',
            'od_sph' => '-1.50', 'od_cyl' => '-0.50', 'od_axis' => '180', 'od_pdr' => '31.0',
            'os_sph' => '-1.25', 'os_cyl' => '-0.25', 'os_axis' => '175', 'os_pdl' => '31.5',
            'pd_total' => '62.5',
        ], $overrides);
    }

    public function test_create_binds_customer_from_route_not_from_input(): void
    {
        $other = Customer::factory()->create(['branch_id' => $this->branchA->id]);

        // Input bebas mencoba membajak kaitan ke pelanggan lain — diabaikan.
        $this->actingAs($this->owner)->post(
            '/pelanggan/'.$this->customer->id.'/resep',
            $this->validGrid(['customer_id' => $other->id]),
        )->assertRedirect();

        $rx = Prescription::firstOrFail();
        $this->assertSame($this->customer->id, $rx->customer_id);
        $this->assertSame($this->branchA->id, $rx->branch_id);
    }

    public function test_axis_is_required_when_cyl_non_zero(): void
    {
        $this->actingAs($this->owner)->post(
            '/pelanggan/'.$this->customer->id.'/resep',
            $this->validGrid(['od_axis' => null]),
        )->assertSessionHasErrors('od_axis');

        $this->assertSame(0, Prescription::count());
    }

    public function test_axis_not_required_when_cyl_zero(): void
    {
        $this->actingAs($this->owner)->post(
            '/pelanggan/'.$this->customer->id.'/resep',
            $this->validGrid(['od_cyl' => '0', 'od_axis' => null, 'os_cyl' => '0', 'os_axis' => null]),
        )->assertRedirect();

        $this->assertSame(1, Prescription::count());
    }

    public function test_used_prescription_is_locked_from_update(): void
    {
        $rx = Prescription::factory()->used()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id, 'od_sph' => -2.00,
        ]);

        $this->actingAs($this->owner)->put(
            '/resep/'.$rx->id,
            $this->validGrid(['od_sph' => '-9.99']),
        )->assertSessionHasErrors('examined_at');

        $this->assertSame('-2.00', (string) $rx->refresh()->od_sph);
    }

    public function test_duplicate_creates_unlocked_copy_and_keeps_original(): void
    {
        $rx = Prescription::factory()->used()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id, 'od_sph' => -3.25,
        ]);

        $this->actingAs($this->owner)->post('/resep/'.$rx->id.'/duplikat')->assertRedirect();

        $this->assertSame(2, Prescription::count());
        $copy = Prescription::where('id', '!=', $rx->id)->firstOrFail();
        $this->assertNull($copy->used_at);
        $this->assertSame('-3.25', (string) $copy->od_sph);
        $this->assertSame($this->customer->id, $copy->customer_id);
        $this->assertNotNull($rx->refresh()->used_at);
    }

    public function test_prescription_of_other_branch_is_not_found(): void
    {
        $branchB = Branch::create(['name' => 'Cabang B']);
        $customerB = Customer::factory()->create(['branch_id' => $branchB->id]);
        $rxB = Prescription::factory()->create(['customer_id' => $customerB->id, 'branch_id' => $branchB->id]);

        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branchA->id]);

        $this->actingAs($kasir)->get('/resep/'.$rxB->id)->assertNotFound();
    }

    public function test_clinical_notes_require_medical_notes_permission(): void
    {
        $rx = Prescription::factory()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id,
            'notes' => 'Katarak awal mata kiri',
        ]);

        // Kasir: prescriptions.view TANPA medical_notes.view (matriks C.2).
        $kasir = User::factory()->create();
        $kasir->roles()->sync([Role::where('name', 'kasir')->firstOrFail()->id]);
        $kasir->branches()->sync([$this->branchA->id]);

        $this->actingAs($kasir)->get('/resep/'.$rx->id)->assertOk()->assertDontSee('Katarak awal mata kiri');
        $this->actingAs($this->owner)->get('/resep/'.$rx->id)->assertOk()->assertSee('Katarak awal mata kiri');
    }

    public function test_print_document_contains_grid_and_pd_values(): void
    {
        $rx = Prescription::factory()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id,
        ]);

        $html = $this->actingAs($this->owner)->get('/resep/'.$rx->id.'/cetak')->assertOk()->getContent();
        $this->assertStringContainsString('A5 portrait', $html);
        $this->assertStringContainsString('31.0', $html); // PDR sah tampil di RESEP
        $this->assertStringContainsString('31.5', $html); // PDL sah tampil di RESEP
    }

    public function test_show_page_displays_pd_in_prescription_context(): void
    {
        $rx = Prescription::factory()->create([
            'customer_id' => $this->customer->id, 'branch_id' => $this->branchA->id,
        ]);

        $this->actingAs($this->owner)->get('/resep/'.$rx->id)
            ->assertOk()
            ->assertSee('PDR')
            ->assertSee('PDL');
    }
}
