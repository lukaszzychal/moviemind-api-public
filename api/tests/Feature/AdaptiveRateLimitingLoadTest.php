<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Load test for adaptive rate limiting under concurrent requests.
 */
class AdaptiveRateLimitingLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');

        // Configure low limits for testing
        Config::set('rate-limiting.defaults.search', 5); // 5 req/min
        Config::set('rate-limiting.min.search', 2);
        Config::set('rate-limiting.defaults.generate', 3);
        Config::set('rate-limiting.min.generate', 1);
        Config::set('rate-limiting.defaults.report', 3);
        Config::set('rate-limiting.min.report', 1);

        Config::set('rate-limiting.logging.enabled', false);
        config(['logging.default' => 'stack']); // Ensure logging is configured
        config(['services.tmdb.api_key' => 'test-api-key']);

        // Setup fake EntityVerificationService for search endpoint
        // Use query that matches seeded movies (Matrix) to ensure 200 response
        $this->fakeEntityVerificationService();

        // Clear rate limiters before each test
        RateLimiter::clear('adaptive-rate-limit:search:127.0.0.1');
        RateLimiter::clear('adaptive-rate-limit:generate:127.0.0.1');
        RateLimiter::clear('adaptive-rate-limit:report:127.0.0.1');
    }

    public function test_rate_limiting_handles_concurrent_requests_correctly(): void
    {
        $concurrentRequests = 10; // More than limit (5)
        $expectedLimit = 5;

        $successCount = 0;
        $rateLimitedCount = 0;
        $responses = [];

        // Simulate concurrent requests (use query that matches seeded movies)
        for ($i = 0; $i < $concurrentRequests; $i++) {
            $response = $this->getJson('/api/v1/movies/search?q=Matrix');
            $responses[] = $response;

            if ($response->status() === 200) {
                $successCount++;
            } elseif ($response->status() === 429) {
                $rateLimitedCount++;
                $response->assertJsonStructure([
                    'error',
                    'message',
                    'retry_after',
                ]);
            }
        }

        // Should have some successful requests (up to limit)
        $this->assertGreaterThan(0, $successCount, 'Should have some successful requests');
        $this->assertLessThanOrEqual($expectedLimit, $successCount, "Should not exceed limit of {$expectedLimit}");

        // Should have some rate-limited requests
        $this->assertGreaterThan(0, $rateLimitedCount, 'Should have rate-limited requests when limit exceeded');
    }

    public function test_rate_limiting_resets_after_period(): void
    {
        $limit = 5;

        // Exhaust the limit (use query that matches seeded movies)
        for ($i = 0; $i < $limit + 2; $i++) {
            $response = $this->getJson('/api/v1/movies/search?q=Matrix');
            if ($response->status() === 429) {
                break;
            }
        }

        // Verify we hit the limit
        $rateLimitedResponse = $this->getJson('/api/v1/movies/search?q=Matrix');
        $rateLimitedResponse->assertStatus(429);

        // Clear rate limiter (simulating time passing)
        RateLimiter::clear('adaptive-rate-limit:search:127.0.0.1');

        // After reset, should work again
        $newResponse = $this->getJson('/api/v1/movies/search?q=Matrix');
        $newResponse->assertOk(); // Should succeed after reset
    }

    public function test_rate_limiting_is_per_endpoint(): void
    {
        $searchLimit = 5;
        $generateLimit = 3;

        // Exhaust search limit (use query that matches seeded movies)
        for ($i = 0; $i < $searchLimit + 2; $i++) {
            $response = $this->getJson('/api/v1/movies/search?q=Matrix');
            if ($response->status() === 429) {
                break;
            }
        }

        // Verify search is rate limited
        $searchResponse = $this->getJson('/api/v1/movies/search?q=Matrix');
        $searchResponse->assertStatus(429);

        // Generate endpoint should still work (different endpoint)
        $generateResponse = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'slug' => 'test-movie-2024',
        ]);

        // Should either succeed (200/202), be validation error (422), or feature disabled (403)
        // but NOT rate limited (429) because it's a different endpoint
        $this->assertNotEquals(429, $generateResponse->status(), 'Generate endpoint should not be rate limited by search endpoint');
        $this->assertContains(
            $generateResponse->status(),
            [200, 202, 422, 403],
            'Generate endpoint should return valid status code'
        );
    }

    public function test_rate_limiting_headers_are_present_under_load(): void
    {
        $requestsToMake = 7; // More than limit

        $lastSuccessfulResponse = null;
        $rateLimitedResponse = null;

        for ($i = 0; $i < $requestsToMake; $i++) {
            $response = $this->getJson('/api/v1/movies/search?q=Matrix');

            if ($response->status() === 200) {
                $lastSuccessfulResponse = $response;
            } elseif ($response->status() === 429) {
                $rateLimitedResponse = $response;
                break;
            }
        }

        // Successful response should have rate limit headers
        if ($lastSuccessfulResponse) {
            $this->assertTrue(
                $lastSuccessfulResponse->headers->has('X-RateLimit-Limit'),
                'Successful response should have X-RateLimit-Limit header'
            );
            $this->assertTrue(
                $lastSuccessfulResponse->headers->has('X-RateLimit-Remaining'),
                'Successful response should have X-RateLimit-Remaining header'
            );
        }

        // Rate-limited response should have retry-after header
        if ($rateLimitedResponse) {
            $this->assertTrue(
                $rateLimitedResponse->headers->has('Retry-After'),
                'Rate-limited response should have Retry-After header'
            );
            $this->assertTrue(
                $rateLimitedResponse->headers->has('X-RateLimit-Limit'),
                'Rate-limited response should have X-RateLimit-Limit header'
            );
        }
    }

    public function test_rate_limiting_works_consistently_across_multiple_batches(): void
    {
        $limit = 5;
        $batches = 3;
        $requestsPerBatch = $limit + 2;

        $totalSuccessCount = 0;
        $totalRateLimitedCount = 0;

        for ($batch = 0; $batch < $batches; $batch++) {
            // Clear rate limiter between batches (simulating time passing)
            if ($batch > 0) {
                RateLimiter::clear('adaptive-rate-limit:search:127.0.0.1');
            }

            $batchSuccessCount = 0;
            $batchRateLimitedCount = 0;

            for ($i = 0; $i < $requestsPerBatch; $i++) {
                $response = $this->getJson('/api/v1/movies/search?q=Matrix');

                if ($response->status() === 200) {
                    $batchSuccessCount++;
                    $totalSuccessCount++;
                } elseif ($response->status() === 429) {
                    $batchRateLimitedCount++;
                    $totalRateLimitedCount++;
                    break; // Stop after hitting limit in this batch
                }
            }

            // Each batch should respect the limit
            $this->assertLessThanOrEqual(
                $limit,
                $batchSuccessCount,
                "Batch {$batch} should not exceed limit of {$limit}"
            );

            if ($batchSuccessCount >= $limit) {
                $this->assertGreaterThan(
                    0,
                    $batchRateLimitedCount,
                    "Batch {$batch} should have rate-limited requests when limit exceeded"
                );
            }
        }
    }
}
