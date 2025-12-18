<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\EntityVerificationServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for TMDB health check endpoint.
 *
 * @author MovieMind API Team
 */
class TmdbHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /**
     * Scenario: Health check reports success when TMDB API is accessible
     * Given: TMDB API key is configured
     * When: A GET request is sent to /api/v1/health/tmdb
     * Then: Response indicates TMDB API is reachable
     */
    public function test_health_check_reports_success(): void
    {
        // Given: TMDB API key is configured and service returns success
        $mockService = $this->createMock(EntityVerificationServiceInterface::class);
        $mockService->expects($this->once())
            ->method('health')
            ->willReturn([
                'success' => true,
                'service' => 'tmdb',
                'message' => 'TMDb API is accessible',
                'status' => 200,
            ]);

        $this->app->instance(EntityVerificationServiceInterface::class, $mockService);

        // When: A GET request is sent
        $response = $this->getJson('/api/v1/health/tmdb');

        // Then: Response indicates TMDB API is reachable
        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'service' => 'tmdb',
                'message' => 'TMDb API is accessible',
            ])
            ->assertJsonStructure([
                'success',
                'service',
                'message',
                'status',
            ]);
    }

    /**
     * Scenario: Health check reports error when TMDB API key is not configured
     * Given: TMDB API key is not set
     * When: A GET request is sent to /api/v1/health/tmdb
     * Then: Response indicates API key is missing
     */
    public function test_health_check_reports_error_without_key(): void
    {
        // Given: TMDB API key is not configured
        $mockService = $this->createMock(EntityVerificationServiceInterface::class);
        $mockService->expects($this->once())
            ->method('health')
            ->willReturn([
                'success' => false,
                'service' => 'tmdb',
                'error' => 'TMDb API key not configured. Set TMDB_API_KEY in .env',
            ]);

        $this->app->instance(EntityVerificationServiceInterface::class, $mockService);

        // When: A GET request is sent
        $response = $this->getJson('/api/v1/health/tmdb');

        // Then: Response indicates API key is missing
        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'service' => 'tmdb',
                'error' => 'TMDb API key not configured. Set TMDB_API_KEY in .env',
            ]);
    }

    /**
     * Scenario: Health check reports error when TMDB API is unreachable
     * Given: TMDB API key is configured but API returns error
     * When: A GET request is sent to /api/v1/health/tmdb
     * Then: Response indicates API is unreachable
     */
    public function test_health_check_reports_error_when_api_unreachable(): void
    {
        // Given: TMDB API key is configured but API is unreachable
        $mockService = $this->createMock(EntityVerificationServiceInterface::class);
        $mockService->expects($this->once())
            ->method('health')
            ->willReturn([
                'success' => false,
                'service' => 'tmdb',
                'error' => 'TMDb API is not reachable: Connection timeout',
                'status' => 503,
            ]);

        $this->app->instance(EntityVerificationServiceInterface::class, $mockService);

        // When: A GET request is sent
        $response = $this->getJson('/api/v1/health/tmdb');

        // Then: Response indicates API is unreachable
        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'service' => 'tmdb',
            ])
            ->assertJsonStructure([
                'success',
                'service',
                'error',
                'status',
            ]);
    }
}
