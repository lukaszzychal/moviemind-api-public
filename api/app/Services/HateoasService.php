<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\Person;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class HateoasService
{
    public function movieLinks(Movie $movie): array
    {
        $links = [
            'self' => [
                'href' => url("/api/v1/movies/{$movie->slug}"),
            ],
            'generate' => [
                'href' => url('/api/v1/generate'),
                'method' => 'POST',
                'body' => [
                    'entity_type' => 'MOVIE',
                    'entity_id' => $movie->id,
                ],
            ],
        ];

        // Only load people links if the relation is already loaded (avoid lazy loading)
        // This prevents unnecessary database queries when people relation is not needed
        if ($movie->relationLoaded('people')) {
            /** @var \Illuminate\Support\Collection<int, Person> $people */
            $people = $movie->people
                ->sortBy(function (Model $related, int $index): int {
                    /** @var Person $person */
                    $person = $related;

                    return $person->pivot?->getAttribute('billing_order') ?? PHP_INT_MAX;
                });

            $links['people'] = $people
                ->map(function (Person $person): array {
                    /** @var Pivot|null $pivot */
                    $pivot = $person->pivot;

                    $link = [
                        'href' => url("/api/v1/people/{$person->slug}"),
                        'title' => $person->name,
                    ];

                    $role = $pivot?->getAttribute('role');
                    if ($role) {
                        $link['role'] = $role;
                    }

                    $characterName = $pivot?->getAttribute('character_name');
                    if ($characterName) {
                        $link['character_name'] = $characterName;
                    }

                    $job = $pivot?->getAttribute('job');
                    if ($job) {
                        $link['job'] = $job;
                    }

                    $billingOrder = $pivot?->getAttribute('billing_order');
                    if ($billingOrder !== null) {
                        $link['billing_order'] = $billingOrder;
                    }

                    return $link;
                })
                ->values()
                ->all();
        } else {
            // If relation is not loaded, return empty array to indicate no people links available
            // This prevents lazy loading which would cause N+1 query problem
            $links['people'] = [];
        }

        return $links;
    }

    public function personLinks(Person $person): array
    {
        $links = [
            'self' => [
                'href' => url("/api/v1/people/{$person->slug}"),
            ],
        ];

        // Only load movies links if the relation is already loaded (avoid lazy loading)
        // This prevents unnecessary database queries when movies relation is not needed
        if ($person->relationLoaded('movies')) {
            /** @var \Illuminate\Support\Collection<int, Movie> $movies */
            $movies = $person->movies;

            $links['movies'] = $movies
                ->map(function (Movie $movie): array {
                    /** @var Pivot|null $pivot */
                    $pivot = $movie->pivot;

                    $link = [
                        'href' => url("/api/v1/movies/{$movie->slug}"),
                        'title' => $movie->title,
                    ];

                    $role = $pivot?->getAttribute('role');
                    if ($role) {
                        $link['role'] = $role;
                    }

                    $characterName = $pivot?->getAttribute('character_name');
                    if ($characterName) {
                        $link['character_name'] = $characterName;
                    }

                    $job = $pivot?->getAttribute('job');
                    if ($job) {
                        $link['job'] = $job;
                    }

                    $billingOrder = $pivot?->getAttribute('billing_order');
                    if ($billingOrder !== null) {
                        $link['billing_order'] = $billingOrder;
                    }

                    return $link;
                })
                ->values()
                ->all();
        } else {
            // If relation is not loaded, return empty array to indicate no movies links available
            // This prevents lazy loading which would cause N+1 query problem
            $links['movies'] = [];
        }

        return $links;
    }
}
