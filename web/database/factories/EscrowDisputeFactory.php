<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EscrowDispute;
use App\Models\EscrowJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscrowDispute>
 */
class EscrowDisputeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'escrow_job_id' => EscrowJob::factory(),
            'opened_by_customer_id' => Customer::factory(),
            'reason' => fake()->sentence(),
            'status' => 'open',
        ];
    }
}
