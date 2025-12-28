<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ApiUsage;
use App\Models\SubscriptionPlan;
use App\Services\ApiKeyService;
use App\Services\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageTrackerTest extends TestCase
{
    use RefreshDatabase;

    private UsageTracker $service;

    private ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new UsageTracker;
        $this->apiKeyService = new ApiKeyService;
    }

    public function test_track_request_creates_usage_record(): void
    {
        // ARRANGE: Create API key for tracking
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];

        // ACT: Track a request
        $this->service->trackRequest($apiKey, '/api/v1/movies', method: 'GET', responseStatus: 200);

        // ASSERT: Verify usage record was created
        $this->assertDatabaseHas('api_usage', [
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/movies',
            'method' => 'GET',
            'response_status' => 200,
        ]);
    }

    public function test_get_monthly_usage_returns_correct_count(): void
    {
        // ARRANGE: Create API key and track multiple requests
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];
        $month = now()->format('Y-m');
        $this->service->trackRequest($apiKey, '/api/v1/movies');
        $this->service->trackRequest($apiKey, '/api/v1/movies');
        $this->service->trackRequest($apiKey, '/api/v1/people');

        // ACT: Get monthly usage count
        $usage = $this->service->getMonthlyUsage($apiKey, $month);

        // ASSERT: Verify correct count is returned
        $this->assertEquals(3, $usage);
    }

    public function test_get_remaining_quota_returns_correct_value(): void
    {
        // ARRANGE: Create plan with limit and API key, then use some requests
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 100,
        ]);
        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];
        $this->trackMultipleRequests($apiKey, 30);

        // ACT: Get remaining quota
        $remaining = $this->service->getRemainingQuota($apiKey, $plan);

        // ASSERT: Verify correct remaining quota
        $this->assertEquals(70, $remaining);
    }

    public function test_get_remaining_quota_returns_null_for_unlimited_plan(): void
    {
        // ARRANGE: Create unlimited plan and API key
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 0, // Unlimited
        ]);
        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];

        // ACT: Get remaining quota
        $remaining = $this->service->getRemainingQuota($apiKey, $plan);

        // ASSERT: Verify null is returned for unlimited plan
        $this->assertNull($remaining);
    }

    public function test_has_exceeded_monthly_limit_returns_true_when_exceeded(): void
    {
        // ARRANGE: Create plan with limit, API key, and use all requests
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 10,
        ]);
        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];
        $this->trackMultipleRequests($apiKey, 10);

        // ACT: Check if limit is exceeded
        $exceeded = $this->service->hasExceededMonthlyLimit($apiKey, $plan);

        // ASSERT: Verify limit is exceeded
        $this->assertTrue($exceeded);
    }

    public function test_has_exceeded_rate_limit_returns_true_when_exceeded(): void
    {
        // ARRANGE: Create plan with rate limit, API key, and simulate 5 requests in last minute
        $plan = SubscriptionPlan::factory()->create([
            'rate_limit_per_minute' => 5,
        ]);
        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];
        $this->createMultipleUsageRecords($apiKey, $plan, 5);

        // ACT: Check if rate limit is exceeded
        $exceeded = $this->service->hasExceededRateLimit($apiKey, $plan);

        // ASSERT: Verify rate limit is exceeded
        $this->assertTrue($exceeded);
    }

    public function test_get_usage_in_time_window_returns_correct_count(): void
    {
        // ARRANGE: Create API key and track 3 requests
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];
        $this->trackMultipleRequests($apiKey, 3);

        // ACT: Get usage count for 60-second window
        $usage = $this->service->getUsageInTimeWindow($apiKey, 60);

        // ASSERT: Verify correct usage count
        $this->assertEquals(3, $usage);
    }

    // Helper methods for test data setup

    /**
     * Track multiple requests for an API key.
     */
    private function trackMultipleRequests($apiKey, int $count, string $endpoint = '/api/v1/movies'): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->service->trackRequest($apiKey, $endpoint);
        }
    }

    /**
     * Create multiple usage records directly in database (for rate limit testing).
     */
    private function createMultipleUsageRecords($apiKey, $plan, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            ApiUsage::create([
                'api_key_id' => $apiKey->id,
                'plan_id' => $plan->id,
                'endpoint' => '/api/v1/movies',
                'method' => 'GET',
                'response_status' => 200,
                'month' => now()->format('Y-m'),
                'created_at' => now(),
            ]);
        }
    }
}
