<?php

namespace Database\Factories;

use App\Modules\Master\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'name' => 'Cabang '.fake()->unique()->city(),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'is_active' => true,
        ];
    }
}
