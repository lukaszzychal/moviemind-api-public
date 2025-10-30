<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Person;

class HateoasService
{
    public function movieLinks(Movie $movie): array
    {
        return [
            'self' => url("/api/v1/movies/{$movie->slug}"),
            'people' => url("/api/v1/movies/{$movie->slug}"),
            'generate' => [
                'href' => url('/api/v1/generate'),
                'method' => 'POST',
                'body' => [
                    'entity_type' => 'MOVIE',
                    'entity_id' => $movie->id,
                ],
            ],
        ];
    }

    public function personLinks(Person $person): array
    {
        return [
            'self' => url("/api/v1/people/{$person->slug}"),
            'movies' => url('/api/v1/movies'),
        ];
    }
}


