<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Always seed subscription plans (needed for API keys)
        $this->call([
            SubscriptionPlanSeeder::class,
        ]);

        // Only seed test data in non-production environments
        if (! app()->environment('production', 'staging')) {
            $this->call([
                AdminUserSeeder::class, // Create default admin user
                ApiKeySeeder::class, // Create demo API keys for each plan
                GenreSeeder::class,
                MovieSeeder::class,
                PeopleSeeder::class,
                ActorSeeder::class, // Creates Keanu Reeves and links to The Matrix
            ]);
        }
    }
}
