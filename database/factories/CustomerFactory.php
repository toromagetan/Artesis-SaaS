<?php

namespace Database\Factories;

use App\Modules\CRM\Models\Customer;
use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '08'.fake()->numerify('##########'),
            'email' => fake()->optional()->safeEmail(),
            'address' => fake()->optional()->address(),
            'birth_date' => fake()->optional()->date(),
            'branch_id' => Branch::factory(),
            'insurance_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
