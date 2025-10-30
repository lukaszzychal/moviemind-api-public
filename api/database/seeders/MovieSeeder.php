<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\MovieDescription;
use App\Models\Genre;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = Movie::create([
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
        ]);

        $desc = MovieDescription::create([
            'movie_id' => $matrix->id,
            'locale' => 'en-US',
            'text' => 'A hacker discovers the truth about reality and leads a rebellion.',
            'context_tag' => 'modern',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
        ]);

        $matrix->update(['default_description_id' => $desc->id]);

        $inception = Movie::create([
            'title' => 'Inception',
            'release_year' => 2010,
            'director' => 'Christopher Nolan',
        ]);

        $desc2 = MovieDescription::create([
            'movie_id' => $inception->id,
            'locale' => 'en-US',
            'text' => 'A thief enters dreams to steal secrets, facing a final, complex mission.',
            'context_tag' => 'modern',
            'origin' => 'GENERATED',
            'ai_model' => 'mock',
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


