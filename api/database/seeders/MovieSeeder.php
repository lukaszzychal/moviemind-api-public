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

        // Use fixed slugs so MANUAL_TESTING_GUIDE and TC-MOVIE-* scenarios pass (the-matrix-1999, inception-2010).
        $matrix = Movie::updateOrCreate(
            ['slug' => 'the-matrix-1999'],
            [
                'title' => 'The Matrix',
                'release_year' => 1999,
                'director' => 'The Wachowskis',
            ]
        );

        if (! $matrix->default_description_id) {
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

        \App\Models\TmdbSnapshot::updateOrCreate(
            ['entity_type' => 'MOVIE', 'entity_id' => $matrix->id],
            ['tmdb_id' => 603, 'tmdb_type' => 'movie', 'raw_data' => ['id' => 603, 'title' => 'The Matrix'], 'fetched_at' => \Illuminate\Support\Carbon::now()]
        );

        $inception = Movie::updateOrCreate(
            ['slug' => 'inception-2010'],
            [
                'title' => 'Inception',
                'release_year' => 2010,
                'director' => 'Christopher Nolan',
            ]
        );

        if (! $inception->default_description_id) {
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

        \App\Models\TmdbSnapshot::updateOrCreate(
            ['entity_type' => 'MOVIE', 'entity_id' => $inception->id],
            ['tmdb_id' => 27205, 'tmdb_type' => 'movie', 'raw_data' => ['id' => 27205, 'title' => 'Inception', 'overview' => 'A thief stealing corporate secrets through the use of dream-sharing technology is given the inverse task of planting an idea into the mind of a C.E.O.', 'release_date' => '2010-07-15'], 'fetched_at' => \Illuminate\Support\Carbon::now()]
        );

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
