<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SyncMovieMetadataJob;
use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncMovieMetadataJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Queue::fake();
    }

    /**
     * RED: Test that job syncs actors from TMDB data.
     * This test should FAIL initially (job doesn't exist yet).
     */
    public function test_syncs_actors_from_tmdb_data(): void
    {
        // Arrange: Create movie with TMDB snapshot
        $movie = Movie::create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999',
            'release_year' => 1999,
            'director' => 'Lana Wachowski',
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 603,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 603,
                'title' => 'The Matrix',
                'credits' => [
                    'cast' => [
                        [
                            'id' => 6384,
                            'name' => 'Keanu Reeves',
                            'character' => 'Neo',
                            'order' => 0,
                        ],
                        [
                            'id' => 2975,
                            'name' => 'Laurence Fishburne',
                            'character' => 'Morpheus',
                            'order' => 1,
                        ],
                    ],
                    'crew' => [
                        [
                            'id' => 12345,
                            'name' => 'Lana Wachowski',
                            'job' => 'Director',
                        ],
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Dispatch job
        $job = new SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Actors should be created and linked
        $this->assertDatabaseHas('people', [
            'name' => 'Keanu Reeves',
        ]);

        $this->assertDatabaseHas('people', [
            'name' => 'Laurence Fishburne',
        ]);

        $keanu = Person::where('name', 'Keanu Reeves')->first();
        $this->assertNotNull($keanu);
        $this->assertEquals('keanu-reeves', $keanu->slug);

        // Assert: Movie-Person relationships created
        $this->assertTrue($movie->people->contains($keanu));
        $this->assertEquals('Neo', $movie->people->firstWhere('name', 'Keanu Reeves')->pivot->character_name);
        $this->assertEquals('ACTOR', $movie->people->firstWhere('name', 'Keanu Reeves')->pivot->role);
    }

    /**
     * RED: Test that job handles missing TMDB snapshot gracefully.
     */
    public function test_handles_missing_snapshot_gracefully(): void
    {
        // Arrange: Create movie without snapshot
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-2000',
            'release_year' => 2000,
        ]);

        // Act & Assert: Job should not fail, just skip
        $job = new SyncMovieMetadataJob($movie->id);
        $job->handle();

        // No actors should be created
        $this->assertEquals(0, Person::count());
    }

    /**
     * RED: Test that job handles TMDB data without credits gracefully.
     */
    public function test_handles_missing_credits_gracefully(): void
    {
        // Arrange: Movie with snapshot but no credits
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-2000',
            'release_year' => 2000,
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 123,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 123,
                'title' => 'Test Movie',
                // No credits
            ],
            'fetched_at' => now(),
        ]);

        // Act & Assert: Job should not fail
        $job = new SyncMovieMetadataJob($movie->id);
        $job->handle();

        // No actors should be created
        $this->assertEquals(0, Person::count());
    }
}
