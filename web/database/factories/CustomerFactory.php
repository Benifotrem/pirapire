<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'linking_key' => bin2hex(random_bytes(33)),
            'display_name' => fake()->userName(),
            'telegram_chat_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
        ];
    }
}
