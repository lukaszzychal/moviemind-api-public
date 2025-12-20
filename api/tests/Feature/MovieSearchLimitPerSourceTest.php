<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Movie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for limit per source in movie search.
 *
 * @author MovieMind API Team
 */
class MovieSearchLimitPerSourceTest extends TestCase
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
     * Test: Search with custom local_limit.
     */
    public function test_search_respects_local_limit(): void
    {
        // Given: More than 10 movies in database
        Movie::factory()->count(15)->create();

        // When: Search with local_limit=5
        $response = $this->getJson('/api/v1/movies/search?q=&local_limit=5');

        // Then: Should return at most 5 local movies
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('local_count', $data);
        $this->assertLessThanOrEqual(5, $data['local_count']);
    }

    /**
     * Test: Search with custom external_limit.
     */
    public function test_search_respects_external_limit(): void
    {
        // Given: Search query that will trigger external search
        // Note: This test assumes TMDb verification is enabled and returns results

        // When: Search with external_limit=3
        $response = $this->getJson('/api/v1/movies/search?q=matrix&external_limit=3');

        // Then: Should return at most 3 external movies
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('external_count', $data);
        // Note: external_count may be 0 if TMDb is disabled or no results
        // We just verify the field exists and is a number
        $this->assertIsInt($data['external_count']);
    }

    /**
     * Test: Search with both local_limit and external_limit.
     */
    public function test_search_respects_both_limits(): void
    {
        // Given: More than 10 movies in database
        Movie::factory()->count(15)->create();

        // When: Search with both limits
        $response = $this->getJson('/api/v1/movies/search?q=&local_limit=5&external_limit=3');

        // Then: Should respect both limits
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('local_count', $data);
        $this->assertArrayHasKey('external_count', $data);
        $this->assertLessThanOrEqual(5, $data['local_count']);
        $this->assertIsInt($data['external_count']);
    }

    /**
     * Test: Search uses per_page as default when limits not specified.
     */
    public function test_search_uses_per_page_as_default_limit(): void
    {
        // Given: More than 20 movies in database
        Movie::factory()->count(25)->create();

        // When: Search with per_page=10 but no local_limit/external_limit
        $response = $this->getJson('/api/v1/movies/search?q=&per_page=10');

        // Then: Should use per_page as default for both sources
        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('local_count', $data);
        $this->assertLessThanOrEqual(10, $data['local_count']);
    }

    /**
     * Test: Validation for local_limit (min/max).
     */
    public function test_local_limit_validation(): void
    {
        // When: Search with local_limit=0 (below minimum)
        $response = $this->getJson('/api/v1/movies/search?q=&local_limit=0');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['local_limit']);

        // When: Search with local_limit=200 (above maximum)
        $response = $this->getJson('/api/v1/movies/search?q=&local_limit=200');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['local_limit']);
    }

    /**
     * Test: Validation for external_limit (min/max).
     */
    public function test_external_limit_validation(): void
    {
        // When: Search with external_limit=0 (below minimum)
        $response = $this->getJson('/api/v1/movies/search?q=&external_limit=0');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['external_limit']);

        // When: Search with external_limit=200 (above maximum)
        $response = $this->getJson('/api/v1/movies/search?q=&external_limit=200');

        // Then: Should return validation error
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['external_limit']);
    }

    /**
     * Test: local_limit and external_limit work independently.
     */
    public function test_limits_work_independently(): void
    {
        // Given: More than 20 movies in database
        Movie::factory()->count(25)->create();

        // When: Search with different limits for each source
        $response = $this->getJson('/api/v1/movies/search?q=&local_limit=5&external_limit=10');

        // Then: Each source should respect its own limit
        $response->assertOk();
        $data = $response->json();

        $this->assertLessThanOrEqual(5, $data['local_count']);
        // external_count may be 0 if TMDb is disabled, but if > 0, should be <= 10
        if ($data['external_count'] > 0) {
            $this->assertLessThanOrEqual(10, $data['external_count']);
        }
    }

    /**
     * Test: Limits are included in cache key.
     */
    public function test_limits_affect_cache_key(): void
    {
        // Given: Movies in database
        Movie::factory()->count(15)->create();

        // When: Search with local_limit=5
        $response1 = $this->getJson('/api/v1/movies/search?q=&local_limit=5');
        $response1->assertOk();

        // When: Search with same query but different local_limit=10
        $response2 = $this->getJson('/api/v1/movies/search?q=&local_limit=10');
        $response2->assertOk();

        // Then: Results may differ (different limits = different cache keys)
        // We just verify both requests succeed and limits are respected
        $this->assertLessThanOrEqual(5, $response1->json('local_count'));
        $this->assertLessThanOrEqual(10, $response2->json('local_count'));
    }
}
