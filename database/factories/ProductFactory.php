<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Master produk (A1). Detail per tipe dibuat factory detail
 * masing-masing; factory ini hanya membuat masternya.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'category_id' => fn () => self::categoryId('frame'),
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'is_active' => true,
        ];
    }

    /** Master untuk kategori bertipe tertentu (frame/lens/rx/…). */
    public function forType(string $type): static
    {
        return $this->state(fn () => ['category_id' => self::categoryId($type)]);
    }

    public static function categoryId(string $type): ?int
    {
        return ProductCategory::query()
            ->where('type', $type)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->value('id');
    }
}
