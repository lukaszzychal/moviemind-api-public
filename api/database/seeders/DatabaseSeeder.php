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
        $this->call([
            SubscriptionPlanSeeder::class, // Must run first (before ApiKeySeeder if added later)
            GenreSeeder::class,
            MovieSeeder::class,
            PeopleSeeder::class,
            ActorSeeder::class, // Creates Keanu Reeves and links to The Matrix
        ]);
    }
}
