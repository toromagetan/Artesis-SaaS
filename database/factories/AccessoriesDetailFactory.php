<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\AccessoriesDetail;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccessoriesDetail>
 */
class AccessoriesDetailFactory extends Factory
{
    protected $model = AccessoriesDetail::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->forType('accessory'),
            'sku' => 'ACC-'.fake()->unique()->numberBetween(1000, 99999),
            'cost_price' => fake()->numberBetween(10_000, 300_000),
            'sale_price' => fake()->numberBetween(20_000, 500_000),
            'is_active' => true,
        ];
    }
}
