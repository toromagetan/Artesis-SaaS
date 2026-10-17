<?php

namespace Database\Factories;

use App\Modules\Clinical\Models\Prescription;
use App\Modules\CRM\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prescription>
 */
class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'branch_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])->branch_id,
            'examined_at' => fake()->date(),
            'examiner_id' => null,
            'od_sph' => -1.50,
            'od_cyl' => -0.50,
            'od_axis' => 180,
            'od_add' => null,
            'od_pdr' => 31.0,
            'os_sph' => -1.25,
            'os_cyl' => -0.25,
            'os_axis' => 175,
            'os_add' => null,
            'os_pdl' => 31.5,
            'pd_total' => 62.5,
            'prism' => null,
            'notes' => null,
            'used_at' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => ['used_at' => now()]);
    }
}
