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
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];

        $this->service->trackRequest($apiKey, '/api/v1/movies', method: 'GET', responseStatus: 200);

        $this->assertDatabaseHas('api_usage', [
            'api_key_id' => $apiKey->id,
            'endpoint' => '/api/v1/movies',
            'method' => 'GET',
            'response_status' => 200,
        ]);
    }

    public function test_get_monthly_usage_returns_correct_count(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];
        $month = now()->format('Y-m');

        // Create 3 usage records
        $this->service->trackRequest($apiKey, '/api/v1/movies');
        $this->service->trackRequest($apiKey, '/api/v1/movies');
        $this->service->trackRequest($apiKey, '/api/v1/people');

        $usage = $this->service->getMonthlyUsage($apiKey, $month);

        $this->assertEquals(3, $usage);
    }

    public function test_get_remaining_quota_returns_correct_value(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 100,
        ]);

        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];

        // Use 30 requests
        for ($i = 0; $i < 30; $i++) {
            $this->service->trackRequest($apiKey, '/api/v1/movies');
        }

        $remaining = $this->service->getRemainingQuota($apiKey, $plan);

        $this->assertEquals(70, $remaining);
    }

    public function test_get_remaining_quota_returns_null_for_unlimited_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 0, // Unlimited
        ]);

        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];

        $remaining = $this->service->getRemainingQuota($apiKey, $plan);

        $this->assertNull($remaining);
    }

    public function test_has_exceeded_monthly_limit_returns_true_when_exceeded(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 10,
        ]);

        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];

        // Use all 10 requests
        for ($i = 0; $i < 10; $i++) {
            $this->service->trackRequest($apiKey, '/api/v1/movies');
        }

        $exceeded = $this->service->hasExceededMonthlyLimit($apiKey, $plan);

        $this->assertTrue($exceeded);
    }

    public function test_has_exceeded_rate_limit_returns_true_when_exceeded(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'rate_limit_per_minute' => 5,
        ]);

        $result = $this->apiKeyService->createKey('Test Key', planId: $plan->id);
        $apiKey = $result['apiKey'];

        // Create 5 usage records in the last minute (simulate by setting created_at)
        for ($i = 0; $i < 5; $i++) {
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

        $exceeded = $this->service->hasExceededRateLimit($apiKey, $plan);

        $this->assertTrue($exceeded);
    }

    public function test_get_usage_in_time_window_returns_correct_count(): void
    {
        $result = $this->apiKeyService->createKey('Test Key');
        $apiKey = $result['apiKey'];

        // Create 3 usage records now
        for ($i = 0; $i < 3; $i++) {
            $this->service->trackRequest($apiKey, '/api/v1/movies');
        }

        $usage = $this->service->getUsageInTimeWindow($apiKey, 60);

        $this->assertEquals(3, $usage);
    }
}
