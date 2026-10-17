<?php

namespace Tests\Feature\Clinical;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\Clinical\Services\PrecalService;
use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Master\Models\Branch;
use Database\Seeders\IdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * M.2 Precal (Blueprint D.5 + aturan MUTLAK owner): 11 parameter per mata;
 * PDR/PDL/PD dilarang muncul dalam struktur output, halaman, maupun
 * cetakan precal — dalam nama kunci maupun nilai.
 */
class PrecalServiceTest extends TestCase
{
    use RefreshDatabase;

    private Prescription $rx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(IdentitySeeder::class);
        $branch = Branch::where('name', 'Pusat')->firstOrFail();
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $this->rx = Prescription::factory()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'od_sph' => -1.50, 'od_cyl' => -0.50, 'od_axis' => 180, 'od_pdr' => 31.0,
            'os_sph' => -1.25, 'os_cyl' => -0.25, 'os_axis' => 175, 'os_pdl' => 32.5,
            'pd_total' => 63.5,
        ]);
    }

    public function test_eleven_parameters_per_eye_with_correct_math(): void
    {
        $precal = app(PrecalService::class)->compute($this->rx);

        $this->assertCount(11, $precal['od']);
        $this->assertCount(11, $precal['os']);

        // Daya total (SE): OD = -1.50 + (-0.50/2) = -1.75
        $this->assertSame(-1.75, $precal['od']['total_eye_power']);
        // Vertex: -1.75 / (1 - 0.012 * -1.75) = -1.71
        $this->assertSame(-1.71, $precal['od']['vertex_effective_power']);
        // Desentrasi OD: 35 - 31.0 = 4.0 → blank minimum 2*4 + 52 = 60.0
        $this->assertSame(4.0, $precal['od']['decentration_mm']);
        $this->assertSame(60.0, $precal['od']['minimum_blank_diameter_mm']);
        // Selisih SE antar mata 0.375 ≤ 0.50 → seimbang
        $this->assertSame('balanced', $precal['od']['pair_summary']);
        $this->assertSame('within_range', $precal['od']['rx_feasibility']);
    }

    public function test_output_structure_contains_no_pd_anywhere(): void
    {
        $precal = app(PrecalService::class)->compute($this->rx);

        $keys = [];
        $walk = function ($node) use (&$walk, &$keys) {
            foreach ($node as $key => $value) {
                $keys[] = (string) $key;
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($precal);

        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/pd/i', $key, "Kunci precal mengandung PD: {$key}");
        }

        $json = json_encode($precal);
        $this->assertStringNotContainsString('31.0', $json);
        $this->assertStringNotContainsString('32.5', $json);
        $this->assertStringNotContainsString('63.5', $json);
    }

    public function test_precal_pages_and_print_contain_no_pd(): void
    {
        $owner = User::where('username', 'owner')->firstOrFail();

        foreach (['/resep/'.$this->rx->id.'/precal', '/resep/'.$this->rx->id.'/precal/cetak'] as $url) {
            $html = $this->actingAs($owner)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('PDR', $html, $url);
            $this->assertStringNotContainsString('PDL', $html, $url);
            $this->assertStringNotContainsString('PD Total', $html, $url);
            $this->assertStringNotContainsString('>31.0<', $html, $url);
            $this->assertStringNotContainsString('>32.5<', $html, $url);
            $this->assertStringNotContainsString('>63.5<', $html, $url);
            $this->assertStringContainsString('Precal', $html, $url);
        }
    }

    public function test_out_of_range_power_is_flagged(): void
    {
        $this->rx->update(['od_sph' => -15.00]);

        $precal = app(PrecalService::class)->compute($this->rx->refresh());
        $this->assertSame('outside_range', $precal['od']['rx_feasibility']);
        $this->assertSame('within_range', $precal['os']['rx_feasibility']);
    }

    public function test_missing_pd_yields_null_decentration_and_blank(): void
    {
        $this->rx->update(['od_pdr' => null, 'os_pdl' => null, 'pd_total' => null]);

        $precal = app(PrecalService::class)->compute($this->rx->refresh());
        $this->assertNull($precal['od']['decentration_mm']);
        $this->assertNull($precal['od']['minimum_blank_diameter_mm']);
    }
}
