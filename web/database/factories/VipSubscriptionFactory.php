<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\VipSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VipSubscription>
 */
class VipSubscriptionFactory extends Factory
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
            'status' => 'active',
            'amount_sats' => 5000,
            'payment_hash' => bin2hex(random_bytes(32)),
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
        ];
    }
}
