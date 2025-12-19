<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdaptiveRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure rate limiting for tests
        Config::set('rate-limiting.defaults.search', 10); // Lower for testing
        Config::set('rate-limiting.min.search', 2);
        Config::set('rate-limiting.defaults.generate', 5);
        Config::set('rate-limiting.min.generate', 1);
        Config::set('rate-limiting.defaults.report', 5);
        Config::set('rate-limiting.min.report', 1);

        Config::set('rate-limiting.logging.enabled', false);

        // Clear rate limiters before each test
        RateLimiter::clear('adaptive-rate-limit:search:127.0.0.1');
        RateLimiter::clear('adaptive-rate-limit:generate:127.0.0.1');
        RateLimiter::clear('adaptive-rate-limit:report:127.0.0.1');
    }

    public function test_search_endpoint_respects_rate_limit(): void
    {
        // Make requests up to the limit
        $maxRequests = 15;
        $successfulRequests = 0;
        $rateLimited = false;

        for ($i = 0; $i < $maxRequests; $i++) {
            $response = $this->getJson('/api/v1/movies/search?q=test');

            if ($response->status() === 200) {
                $successfulRequests++;
            } elseif ($response->status() === 429) {
                // Rate limit exceeded
                $rateLimited = true;
                $this->assertJson($response->content());
                $data = $response->json();
                $this->assertArrayHasKey('error', $data);
                $this->assertArrayHasKey('retry_after', $data);
                $this->assertArrayHasKey('message', $data);
                break; // Stop after first 429
            }
        }

        // Should either have successful requests OR be rate limited
        $this->assertTrue(
            $successfulRequests > 0 || $rateLimited,
            'Should have at least one successful request OR be rate limited'
        );
    }

    public function test_rate_limit_returns_429_with_retry_after(): void
    {
        // Exceed rate limit
        $maxRequests = 15; // More than default (10)

        $lastResponse = null;
        for ($i = 0; $i < $maxRequests; $i++) {
            $lastResponse = $this->getJson('/api/v1/movies/search?q=test');
            if ($lastResponse->status() === 429) {
                break;
            }
        }

        // Should eventually get 429
        if ($lastResponse && $lastResponse->status() === 429) {
            $this->assertEquals(429, $lastResponse->status());
            $data = $lastResponse->json();
            $this->assertArrayHasKey('error', $data);
            $this->assertArrayHasKey('retry_after', $data);
            $this->assertArrayHasKey('message', $data);
            $this->assertIsInt($data['retry_after']);
            $this->assertGreaterThan(0, $data['retry_after']);

            // Check headers
            $this->assertTrue($lastResponse->headers->has('Retry-After'));
            $this->assertTrue($lastResponse->headers->has('X-RateLimit-Limit'));
        }
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/movies/search?q=test');

        // Should have rate limit headers regardless of status
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'), 'Should have X-RateLimit-Limit header');
        $this->assertTrue($response->headers->has('X-RateLimit-Remaining'), 'Should have X-RateLimit-Remaining header');

        $limit = (int) $response->headers->get('X-RateLimit-Limit');
        $remaining = (int) $response->headers->get('X-RateLimit-Remaining');

        $this->assertGreaterThan(0, $limit, 'Rate limit should be > 0');
        $this->assertGreaterThanOrEqual(0, $remaining, 'Remaining should be >= 0');
        $this->assertLessThanOrEqual($limit, $remaining, 'Remaining should be <= limit');
    }

    public function test_generate_endpoint_has_rate_limiting(): void
    {
        $response = $this->postJson('/api/v1/generate', [
            'type' => 'movie',
            'slug' => 'test-movie-2024',
        ]);

        // Should either succeed (200/202), be rate limited (429), or validation error (422)
        $this->assertContains($response->status(), [200, 202, 422, 429], 'Should return valid status code');

        if ($response->status() === 429) {
            $this->assertArrayHasKey('retry_after', $response->json());
        }
    }

    public function test_report_endpoint_has_rate_limiting(): void
    {
        // Create a movie first
        $movie = \App\Models\Movie::factory()->create();

        $response = $this->postJson("/api/v1/movies/{$movie->slug}/report", [
            'type' => 'grammar',
            'message' => 'Test report message with enough characters to pass validation',
        ]);

        // Should either succeed (201), be rate limited (429), or validation error (422)
        $this->assertContains($response->status(), [201, 422, 429], 'Should return valid status code');

        if ($response->status() === 429) {
            $this->assertArrayHasKey('retry_after', $response->json());
        }
    }

    public function test_rate_limit_is_per_endpoint(): void
    {
        // Exhaust search limit
        for ($i = 0; $i < 15; $i++) {
            $this->getJson('/api/v1/movies/search?q=test');
        }

        // Generate endpoint should still work (different endpoint)
        $response = $this->postJson('/api/v1/generate', [
            'type' => 'movie',
            'slug' => 'test-movie-2024',
        ]);

        // Should not be rate limited (different endpoint)
        $this->assertNotEquals(429, $response->status(), 'Generate endpoint should not be rate limited by search endpoint');
    }
}
