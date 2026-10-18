<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductParent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numberBetween(1000, 99999),
            'type' => 'frame',
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'color' => fake()->safeColorName(),
            'cost_price' => fake()->numberBetween(50_000, 900_000),
            'sale_price' => fake()->numberBetween(100_000, 1_500_000),
            'is_active' => true,
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    /** Varian di bawah induk yang serumpun (ADR-015). */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->parent_id === null) {
                $parent = ProductParent::firstOrCreate(
                    ['type' => $product->type, 'brand' => $product->brand, 'name' => $product->name],
                    ['is_active' => true],
                );
                $product->update(['parent_id' => $parent->id]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
