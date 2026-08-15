<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            User::factory()->raw(['name' => 'Test User']),
        );

        // Demo data for local development — see each seeder's docblock.
        // Idempotent (firstOrCreate throughout), safe to re-run.
        $this->call([
            LedAdSeeder::class,
            VipDemoSeeder::class,
        ]);
    }
}
