<?php

namespace Tests\Feature\Clinical;

use App\Modules\Clinical\Models\FittingMeasurement;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F5.2 Fitting AI — halaman ukur terpagar prescriptions.create,
 * penyimpanan angka fitting (termasuk rujukan pupilometer),
 * rekomendasi ukuran dari lebar wajah, dan riwayat di halaman ukur.
 */
class FittingMeasurementTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $this->owner = User::where('username', 'owner')->firstOrFail();
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $this->customer = Customer::factory()->create(['branch_id' => $branch->id]);
    }

    public function test_measure_page_and_store_with_size_recommendation(): void
    {
        $this->actingAs($this->owner)->get(route('fittings.measure', $this->customer))
            ->assertOk()
            ->assertSee('FaceLandmarker', false)
            ->assertSee('getUserMedia', false);

        $this->actingAs($this->owner)->post(route('fittings.store', $this->customer), [
            'method' => 'camera', 'pd_binocular' => 63.5, 'pd_mono_od' => 32.0, 'pd_mono_os' => 31.5,
            'face_width' => 138.0, 'pd_reference' => 63.0,
        ])->assertRedirect(route('fittings.measure', $this->customer));

        $row = FittingMeasurement::firstOrFail();
        $this->assertSame('63.5', (string) $row->pd_binocular);
        $this->assertSame('63.0', (string) $row->pd_reference);
        $this->assertSame('M', $row->frame_size_recommendation);
        $this->assertSame($this->owner->id, $row->measured_by);

        $this->actingAs($this->owner)->get(route('fittings.measure', $this->customer))
            ->assertOk()->assertSee('63.5');
    }

    public function test_store_requires_a_pd_and_size_boundaries(): void
    {
        $this->actingAs($this->owner)->post(route('fittings.store', $this->customer), [
            'method' => 'camera', 'face_width' => 120.0,
        ])->assertSessionHasErrors('pd_binocular');
        $this->assertSame(0, FittingMeasurement::count());

        $this->assertSame('S', FittingMeasurement::sizeForFaceWidth(128));
        $this->assertSame('M', FittingMeasurement::sizeForFaceWidth(141));
        $this->assertSame('L', FittingMeasurement::sizeForFaceWidth(150));
    }

    public function test_fitting_pages_are_permission_fenced(): void
    {
        $gudang = User::factory()->create();
        $gudang->roles()->sync([Role::where('name', 'Gudang')->firstOrFail()->id]);
        $this->actingAs($gudang)->get(route('fittings.measure', $this->customer))->assertForbidden();
        $this->actingAs($gudang)->post(route('fittings.store', $this->customer), [
            'method' => 'manual', 'pd_reference' => 62.0,
        ])->assertForbidden();
    }
}
