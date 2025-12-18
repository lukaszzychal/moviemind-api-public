<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\RelationshipType;
use App\Jobs\SyncMovieRelationshipsJob;
use App\Models\Movie;
use App\Models\MovieRelationship;
use App\Models\TmdbSnapshot;
use App\Services\TmdbVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for SyncMovieRelationshipsJob.
 *
 * @author MovieMind API Team
 */
class SyncMovieRelationshipsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Log::fake();
    }

    /**
     * Scenario: Job handles missing movie gracefully
     *
     * Given: Movie does not exist
     * When: SyncMovieRelationshipsJob runs
     * Then: Job should log warning and return without error
     */
    public function test_job_handles_missing_movie_gracefully(): void
    {
        // Given: Movie does not exist
        $nonExistentMovieId = 99999;

        // When: Job runs
        $job = new SyncMovieRelationshipsJob($nonExistentMovieId);
        $tmdbService = $this->createMock(TmdbVerificationService::class);
        $job->handle($tmdbService);

        // Then: No error thrown, warning logged
        Log::assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'Movie not found');
        });
    }

    /**
     * Scenario: Job handles missing snapshot gracefully
     *
     * Given: Movie exists but has no TMDb snapshot
     * When: SyncMovieRelationshipsJob runs
     * Then: Job should log warning and return without error
     */
    public function test_job_handles_missing_snapshot_gracefully(): void
    {
        // Given: Movie exists but has no snapshot
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-2020',
            'release_year' => 2020,
        ]);

        // When: Job runs
        $job = new SyncMovieRelationshipsJob($movie->id);
        $tmdbService = $this->createMock(TmdbVerificationService::class);
        $job->handle($tmdbService);

        // Then: Warning logged
        Log::assertLogged('warning', function ($message, $context) {
            return str_contains($message, 'No TMDb snapshot found');
        });
    }

    /**
     * Scenario: Job creates relationships from collection data
     *
     * Given: Movie exists with TMDb snapshot containing collection
     * When: SyncMovieRelationshipsJob runs with collection data
     * Then: Relationships should be created for movies in collection
     */
    public function test_job_creates_relationships_from_collection(): void
    {
        // Given: Movies exist
        $movie1 = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
            'tmdb_id' => 603,
        ]);

        $movie2 = Movie::create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003',
            'release_year' => 2003,
            'tmdb_id' => 604,
        ]);

        $movie3 = Movie::create([
            'title' => 'The Matrix Revolutions',
            'slug' => 'the-matrix-revolutions-2003',
            'release_year' => 2003,
            'tmdb_id' => 605,
        ]);

        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie1->id,
            'tmdb_id' => 603,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 603,
                'belongs_to_collection' => [
                    'id' => 234,
                    'name' => 'The Matrix Collection',
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Mock TMDb service to return collection data
        $tmdbService = $this->createMock(TmdbVerificationService::class);
        $tmdbService->method('getMovieDetails')
            ->willReturn([
                'id' => 603,
                'belongs_to_collection' => [
                    'id' => 234,
                    'name' => 'The Matrix Collection',
                ],
            ]);

        $tmdbService->method('getCollectionDetails')
            ->willReturn([
                'id' => 234,
                'name' => 'The Matrix Collection',
                'parts' => [
                    ['id' => 603, 'title' => 'The Matrix', 'release_date' => '1999-03-31'],
                    ['id' => 604, 'title' => 'The Matrix Reloaded', 'release_date' => '2003-05-15'],
                    ['id' => 605, 'title' => 'The Matrix Revolutions', 'release_date' => '2003-11-05'],
                ],
            ]);

        // When: Job runs
        $job = new SyncMovieRelationshipsJob($movie1->id);
        $job->handle($tmdbService);

        // Then: Relationships should be created
        $this->assertEquals(2, MovieRelationship::where('movie_id', $movie1->id)->count());
        $this->assertTrue(
            MovieRelationship::where('movie_id', $movie1->id)
                ->where('related_movie_id', $movie2->id)
                ->where('relationship_type', RelationshipType::SEQUEL)
                ->exists()
        );
    }
}
