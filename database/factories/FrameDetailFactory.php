<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\FrameDetail;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FrameDetail>
 */
class FrameDetailFactory extends Factory
{
    protected $model = FrameDetail::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->forType('frame'),
            'sku' => 'FRM-'.fake()->unique()->numberBetween(1000, 99999),
            'color' => fake()->safeColorName(),
            'cost_price' => fake()->numberBetween(50_000, 900_000),
            'sale_price' => fake()->numberBetween(100_000, 1_500_000),
            'is_active' => true,
        ];
    }
}
