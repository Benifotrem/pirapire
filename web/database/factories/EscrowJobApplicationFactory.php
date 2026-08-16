<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Models\EscrowJobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EscrowJobApplication>
 */
class EscrowJobApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'escrow_job_id' => EscrowJob::factory(),
            'freelancer_customer_id' => Customer::factory(),
            'message' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }
}
