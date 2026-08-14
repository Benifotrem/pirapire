<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EscrowJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscrowJob>
 */
class EscrowJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 500000);
        $preimage = bin2hex(random_bytes(32));

        return [
            'creator_customer_id' => Customer::factory(),
            'description' => fake()->sentence(),
            'amount_sats' => $amount,
            'fee_sats' => (int) round($amount * 0.015),
            'status' => 'created',
            'hold_invoice' => 'lnbc'.fake()->regexify('[a-z0-9]{50}'),
            'payment_hash' => hash('sha256', hex2bin($preimage)),
            'preimage' => $preimage,
            'expires_at' => now()->addHour(),
        ];
    }
}
