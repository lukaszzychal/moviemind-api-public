<?php

namespace Database\Seeders;

use App\Enums\ContextTag;
use App\Enums\DescriptionOrigin;
use App\Enums\Locale;
use App\Models\Genre;
use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('MovieSeeder: Skipping test data in production/staging environment');

            return;
        }

        $matrix = Movie::firstOrCreate(
            ['slug' => Movie::generateSlug('The Matrix', 1999, 'The Wachowskis')],
            [
                'title' => 'The Matrix',
                'release_year' => 1999,
                'director' => 'The Wachowskis',
            ]
        );

        if ($matrix->wasRecentlyCreated) {
            $desc = MovieDescription::create([
                'movie_id' => $matrix->id,
                'locale' => Locale::EN_US,
                'text' => 'A hacker discovers the truth about reality and leads a rebellion.',
                'context_tag' => ContextTag::MODERN,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock',
            ]);
            $matrix->update(['default_description_id' => $desc->id]);
        }

        $inception = Movie::firstOrCreate(
            ['slug' => Movie::generateSlug('Inception', 2010, 'Christopher Nolan')],
            [
                'title' => 'Inception',
                'release_year' => 2010,
                'director' => 'Christopher Nolan',
            ]
        );

        if ($inception->wasRecentlyCreated) {
            $desc2 = MovieDescription::create([
                'movie_id' => $inception->id,
                'locale' => Locale::EN_US,
                'text' => 'A thief enters dreams to steal secrets, facing a final, complex mission.',
                'context_tag' => ContextTag::MODERN,
                'origin' => DescriptionOrigin::GENERATED,
                'ai_model' => 'mock',
            ]);
            $inception->update(['default_description_id' => $desc2->id]);
        }

        $attach = function (Movie $movie, array $names) {
            $ids = [];
            foreach ($names as $name) {
                $genre = Genre::firstOrCreate(['slug' => \Illuminate\Support\Str::slug($name)], ['name' => $name]);
                $ids[] = $genre->id;
            }
            $movie->genres()->syncWithoutDetaching($ids);
        };

        $attach($matrix, ['Action', 'Sci-Fi']);
        $attach($inception, ['Action', 'Sci-Fi', 'Thriller']);
    }
}
