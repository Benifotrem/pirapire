<?php

namespace Database\Factories;

use App\Models\LedAd;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LedAd>
 */
class LedAdFactory extends Factory
{
    public function definition(): array
    {
        return [
            'message' => fake()->catchPhrase(),
            'url' => fake()->url(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
