<?php

namespace Database\Factories;

use App\Models\Alert;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'currency' => fake()->randomElement(['PYG', 'USD']),
            'source' => 'all',
            'order_type' => fake()->randomElement(['BUY', 'SELL', 'ANY']),
            'min_amount' => null,
            'max_amount' => null,
            'payment_methods' => [],
            'is_active' => true,
        ];
    }
}
