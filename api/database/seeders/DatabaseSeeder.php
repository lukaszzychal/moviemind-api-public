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
            GenreSeeder::class,
            MovieSeeder::class,
            PeopleSeeder::class,
            ActorSeeder::class, // Creates Keanu Reeves and links to The Matrix
        ]);
    }
}
