<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\LensDetail;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensDetail>
 */
class LensDetailFactory extends Factory
{
    protected $model = LensDetail::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->forType('lens'),
            'sku' => 'LNS-'.fake()->unique()->numberBetween(1000, 99999),
            'type' => 'SV',
            'index' => '1.56',
            'eye_side' => 'Both',
            'sph' => -2.00,
            'cyl' => 0.00,
            'cost_price' => fake()->numberBetween(50_000, 900_000),
            'sale_price' => fake()->numberBetween(100_000, 1_500_000),
            'is_active' => true,
        ];
    }
}
