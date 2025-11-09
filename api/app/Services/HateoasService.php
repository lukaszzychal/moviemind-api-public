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
        /** @var \Illuminate\Support\Collection<int, Person> $people */
        $people = $movie->people
            ->sortBy(function (Model $related, int $index): int {
                /** @var Person $person */
                $person = $related;

                return $person->pivot?->getAttribute('billing_order') ?? PHP_INT_MAX;
            });

        return [
            'self' => [
                'href' => url("/api/v1/movies/{$movie->slug}"),
            ],
            'people' => $people
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
                ->all(),
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
        /** @var \Illuminate\Support\Collection<int, Movie> $movies */
        $movies = $person->movies;

        return [
            'self' => [
                'href' => url("/api/v1/people/{$person->slug}"),
            ],
            'movies' => $movies
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
                ->all(),
        ];
    }
}
