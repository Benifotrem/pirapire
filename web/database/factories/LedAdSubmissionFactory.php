<?php

namespace Database\Factories;

use App\Models\LedAdSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedAdSubmission>
 */
class LedAdSubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_name' => fake()->company(),
            'category' => fake()->randomElement(['cafeteria', 'restaurante', 'tienda', 'hotel', 'servicios', 'otro']),
            'description' => fake()->sentence(),
            'address' => fake()->address(),
            'city' => fake()->randomElement(['Asunción', 'Ciudad del Este', 'Encarnación']),
            'business_hours' => 'Lun a Vie 8:00–18:00',
            'accepts_lightning' => true,
            'accepts_onchain' => true,
            'message' => fake()->catchPhrase(),
            'url' => fake()->url(),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'status' => 'pending',
        ];
    }
}
