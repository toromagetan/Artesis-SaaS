<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\SoftlensDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SoftlensDetail>
 */
class SoftlensDetailFactory extends Factory
{
    protected $model = SoftlensDetail::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->forType('softlens'),
            'sku' => 'SFT-'.fake()->unique()->numberBetween(1000, 99999),
            'color' => fake()->safeColorName(),
            'power' => -3.00,
            'expiry_tracked' => true,
            'cost_price' => fake()->numberBetween(50_000, 900_000),
            'sale_price' => fake()->numberBetween(100_000, 1_500_000),
            'is_active' => true,
        ];
    }
}
