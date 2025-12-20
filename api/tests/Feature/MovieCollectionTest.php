<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use App\Models\TmdbSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for movie collection endpoint.
 *
 * @author MovieMind API Team
 */
class MovieCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
        config(['logging.default' => 'stack']);
        config(['rate-limiting.logging.enabled' => false]);
    }

    /**
     * Test: Get collection for a movie with TMDb snapshot containing belongs_to_collection.
     */
    public function test_get_collection_returns_collection_with_movies(): void
    {
        // Given: Movies in the same collection (use unique slugs to avoid conflicts with seeder)
        $uniqueSuffix = time();
        $movie1 = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
            'tmdb_id' => 603,
        ]);

        $movie2 = Movie::factory()->create([
            'title' => 'The Matrix Reloaded',
            'slug' => 'the-matrix-reloaded-2003-'.$uniqueSuffix,
            'release_year' => 2003,
            'tmdb_id' => 604,
        ]);

        $movie3 = Movie::factory()->create([
            'title' => 'The Matrix Revolutions',
            'slug' => 'the-matrix-revolutions-2003-'.$uniqueSuffix,
            'release_year' => 2003,
            'tmdb_id' => 605,
        ]);

        // Create TMDb snapshots with belongs_to_collection
        $collectionId = 234;
        $collectionName = 'The Matrix Collection';

        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie1->id,
            'tmdb_id' => 603,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 603,
                'title' => 'The Matrix',
                'belongs_to_collection' => [
                    'id' => $collectionId,
                    'name' => $collectionName,
                ],
            ],
            'fetched_at' => now(),
        ]);

        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie2->id,
            'tmdb_id' => 604,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 604,
                'title' => 'The Matrix Reloaded',
                'belongs_to_collection' => [
                    'id' => $collectionId,
                    'name' => $collectionName,
                ],
            ],
            'fetched_at' => now(),
        ]);

        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie3->id,
            'tmdb_id' => 605,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 605,
                'title' => 'The Matrix Revolutions',
                'belongs_to_collection' => [
                    'id' => $collectionId,
                    'name' => $collectionName,
                ],
            ],
            'fetched_at' => now(),
        ]);

        // When: Request collection for movie1
        $response = $this->getJson('/api/v1/movies/'.$movie1->slug.'/collection');

        // Then: Should return collection with all movies
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('collection', $data);
        $this->assertArrayHasKey('movies', $data);

        $this->assertEquals($collectionName, $data['collection']['name']);
        $this->assertEquals($collectionId, $data['collection']['tmdb_collection_id']);
        $this->assertEquals(3, $data['collection']['count']);

        $this->assertCount(3, $data['movies']);
        $this->assertArrayHasKey('slug', $data['movies'][0]);
        $this->assertArrayHasKey('title', $data['movies'][0]);
    }

    /**
     * Test: Get collection for a movie without belongs_to_collection returns 404.
     */
    public function test_get_collection_returns_404_when_movie_has_no_collection(): void
    {
        // Given: A movie without collection
        $movie = Movie::factory()->create([
            'title' => 'Standalone Movie',
            'slug' => 'standalone-movie-2020',
            'release_year' => 2020,
        ]);

        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 12345,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 12345,
                'title' => 'Standalone Movie',
                // No belongs_to_collection
            ],
            'fetched_at' => now(),
        ]);

        // When: Request collection
        $response = $this->getJson('/api/v1/movies/'.$movie->slug.'/collection');

        // Then: Should return 404
        $response->assertNotFound();
    }

    /**
     * Test: Get collection for a movie without TMDb snapshot returns 404.
     */
    public function test_get_collection_returns_404_when_no_snapshot(): void
    {
        // Given: A movie without TMDb snapshot
        $movie = Movie::factory()->create([
            'title' => 'Movie Without Snapshot',
            'slug' => 'movie-without-snapshot-2020',
            'release_year' => 2020,
        ]);

        // When: Request collection
        $response = $this->getJson('/api/v1/movies/'.$movie->slug.'/collection');

        // Then: Should return 404
        $response->assertNotFound();
    }

    /**
     * Test: Get collection for a non-existent movie returns 404.
     */
    public function test_get_collection_returns_404_for_non_existent_movie(): void
    {
        // When: Request collection for non-existent movie
        $response = $this->getJson('/api/v1/movies/non-existent-movie-2020/collection');

        // Then: Should return 404
        $response->assertNotFound();
    }

    /**
     * Test: Collection response includes HATEOAS links.
     */
    public function test_collection_response_includes_hateoas_links(): void
    {
        // Given: A movie with collection (use unique slug to avoid conflicts with seeder)
        $uniqueSuffix = time();
        $movie = Movie::factory()->create([
            'title' => 'The Matrix',
            'slug' => 'the-matrix-1999-'.$uniqueSuffix,
            'release_year' => 1999,
            'tmdb_id' => 603,
        ]);

        $collectionId = 234;
        TmdbSnapshot::create([
            'entity_type' => 'MOVIE',
            'entity_id' => $movie->id,
            'tmdb_id' => 603,
            'tmdb_type' => 'movie',
            'raw_data' => [
                'id' => 603,
                'title' => 'The Matrix',
                'belongs_to_collection' => [
                    'id' => $collectionId,
                    'name' => 'The Matrix Collection',
                ],
            ],
            'fetched_at' => now(),
        ]);

        // When: Request collection
        $response = $this->getJson('/api/v1/movies/'.$movie->slug.'/collection');

        // Then: Should include _links
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('_links', $data);
        $this->assertArrayHasKey('self', $data['_links']);
    }
}
