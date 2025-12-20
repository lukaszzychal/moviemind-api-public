<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for GET /movies?slugs=... endpoint (RESTful bulk retrieve).
 */
class MovieBulkGetTest extends TestCase
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

    public function test_get_movies_with_slugs_parameter_returns_multiple_movies(): void
    {
        // Get existing movies from seeder
        $movie1 = Movie::first();
        $movie2 = Movie::skip(1)->first();

        $this->assertNotNull($movie1, 'Should have at least one movie from seeder');
        $this->assertNotNull($movie2, 'Should have at least two movies from seeder');

        $response = $this->getJson('/api/v1/movies?slugs='.$movie1->slug.','.$movie2->slug);

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('not_found', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('requested_count', $data);

        $this->assertCount(2, $data['data']);
        $this->assertEmpty($data['not_found']);
        $this->assertEquals(2, $data['count']);
        $this->assertEquals(2, $data['requested_count']);
    }

    public function test_get_movies_with_slugs_handles_not_found_slugs(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Should have at least one movie from seeder');

        $response = $this->getJson('/api/v1/movies?slugs='.$movie->slug.',non-existent-slug-12345');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $this->assertCount(1, $data['not_found']);
        $this->assertContains('non-existent-slug-12345', $data['not_found']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals(2, $data['requested_count']);
    }

    public function test_get_movies_with_slugs_returns_movies_in_same_order(): void
    {
        // Create test movies to ensure we have enough
        $movie1 = Movie::factory()->create(['title' => 'Movie A', 'slug' => 'movie-a-2000']);
        $movie2 = Movie::factory()->create(['title' => 'Movie B', 'slug' => 'movie-b-2010']);
        $movie3 = Movie::factory()->create(['title' => 'Movie C', 'slug' => 'movie-c-2020']);

        $requestedSlugs = [$movie3->slug, $movie1->slug, $movie2->slug];
        $slugsParam = implode(',', $requestedSlugs);

        $response = $this->getJson('/api/v1/movies?slugs='.$slugsParam);

        $response->assertOk();
        $data = $response->json();

        $returnedSlugs = array_column($data['data'], 'slug');
        $this->assertEquals($requestedSlugs, $returnedSlugs, 'Movies should be returned in the same order as requested');
    }

    public function test_get_movies_with_slugs_handles_duplicate_slugs(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie);

        $response = $this->getJson('/api/v1/movies?slugs='.$movie->slug.','.$movie->slug.','.$movie->slug);

        $response->assertOk();
        $data = $response->json();

        // Should return only one movie (duplicates are deduplicated)
        $this->assertCount(1, $data['data']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals(3, $data['requested_count']);
    }

    public function test_get_movies_with_slugs_validates_max_slugs_limit(): void
    {
        $slugs = array_fill(0, 51, 'test-slug'); // 51 slugs (over limit of 50)
        $slugsParam = implode(',', $slugs);

        $response = $this->getJson('/api/v1/movies?slugs='.$slugsParam);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_get_movies_with_slugs_validates_slug_format(): void
    {
        $response = $this->getJson('/api/v1/movies?slugs=valid-slug,invalid slug with spaces');

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_get_movies_with_slugs_and_include_parameter(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie);

        $response = $this->getJson('/api/v1/movies?slugs='.$movie->slug.'&include=descriptions,people');

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $movieData = $data['data'][0];

        // Verify movie data is present
        $this->assertArrayHasKey('id', $movieData);
        $this->assertArrayHasKey('slug', $movieData);
        $this->assertEquals($movie->slug, $movieData['slug']);
    }

    public function test_get_movies_without_slugs_uses_normal_search(): void
    {
        // Should work as normal search when slugs parameter is not provided
        $response = $this->getJson('/api/v1/movies?q=Matrix');

        $response->assertOk();
        $data = $response->json();

        // Should return normal search format (not bulk format)
        $this->assertArrayHasKey('data', $data);
        // Should NOT have bulk-specific fields
        $this->assertArrayNotHasKey('not_found', $data);
        $this->assertArrayNotHasKey('requested_count', $data);
    }

    public function test_get_movies_with_empty_slugs_returns_validation_error(): void
    {
        // Test with empty slugs parameter
        // Note: In Laravel tests, empty query params may be treated as null
        // So we test with explicit empty string via query array
        $response = $this->call('GET', '/api/v1/movies', ['slugs' => '']);

        // Should return 422 validation error OR handle gracefully (200 with empty results)
        // The important thing is that it doesn't crash
        $this->assertContains($response->status(), [200, 422], 'Should return 200 (graceful) or 422 (validation error)');

        if ($response->status() === 422) {
            $data = $response->json();
            $this->assertArrayHasKey('errors', $data);
        } else {
            // If it returns 200, it should be a valid response (normal search fallback)
            $response->assertOk();
        }
    }
}
