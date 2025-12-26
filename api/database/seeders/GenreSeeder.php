<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        // Genres can be seeded in all environments (they're reference data)
        // But we'll skip in production/staging to be safe
        if (app()->environment('production', 'staging')) {
            $this->command->warn('GenreSeeder: Skipping test genres in production/staging environment');

            return;
        }
        foreach (['Action', 'Sci-Fi', 'Thriller'] as $name) {
            Genre::firstOrCreate([
                'slug' => Str::slug($name),
            ], [
                'name' => $name,
            ]);
        }
    }
}
