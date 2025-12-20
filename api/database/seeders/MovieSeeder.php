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
        $matrix = Movie::create([
            'title' => 'The Matrix',
            'slug' => Movie::generateSlug('The Matrix', 1999, 'The Wachowskis'),
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);

        $desc = MovieDescription::create([
            'movie_id' => $matrix->id,
            'locale' => Locale::EN_US,
            'text' => 'A hacker discovers the truth about reality and leads a rebellion.',
            'context_tag' => ContextTag::MODERN,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $matrix->update(['default_description_id' => $desc->id]);

        $inception = Movie::create([
            'title' => 'Inception',
            'slug' => Movie::generateSlug('Inception', 2010, 'Christopher Nolan'),
            'release_year' => 2010,
            'director' => 'Christopher Nolan',
        ]);

        $desc2 = MovieDescription::create([
            'movie_id' => $inception->id,
            'locale' => Locale::EN_US,
            'text' => 'A thief enters dreams to steal secrets, facing a final, complex mission.',
            'context_tag' => ContextTag::MODERN,
            'origin' => DescriptionOrigin::GENERATED,
            'ai_model' => 'mock',
            'version_number' => 1,
            'archived_at' => null,
        ]);

        $inception->update(['default_description_id' => $desc2->id]);

        // attach genres via pivot
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
