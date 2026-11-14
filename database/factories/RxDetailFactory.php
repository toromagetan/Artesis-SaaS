<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\RxDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RxDetail>
 */
class RxDetailFactory extends Factory
{
    protected $model = RxDetail::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->forType('rx'),
            'sku' => 'RX-'.fake()->unique()->numberBetween(1000, 99999),
            'cost_price' => fake()->numberBetween(50_000, 900_000),
            'sale_price' => fake()->numberBetween(100_000, 1_500_000),
            'is_active' => true,
        ];
    }
}
