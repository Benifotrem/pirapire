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

        return [
            'creator_customer_id' => Customer::factory(),
            'counterparty_customer_id' => Customer::factory(),
            'description' => fake()->sentence(),
            'amount_sats' => $amount,
            'fee_sats' => (int) round($amount * 0.015),
            // 'assigned': a freelancer has been picked and the funding
            // invoice generated, closest default to the old "created"
            // state now that jobs start out 'open' (no invoice, no
            // freelancer) instead. Tests needing an 'open', unassigned
            // job should override counterparty_customer_id to null.
            'status' => 'assigned',
            'funding_invoice' => 'lnbc'.fake()->regexify('[a-z0-9]{50}'),
            'payment_hash' => fake()->sha256(),
            'expires_at' => now()->addHour(),
        ];
    }
}
