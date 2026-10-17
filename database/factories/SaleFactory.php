<?php

namespace Database\Factories;

use App\Modules\CRM\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'number' => 'INV-'.now()->format('Ym').'-'.fake()->unique()->numberBetween(1, 9999),
            'customer_id' => Customer::factory(),
            'branch_id' => fn (array $attributes) => Customer::find($attributes['customer_id'])->branch_id,
            'cashier_id' => User::factory(),
            'sale_date' => now()->toDateString(),
            'subtotal_amount' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'tax_mode' => 'inclusive',
            'total_amount' => 0,
            'paid_amount' => 0,
            'status' => 'active',
        ];
    }

    public function voided(): static
    {
        return $this->state(fn () => [
            'status' => 'void',
            'void_reason' => 'Uji void',
            'voided_at' => now(),
        ]);
    }
}
