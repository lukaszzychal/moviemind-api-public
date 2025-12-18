<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for RequestIdMiddleware.
 *
 * Tests that request-id and correlation-id are:
 * - Generated automatically
 * - Added to response headers
 * - Added to log context
 * - Can be provided by client (correlation-id)
 *
 * @author MovieMind API Team
 */
class RequestIdMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed');
    }

    /**
     * Scenario: Request-ID is automatically generated and added to response
     *
     * Given: API request without X-Request-ID header
     * When: Request is processed
     * Then: Response should include X-Request-ID header with valid UUIDv4
     */
    public function test_request_id_is_generated_automatically(): void
    {
        // Given: API request without X-Request-ID header
        // When: Request is processed
        $response = $this->getJson('/api/v1/movies');

        // Then: Response should include X-Request-ID header with valid UUIDv4
        $response->assertHeader('X-Request-ID');

        $requestId = $response->headers->get('X-Request-ID');
        $this->assertNotNull($requestId);
        $this->assertTrue(Str::isUuid($requestId), 'Request-ID should be valid UUID');
    }

    /**
     * Scenario: Correlation-ID is automatically generated if not provided
     *
     * Given: API request without X-Correlation-ID header
     * When: Request is processed
     * Then: Response should include X-Correlation-ID header with valid UUIDv4
     */
    public function test_correlation_id_is_generated_automatically(): void
    {
        // Given: API request without X-Correlation-ID header
        // When: Request is processed
        $response = $this->getJson('/api/v1/movies');

        // Then: Response should include X-Correlation-ID header with valid UUIDv4
        $response->assertHeader('X-Correlation-ID');

        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotNull($correlationId);
        $this->assertTrue(Str::isUuid($correlationId), 'Correlation-ID should be valid UUID');
    }

    /**
     * Scenario: Client-provided correlation-id is used
     *
     * Given: API request with X-Correlation-ID header
     * When: Request is processed
     * Then: Response should use the same correlation-id from header
     */
    public function test_correlation_id_from_client_is_used(): void
    {
        // Given: API request with X-Correlation-ID header
        $clientCorrelationId = (string) Str::uuid();

        // When: Request is processed
        $response = $this->getJson('/api/v1/movies', [
            'X-Correlation-ID' => $clientCorrelationId,
        ]);

        // Then: Response should use the same correlation-id from header
        $response->assertHeader('X-Correlation-ID', $clientCorrelationId);
    }

    /**
     * Scenario: Invalid correlation-id from client is rejected
     *
     * Given: API request with invalid X-Correlation-ID header (not UUID)
     * When: Request is processed
     * Then: Response should generate new correlation-id (ignore invalid one)
     */
    public function test_invalid_correlation_id_is_rejected(): void
    {
        // Given: API request with invalid X-Correlation-ID header (not UUID)
        $invalidCorrelationId = 'not-a-valid-uuid';

        // When: Request is processed
        $response = $this->getJson('/api/v1/movies', [
            'X-Correlation-ID' => $invalidCorrelationId,
        ]);

        // Then: Response should generate new correlation-id (ignore invalid one)
        $correlationId = $response->headers->get('X-Correlation-ID');
        $this->assertNotEquals($invalidCorrelationId, $correlationId);
        $this->assertTrue(Str::isUuid($correlationId), 'Should generate valid UUID when client provides invalid one');
    }

    /**
     * Scenario: Request-ID and Correlation-ID are different for each request
     *
     * Given: Multiple API requests
     * When: Requests are processed
     * Then: Each request should have unique request-id, correlation-id can be same if provided
     */
    public function test_each_request_has_unique_request_id(): void
    {
        // Given: Multiple API requests
        // When: Requests are processed
        $response1 = $this->getJson('/api/v1/movies');
        $response2 = $this->getJson('/api/v1/movies');

        // Then: Each request should have unique request-id
        $requestId1 = $response1->headers->get('X-Request-ID');
        $requestId2 = $response2->headers->get('X-Request-ID');

        $this->assertNotEquals($requestId1, $requestId2, 'Each request should have unique request-id');
    }

    /**
     * Scenario: Correlation-ID is maintained across related requests
     *
     * Given: Multiple API requests with same X-Correlation-ID header
     * When: Requests are processed
     * Then: All responses should have the same correlation-id
     */
    public function test_correlation_id_is_maintained_across_requests(): void
    {
        // Given: Multiple API requests with same X-Correlation-ID header
        $correlationId = (string) Str::uuid();

        // When: Requests are processed
        $response1 = $this->getJson('/api/v1/movies', [
            'X-Correlation-ID' => $correlationId,
        ]);
        $response2 = $this->getJson('/api/v1/movies', [
            'X-Correlation-ID' => $correlationId,
        ]);

        // Then: All responses should have the same correlation-id
        $this->assertEquals($correlationId, $response1->headers->get('X-Correlation-ID'));
        $this->assertEquals($correlationId, $response2->headers->get('X-Correlation-ID'));

        // But request-ids should be different
        $this->assertNotEquals(
            $response1->headers->get('X-Request-ID'),
            $response2->headers->get('X-Request-ID'),
            'Request-IDs should be different even with same correlation-id'
        );
    }

    /**
     * Scenario: Request-ID and Correlation-ID are present in all API endpoints
     *
     * Given: Various API endpoints
     * When: Requests are processed
     * Then: All responses should include both headers
     */
    public function test_headers_present_in_all_endpoints(): void
    {
        // Given: Various API endpoints
        $endpoints = [
            '/api/v1/movies',
            '/api/v1/movies/search?q=matrix',
            '/api/v1/health/openai',
            '/api/v1/health/tmdb',
        ];

        // When: Requests are processed
        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);

            // Then: All responses should include both headers
            $response->assertHeader('X-Request-ID');
            $response->assertHeader('X-Correlation-ID');

            $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
            $this->assertTrue(Str::isUuid($response->headers->get('X-Correlation-ID')));
        }
    }

    /**
     * Scenario: Client-provided request-id is used if valid UUID
     *
     * Given: API request with valid X-Request-ID header
     * When: Request is processed
     * Then: Response should use the same request-id from header
     */
    public function test_client_provided_request_id_is_used_if_valid(): void
    {
        // Given: API request with valid X-Request-ID header
        $clientRequestId = (string) Str::uuid();

        // When: Request is processed
        $response = $this->getJson('/api/v1/movies', [
            'X-Request-ID' => $clientRequestId,
        ]);

        // Then: Response should use the same request-id from header
        $response->assertHeader('X-Request-ID', $clientRequestId);
    }
}
