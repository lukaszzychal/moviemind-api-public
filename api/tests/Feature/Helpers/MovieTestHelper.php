<?php

declare(strict_types=1);

namespace Tests\Feature\Helpers;

use App\Enums\RoleType;
use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;

/**
 * Helper class for creating test movies with common scenarios.
 */
class MovieTestHelper
{
    /**
     * Create a movie with actors attached.
     *
     * @param  array<int, array{name: string, character?: string|null, tmdb_id?: int|null, order?: int|null}>  $actors
     * @param  array<string, mixed>  $movieAttributes
     */
    public static function createMovieWithActors(array $actors, array $movieAttributes = []): Movie
    {
        $defaultAttributes = [
            'title' => 'Test Movie',
            'slug' => 'test-movie-'.uniqid(),
            'release_year' => 2020,
            'director' => 'Test Director',
        ];

        $movie = Movie::create(array_merge($defaultAttributes, $movieAttributes));

        foreach ($actors as $actorData) {
            $person = Person::firstOrCreate(
                ['tmdb_id' => $actorData['tmdb_id'] ?? null],
                [
                    'name' => $actorData['name'],
                    'slug' => \Illuminate\Support\Str::slug($actorData['name']),
                ]
            );

            $movie->people()->attach($person->id, [
                'role' => RoleType::ACTOR->value,
                'character_name' => $actorData['character'] ?? null,
                'billing_order' => $actorData['order'] ?? null,
            ]);
        }

        return $movie->fresh(['people']);
    }

    /**
     * Create a movie with TMDB snapshot containing credits.
     *
     * @param  array<string, mixed>  $movieAttributes
     * @param  array<int, array{id?: int|null, name?: string|null, character?: string|null, order?: int|null}>  $cast
     * @param  array<int, array{id?: int|null, name?: string|null, job?: string|null}>  $crew
     * @return array{Movie, TmdbSnapshot}
     */
    public static function createMovieWithTmdbSnapshot(
        array $movieAttributes = [],
        array $cast = [],
        array $crew = []
    ): array {
        $defaultAttributes = [
            'title' => 'Test Movie',
            'slug' => 'test-movie-'.uniqid(),
            'release_year' => 2020,
            'director' => 'Test Director',
            'tmdb_id' => 99999,
        ];

        $movie = Movie::create(array_merge($defaultAttributes, $movieAttributes));

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => $movie->tmdb_id ?? 99999,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => $movie->tmdb_id ?? 99999,
                'title' => $movie->title,
                'credits' => [
                    'cast' => $cast,
                    'crew' => $crew,
                ],
            ],
            'fetched_at' => now(),
        ]);

        return [$movie, $snapshot];
    }

    /**
     * Create a movie with crew members attached.
     *
     * @param  array<int, array{name: string, job: string, tmdb_id?: int|null}>  $crew
     * @param  array<string, mixed>  $movieAttributes
     */
    public static function createMovieWithCrew(array $crew, array $movieAttributes = []): Movie
    {
        $defaultAttributes = [
            'title' => 'Test Movie',
            'slug' => 'test-movie-'.uniqid(),
            'release_year' => 2020,
            'director' => 'Test Director',
        ];

        $movie = Movie::create(array_merge($defaultAttributes, $movieAttributes));

        $jobToRole = [
            'Director' => RoleType::DIRECTOR,
            'Writer' => RoleType::WRITER,
            'Screenplay' => RoleType::WRITER,
            'Producer' => RoleType::PRODUCER,
            'Executive Producer' => RoleType::PRODUCER,
        ];

        foreach ($crew as $crewMember) {
            $person = Person::firstOrCreate(
                ['tmdb_id' => $crewMember['tmdb_id'] ?? null],
                [
                    'name' => $crewMember['name'],
                    'slug' => \Illuminate\Support\Str::slug($crewMember['name']),
                ]
            );

            $job = $crewMember['job'];
            $role = $jobToRole[$job] ?? null;

            if ($role) {
                $movie->people()->attach($person->id, [
                    'role' => $role->value,
                    'job' => $job,
                ]);
            }
        }

        return $movie->fresh(['people']);
    }
}
