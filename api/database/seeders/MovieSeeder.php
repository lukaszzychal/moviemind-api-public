<?php

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Database\Seeder;

class MovieSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = Movie::create([
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
            'genres' => ['Action', 'Sci-Fi'],
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
            'genres' => ['Action', 'Sci-Fi', 'Thriller'],
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
    }
}


