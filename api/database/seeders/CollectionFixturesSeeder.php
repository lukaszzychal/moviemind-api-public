<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Movie;
use App\Models\TmdbSnapshot;
use Illuminate\Database\Seeder;

/**
 * Ensures The Matrix and two sequels exist with TMDb snapshots that have
 * belongs_to_collection so GET /movies/{slug}/collection returns 200.
 * See MANUAL_TESTING_GUIDE Scenario 7 and TC-MOVIE-007.
 */
class CollectionFixturesSeeder extends Seeder
{
    private const COLLECTION_ID = 234;

    private const COLLECTION_NAME = 'The Matrix Collection';

    public function run(): void
    {
        if (app()->environment('production', 'staging')) {
            $this->command->warn('CollectionFixturesSeeder: Skipping test data in production/staging');

            return;
        }

        $matrix = Movie::where('slug', 'the-matrix-1999')->first();
        if (! $matrix) {
            return;
        }

        $this->attachCollectionToSnapshot($matrix->id, 603, 'The Matrix');
        $reloaded = $this->ensureMovieAndSnapshot('the-matrix-reloaded-2003', 'The Matrix Reloaded', 2003, 604);
        $revolutions = $this->ensureMovieAndSnapshot('the-matrix-revolutions-2003', 'The Matrix Revolutions', 2003, 605);
        $this->attachCollectionToSnapshot($reloaded->id, 604, 'The Matrix Reloaded');
        $this->attachCollectionToSnapshot($revolutions->id, 605, 'The Matrix Revolutions');
    }

    private function attachCollectionToSnapshot(string $movieId, int $tmdbId, string $title): void
    {
        $snapshot = TmdbSnapshot::where('entity_type', 'MOVIE')->where('entity_id', $movieId)->first();
        if (! $snapshot) {
            TmdbSnapshot::create([
                'entity_type' => 'MOVIE',
                'entity_id' => $movieId,
                'tmdb_id' => $tmdbId,
                'tmdb_type' => 'movie',
                'raw_data' => [
                    'id' => $tmdbId,
                    'title' => $title,
                    'belongs_to_collection' => [
                        'id' => self::COLLECTION_ID,
                        'name' => self::COLLECTION_NAME,
                    ],
                ],
                'fetched_at' => now(),
            ]);

            return;
        }

        $raw = $snapshot->raw_data ?? [];
        $raw['belongs_to_collection'] = [
            'id' => self::COLLECTION_ID,
            'name' => self::COLLECTION_NAME,
        ];
        $snapshot->update(['raw_data' => $raw]);
    }

    private function ensureMovieAndSnapshot(string $slug, string $title, int $year, int $tmdbId): Movie
    {
        $movie = Movie::firstOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'release_year' => $year,
                'director' => 'The Wachowskis',
            ]
        );

        TmdbSnapshot::updateOrCreate(
            ['entity_type' => 'MOVIE', 'entity_id' => $movie->id],
            [
                'tmdb_id' => $tmdbId,
                'tmdb_type' => 'movie',
                'raw_data' => [
                    'id' => $tmdbId,
                    'title' => $title,
                    'belongs_to_collection' => [
                        'id' => self::COLLECTION_ID,
                        'name' => self::COLLECTION_NAME,
                    ],
                ],
                'fetched_at' => now(),
            ]
        );

        return $movie;
    }
}
