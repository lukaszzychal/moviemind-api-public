<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MovieBulkTest extends TestCase
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

    public function test_bulk_endpoint_returns_multiple_movies(): void
    {
        // Get existing movies from seeder
        $movie1 = Movie::first();
        $movie2 = Movie::skip(1)->first();

        $this->assertNotNull($movie1, 'Should have at least one movie from seeder');
        $this->assertNotNull($movie2, 'Should have at least two movies from seeder');

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => [$movie1->slug, $movie2->slug],
        ]);

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

    public function test_bulk_endpoint_handles_not_found_slugs(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie, 'Should have at least one movie from seeder');

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => [$movie->slug, 'non-existent-slug-12345'],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $this->assertCount(1, $data['not_found']);
        $this->assertContains('non-existent-slug-12345', $data['not_found']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals(2, $data['requested_count']);
    }

    public function test_bulk_endpoint_validates_slugs_required(): void
    {
        $response = $this->postJson('/api/v1/movies/bulk', []);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('slugs', $data['errors']);
    }

    public function test_bulk_endpoint_validates_slugs_is_array(): void
    {
        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => 'not-an-array',
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_bulk_endpoint_validates_max_slugs_limit(): void
    {
        $slugs = array_fill(0, 51, 'test-slug'); // 51 slugs (over limit of 50)

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => $slugs,
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_bulk_endpoint_validates_min_slugs(): void
    {
        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => [],
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_bulk_endpoint_validates_slug_format(): void
    {
        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => ['valid-slug', 'invalid slug with spaces'],
        ]);

        $response->assertStatus(422);
        $data = $response->json();
        $this->assertArrayHasKey('errors', $data);
    }

    public function test_bulk_endpoint_returns_movies_in_same_order_as_requested(): void
    {
        // Create test movies to ensure we have enough
        $movie1 = Movie::factory()->create(['title' => 'Movie A', 'slug' => 'movie-a-2000']);
        $movie2 = Movie::factory()->create(['title' => 'Movie B', 'slug' => 'movie-b-2010']);
        $movie3 = Movie::factory()->create(['title' => 'Movie C', 'slug' => 'movie-c-2020']);

        $requestedSlugs = [$movie3->slug, $movie1->slug, $movie2->slug];

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => $requestedSlugs,
        ]);

        $response->assertOk();
        $data = $response->json();

        $returnedSlugs = array_column($data['data'], 'slug');
        $this->assertEquals($requestedSlugs, $returnedSlugs, 'Movies should be returned in the same order as requested');
    }

    public function test_bulk_endpoint_handles_duplicate_slugs(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie);

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => [$movie->slug, $movie->slug, $movie->slug],
        ]);

        $response->assertOk();
        $data = $response->json();

        // Should return only one movie (duplicates are deduplicated)
        $this->assertCount(1, $data['data']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals(3, $data['requested_count']);
    }

    public function test_bulk_endpoint_includes_movie_data(): void
    {
        $movie = Movie::first();
        $this->assertNotNull($movie);

        $response = $this->postJson('/api/v1/movies/bulk', [
            'slugs' => [$movie->slug],
        ]);

        $response->assertOk();
        $data = $response->json();

        $this->assertCount(1, $data['data']);
        $movieData = $data['data'][0];

        $this->assertArrayHasKey('id', $movieData);
        $this->assertArrayHasKey('slug', $movieData);
        $this->assertArrayHasKey('title', $movieData);
        $this->assertArrayHasKey('release_year', $movieData);
        $this->assertEquals($movie->slug, $movieData['slug']);
        $this->assertEquals($movie->title, $movieData['title']);
    }
}
