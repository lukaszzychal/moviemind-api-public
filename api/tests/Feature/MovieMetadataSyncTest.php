<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\Person;
use App\Models\TmdbSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MovieMetadataSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * RED: Test that movie creation triggers metadata sync job.
     */
    public function test_movie_creation_triggers_metadata_sync_job(): void
    {
        Queue::fake();

        // Use unique slug to ensure new movie is created
        $uniqueSlug = 'test-movie-sync-'.uniqid();

        // Act: Create movie via TmdbMovieCreationService
        $service = app(\App\Services\TmdbMovieCreationService::class);
        $movie = $service->createFromTmdb([
            'id' => 99999,
            'title' => 'Test Movie Sync',
            'release_date' => '2020-01-01',
            'overview' => 'Test overview',
            'director' => 'Test Director',
        ], $uniqueSlug);

        // Assert: Movie was created
        $this->assertNotNull($movie);

        // Assert: SyncMovieMetadataJob should be dispatched
        Queue::assertPushed(\App\Jobs\SyncMovieMetadataJob::class, function ($job) use ($movie) {
            return $job->movieId === $movie->id;
        });
    }

    /**
     * RED: Test that refresh endpoint does NOT sync actors.
     */
    public function test_refresh_endpoint_does_not_sync_actors(): void
    {
        Queue::fake();

        // Arrange: Create movie with existing actors (use different slug to avoid conflicts)
        $movie = Movie::firstOrCreate(
            ['slug' => 'test-matrix-refresh'],
            [
                'title' => 'Test Matrix',
                'release_year' => 1999,
                'director' => 'Test Director',
            ]
        );

        // Clear any existing people relationships
        $movie->people()->detach();

        $actor = Person::firstOrCreate(
            ['slug' => 'test-keanu-reeves'],
            [
                'name' => 'Test Keanu Reeves',
            ]
        );

        $movie->people()->attach($actor->id, [
            'role' => 'ACTOR',
            'character_name' => 'Neo',
            'billing_order' => 0,
        ]);

        $snapshot = TmdbSnapshot::firstOrCreate(
            [
                'entity_type' => 'MOVIE',
                'entity_id' => $movie->id,
            ],
            [
                'tmdb_id' => 999,
                'tmdb_type' => 'movie',
                'raw_data' => [
                    'id' => 999,
                    'title' => 'Test Matrix',
                    'credits' => [
                        'cast' => [
                            ['id' => 6384, 'name' => 'Test Keanu Reeves', 'character' => 'Neo', 'order' => 0],
                        ],
                    ],
                ],
                'fetched_at' => now()->subDay(),
            ]
        );

        // Mock TmdbVerificationService (refreshMovieDetails is not in EntityVerificationServiceInterface)
        // This is acceptable as TmdbVerificationService is a concrete class, not an interface
        $this->mock(\App\Services\TmdbVerificationService::class, function ($mock) use ($snapshot) {
            $mock->shouldReceive('refreshMovieDetails')
                ->with($snapshot->tmdb_id)
                ->andReturn([
                    'id' => $snapshot->tmdb_id,
                    'title' => 'Test Matrix',
                    'release_date' => '1999-03-31',
                    'overview' => 'Updated overview',
                    'director' => 'Test Director',
                    // Note: No credits in refresh response (should be removed by refreshMovieDetails)
                ]);
        });

        // Act: Refresh movie
        $response = $this->postJson("/api/v1/movies/{$movie->slug}/refresh");

        // Assert: Refresh succeeds
        $response->assertStatus(200);

        // Assert: SyncMovieMetadataJob should NOT be dispatched
        Queue::assertNotPushed(\App\Jobs\SyncMovieMetadataJob::class);

        // Assert: Actors remain unchanged
        $this->assertEquals(1, $movie->fresh()->people->count());
        $this->assertEquals('Test Keanu Reeves', $movie->fresh()->people->first()->name);
    }

    /**
     * Test that SyncMovieMetadataJob synchronizes actors and crew from TMDB snapshot.
     * Based on TEST_RESULTS_ETAP3.md - Scenariusz 1.
     */
    public function test_sync_movie_metadata_job_synchronizes_actors_and_crew(): void
    {
        // Arrange: Create movie with TMDB snapshot containing credits
        $movie = Movie::create([
            'title' => 'Test Matrix Sync',
            'slug' => 'test-matrix-sync-1999',
            'release_year' => 1999,
            'director' => 'Test Director',
            'tmdb_id' => 603,
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
                        ['id' => 6384, 'name' => 'Keanu Reeves', 'character' => 'Neo', 'order' => 0],
                        ['id' => 2975, 'name' => 'Laurence Fishburne', 'character' => 'Morpheus', 'order' => 1],
                        ['id' => 530, 'name' => 'Carrie-Anne Moss', 'character' => 'Trinity', 'order' => 2],
                    ],
                    'crew' => [
                        ['id' => 172069, 'name' => 'Lana Wachowski', 'job' => 'Director'],
                        ['id' => 172069, 'name' => 'Lana Wachowski', 'job' => 'Writer'],
                        ['id' => 172070, 'name' => 'Lilly Wachowski', 'job' => 'Director'],
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Actors are synchronized
        $movie->refresh();
        $this->assertEquals(6, $movie->people->count(), 'Should have 6 people (3 actors + 3 crew)');

        // Assert: Actors with character names
        $keanu = Person::where('tmdb_id', 6384)->first();
        $this->assertNotNull($keanu, 'Keanu Reeves should be created');
        $this->assertEquals('Keanu Reeves', $keanu->name);
        $this->assertTrue($movie->people->contains($keanu));
        $keanuPivot = $movie->people->firstWhere('id', $keanu->id)->pivot;
        $this->assertEquals('ACTOR', $keanuPivot->role);
        $this->assertEquals('Neo', $keanuPivot->character_name);
        $this->assertEquals(0, $keanuPivot->billing_order);

        $laurence = Person::where('tmdb_id', 2975)->first();
        $this->assertNotNull($laurence);
        $this->assertEquals('Laurence Fishburne', $laurence->name);
        $laurencePivot = $movie->people->firstWhere('id', $laurence->id)->pivot;
        $this->assertEquals('Morpheus', $laurencePivot->character_name);

        $carrie = Person::where('tmdb_id', 530)->first();
        $this->assertNotNull($carrie);
        $this->assertEquals('Carrie-Anne Moss', $carrie->name);

        // Assert: Crew is synchronized
        $lana = Person::where('tmdb_id', 172069)->first();
        $this->assertNotNull($lana);
        $this->assertEquals('Lana Wachowski', $lana->name);

        // Lana should have both DIRECTOR and WRITER roles
        $lanaDirector = $movie->people()
            ->where('person_id', $lana->id)
            ->where('role', 'DIRECTOR')
            ->first();
        $this->assertNotNull($lanaDirector, 'Lana should be DIRECTOR');

        $lanaWriter = $movie->people()
            ->where('person_id', $lana->id)
            ->where('role', 'WRITER')
            ->first();
        $this->assertNotNull($lanaWriter, 'Lana should be WRITER');

        $lilly = Person::where('tmdb_id', 172070)->first();
        $this->assertNotNull($lilly);
        $this->assertEquals('Lilly Wachowski', $lilly->name);
        $lillyDirector = $movie->people()
            ->where('person_id', $lilly->id)
            ->where('role', 'DIRECTOR')
            ->first();
        $this->assertNotNull($lillyDirector, 'Lilly should be DIRECTOR');
    }

    /**
     * Test that crew (director, writer, producer) is synchronized correctly.
     * Based on TEST_RESULTS_ETAP3.md - Scenariusz 3.
     */
    public function test_sync_movie_metadata_job_synchronizes_crew_correctly(): void
    {
        // Arrange: Create movie with crew data
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-2010',
            'release_year' => 2010,
            'director' => 'Test Director',
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 12345,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 12345,
                'title' => 'Test Movie',
                'credits' => [
                    'crew' => [
                        ['id' => 100, 'name' => 'John Director', 'job' => 'Director'],
                        ['id' => 101, 'name' => 'Jane Writer', 'job' => 'Writer'],
                        ['id' => 102, 'name' => 'Bob Screenplay', 'job' => 'Screenplay'],
                        ['id' => 103, 'name' => 'Alice Producer', 'job' => 'Producer'],
                        ['id' => 104, 'name' => 'Charlie Executive', 'job' => 'Executive Producer'],
                        ['id' => 105, 'name' => 'Unknown Job', 'job' => 'Unknown Job'], // Should be skipped
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Crew is synchronized with correct roles
        $movie->refresh();
        $this->assertGreaterThan(0, $movie->people->count());

        // Director
        $john = Person::where('tmdb_id', 100)->first();
        $this->assertNotNull($john);
        $johnPivot = $movie->people()->where('person_id', $john->id)->where('role', 'DIRECTOR')->first();
        $this->assertNotNull($johnPivot, 'John should be DIRECTOR');

        // Writer (both Writer and Screenplay map to WRITER)
        $jane = Person::where('tmdb_id', 101)->first();
        $this->assertNotNull($jane);
        $janePivot = $movie->people()->where('person_id', $jane->id)->where('role', 'WRITER')->first();
        $this->assertNotNull($janePivot, 'Jane should be WRITER');

        $bob = Person::where('tmdb_id', 102)->first();
        $this->assertNotNull($bob);
        $bobPivot = $movie->people()->where('person_id', $bob->id)->where('role', 'WRITER')->first();
        $this->assertNotNull($bobPivot, 'Bob (Screenplay) should be WRITER');

        // Producer (both Producer and Executive Producer map to PRODUCER)
        $alice = Person::where('tmdb_id', 103)->first();
        $this->assertNotNull($alice);
        $alicePivot = $movie->people()->where('person_id', $alice->id)->where('role', 'PRODUCER')->first();
        $this->assertNotNull($alicePivot, 'Alice should be PRODUCER');

        $charlie = Person::where('tmdb_id', 104)->first();
        $this->assertNotNull($charlie);
        $charliePivot = $movie->people()->where('person_id', $charlie->id)->where('role', 'PRODUCER')->first();
        $this->assertNotNull($charliePivot, 'Charlie (Executive Producer) should be PRODUCER');

        // Unknown job should be skipped
        $unknown = Person::where('tmdb_id', 105)->first();
        if ($unknown) {
            $unknownPivot = $movie->people()->where('person_id', $unknown->id)->first();
            $this->assertNull($unknownPivot, 'Unknown job should not be linked to movie');
        }
    }

    /**
     * Test that tmdb_id is NOT visible in API responses.
     * Based on TEST_RESULTS_ETAP3.md - Scenariusz 4.
     */
    public function test_tmdb_id_is_not_visible_in_api_responses(): void
    {
        // Arrange: Create movie with tmdb_id and synchronized people
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-api-1999',
            'release_year' => 1999,
            'director' => 'Test Director',
            'tmdb_id' => 603,
        ]);

        $person = Person::create([
            'name' => 'Test Actor',
            'slug' => 'test-actor',
            'tmdb_id' => 6384,
        ]);

        $movie->people()->attach($person->id, [
            'role' => 'ACTOR',
            'character_name' => 'Test Character',
        ]);

        // Act: Get movie via API
        $response = $this->getJson("/api/v1/movies/{$movie->slug}");

        // Assert: Movie response does not contain tmdb_id
        $response->assertStatus(200)
            ->assertJsonMissing(['tmdb_id']);

        $data = $response->json();
        $this->assertArrayNotHasKey('tmdb_id', $data, 'Movie should not have tmdb_id in API response');

        // Assert: People in response do not contain tmdb_id
        if (isset($data['people']) && is_array($data['people']) && count($data['people']) > 0) {
            foreach ($data['people'] as $personData) {
                $this->assertArrayNotHasKey('tmdb_id', $personData, 'Person should not have tmdb_id in API response');
            }
        }

        // Verify tmdb_id exists in database
        $this->assertDatabaseHas('movies', [
            'id' => $movie->id,
            'tmdb_id' => 603,
        ]);

        $this->assertDatabaseHas('people', [
            'id' => $person->id,
            'tmdb_id' => 6384,
        ]);
    }

    /**
     * Test edge case: Duplicate persons (same tmdb_id, different names).
     * Should use tmdb_id to find existing person and update if needed.
     */
    public function test_handles_duplicate_persons_by_tmdb_id(): void
    {
        // Arrange: Create person with tmdb_id (use unique tmdb_id to avoid conflicts)
        $uniqueTmdbId = 99999;
        $existingPerson = Person::firstOrCreate(
            ['tmdb_id' => $uniqueTmdbId],
            [
                'name' => 'Test Actor Original',
                'slug' => 'test-actor-original-'.uniqid(),
            ]
        );

        $movie = Movie::create([
            'title' => 'Test Movie Duplicate',
            'slug' => 'test-movie-duplicate-'.uniqid(),
            'release_year' => 1999,
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 88888,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 88888,
                'credits' => [
                    'cast' => [
                        // Same tmdb_id but different name (should use existing person)
                        ['id' => $uniqueTmdbId, 'name' => 'Test Actor Updated Name', 'character' => 'Character', 'order' => 0],
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Should use existing person, not create duplicate
        $this->assertEquals(1, Person::where('tmdb_id', $uniqueTmdbId)->count(), 'Should have only one person with this tmdb_id');
        $this->assertTrue($movie->fresh()->people->contains($existingPerson), 'Movie should link to existing person');

        // Verify the existing person is linked (name may differ, but same person)
        $linkedPerson = $movie->fresh()->people->firstWhere('id', $existingPerson->id);
        $this->assertNotNull($linkedPerson, 'Existing person should be linked to movie');
    }

    /**
     * Test edge case: Person without tmdb_id in TMDB data.
     * Should create person by name only.
     */
    public function test_handles_person_without_tmdb_id(): void
    {
        // Arrange: Movie with cast member without tmdb_id
        $movie = Movie::create([
            'title' => 'Test Movie',
            'slug' => 'test-movie-no-tmdb-id',
            'release_year' => 2000,
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 12345,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 12345,
                'credits' => [
                    'cast' => [
                        ['id' => null, 'name' => 'Unknown Actor', 'character' => 'Character', 'order' => 0],
                        // Or missing 'id' key entirely
                        ['name' => 'Another Actor', 'character' => 'Another Character', 'order' => 1],
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Persons should be created by name
        $unknown = Person::where('name', 'Unknown Actor')->first();
        $this->assertNotNull($unknown, 'Should create person by name even without tmdb_id');
        $this->assertNull($unknown->tmdb_id, 'Person should have null tmdb_id');

        $another = Person::where('name', 'Another Actor')->first();
        $this->assertNotNull($another, 'Should create person by name even without tmdb_id');
    }

    /**
     * Test edge case: Empty cast/crew arrays.
     * Should handle gracefully without errors.
     */
    public function test_handles_empty_cast_and_crew_arrays(): void
    {
        // Arrange: Movie with empty credits
        $movie = Movie::create([
            'title' => 'Test Movie Empty',
            'slug' => 'test-movie-empty-'.uniqid(),
            'release_year' => 2000,
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 99999,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 99999,
                'credits' => [
                    'cast' => [],
                    'crew' => [],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: No errors, no people created for this movie
        $this->assertEquals(0, $movie->fresh()->people->count());
        // Note: Person::count() may include persons from seeders, so we check only this movie
    }

    /**
     * Test edge case: Missing name in cast/crew data.
     * Should skip entries without name.
     */
    public function test_skips_cast_crew_entries_without_name(): void
    {
        // Arrange: Movie with incomplete cast data
        $movie = Movie::create([
            'title' => 'Test Movie Incomplete',
            'slug' => 'test-movie-incomplete-'.uniqid(),
            'release_year' => 2000,
        ]);

        $snapshot = TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 88888,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 88888,
                'credits' => [
                    'cast' => [
                        ['id' => 77777, 'name' => 'Test Actor Valid', 'character' => 'Character', 'order' => 0],
                        ['id' => 99999, 'name' => null, 'character' => 'Character', 'order' => 1], // Missing name
                        ['id' => 88888, 'character' => 'Another Character', 'order' => 2], // No name key
                    ],
                    'crew' => [
                        ['id' => 66666, 'name' => 'Test Director Valid', 'job' => 'Director'],
                        ['id' => 55555, 'job' => 'Writer'], // Missing name
                    ],
                ],
            ],
            'fetched_at' => now(),
        ]);

        // Act: Run SyncMovieMetadataJob
        $job = new \App\Jobs\SyncMovieMetadataJob($movie->id);
        $job->handle();

        // Assert: Only valid entries should be created
        $movie->refresh();
        $peopleCount = $movie->people->count();
        $this->assertGreaterThanOrEqual(2, $peopleCount, 'Should sync at least valid actor and director');
        $this->assertLessThanOrEqual(2, $peopleCount, 'Should sync only valid entries (actor + director)');

        $this->assertTrue($movie->people->contains(function ($person) {
            return $person->name === 'Test Actor Valid';
        }), 'Should sync valid actor');

        $this->assertTrue($movie->people->contains(function ($person) {
            return $person->name === 'Test Director Valid';
        }), 'Should sync valid director');

        // Assert: Invalid entries should not create persons
        $this->assertNull(Person::where('tmdb_id', 99999)->first(), 'Should not create person without name');
        $this->assertNull(Person::where('tmdb_id', 88888)->first(), 'Should not create person without name');
        $this->assertNull(Person::where('tmdb_id', 55555)->first(), 'Should not create crew without name');
    }
}
