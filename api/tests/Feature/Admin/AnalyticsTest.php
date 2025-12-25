<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SubscriptionPlan;
use App\Services\ApiKeyService;
use App\Services\UsageTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $apiKeyService;

    private UsageTracker $usageTracker;

    private SubscriptionPlan $freePlan;

    private SubscriptionPlan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // Bypass Admin API auth for tests
        config(['app.env' => 'local']);
        putenv('ADMIN_AUTH_BYPASS_ENVS=local,staging');

        $this->apiKeyService = app(ApiKeyService::class);
        $this->usageTracker = app(UsageTracker::class);

        $this->freePlan = SubscriptionPlan::where('name', 'free')->firstOrFail();
        $this->proPlan = SubscriptionPlan::where('name', 'pro')->firstOrFail();
    }

    public function test_overview_returns_usage_and_revenue_stats(): void
    {
        // Create API key and track some usage
        $result = $this->apiKeyService->createKey('Test Key', $this->freePlan->id);
        $apiKey = $result['apiKey'];

        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan);

        $response = $this->getJson('/api/v1/admin/analytics/overview');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'usage' => [
                'total_requests',
                'successful_requests',
                'error_requests',
                'success_rate',
                'error_rate',
                'avg_response_time_ms',
            ],
            'revenue' => [
                'monthly_revenue',
                'yearly_revenue',
                'active_subscriptions',
            ],
        ]);
    }

    public function test_by_plan_returns_usage_per_plan(): void
    {
        // Create API keys with different plans
        $freeKey = $this->apiKeyService->createKey('Free Key', $this->freePlan->id)['apiKey'];
        $proKey = $this->apiKeyService->createKey('Pro Key', $this->proPlan->id)['apiKey'];

        // Track usage
        $this->usageTracker->trackRequest($freeKey, '/api/v1/movies', $this->freePlan);
        $this->usageTracker->trackRequest($proKey, '/api/v1/movies', $this->proPlan);
        $this->usageTracker->trackRequest($proKey, '/api/v1/movies/search', $this->proPlan);

        $response = $this->getJson('/api/v1/admin/analytics/by-plan');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'plan_id',
                    'plan_name',
                    'plan_display_name',
                    'total_requests',
                ],
            ],
        ]);
    }

    public function test_by_endpoint_returns_top_endpoints(): void
    {
        $result = $this->apiKeyService->createKey('Test Key', $this->freePlan->id);
        $apiKey = $result['apiKey'];

        // Track usage on different endpoints
        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan);
        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan);
        $this->usageTracker->trackRequest($apiKey, '/api/v1/people', $this->freePlan);

        $response = $this->getJson('/api/v1/admin/analytics/by-endpoint?limit=5');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'endpoint',
                    'method',
                    'total_requests',
                ],
            ],
        ]);
    }

    public function test_by_time_range_returns_usage_by_period(): void
    {
        $result = $this->apiKeyService->createKey('Test Key', $this->freePlan->id);
        $apiKey = $result['apiKey'];

        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan);

        $response = $this->getJson('/api/v1/admin/analytics/by-time-range?period=daily');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'period',
            'data' => [
                '*' => [
                    'period',
                    'total_requests',
                    'successful_requests',
                    'error_requests',
                ],
            ],
        ]);
    }

    public function test_by_time_range_rejects_invalid_period(): void
    {
        $response = $this->getJson('/api/v1/admin/analytics/by-time-range?period=invalid');

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'Invalid period. Must be: daily, weekly, or monthly',
        ]);
    }

    public function test_top_api_keys_returns_most_active_keys(): void
    {
        $key1 = $this->apiKeyService->createKey('Key 1', $this->freePlan->id)['apiKey'];
        $key2 = $this->apiKeyService->createKey('Key 2', $this->freePlan->id)['apiKey'];

        // Key 1 has more usage
        $this->usageTracker->trackRequest($key1, '/api/v1/movies', $this->freePlan);
        $this->usageTracker->trackRequest($key1, '/api/v1/movies', $this->freePlan);
        $this->usageTracker->trackRequest($key2, '/api/v1/movies', $this->freePlan);

        $response = $this->getJson('/api/v1/admin/analytics/top-api-keys?limit=5');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'api_key_id',
                    'api_key_name',
                    'api_key_prefix',
                    'total_requests',
                ],
            ],
        ]);
    }

    public function test_error_rate_returns_error_percentage(): void
    {
        $result = $this->apiKeyService->createKey('Test Key', $this->freePlan->id);
        $apiKey = $result['apiKey'];

        // Track successful and error requests
        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan, 'GET', 200);
        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan, 'GET', 404);
        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan, 'GET', 200);

        $response = $this->getJson('/api/v1/admin/analytics/error-rate');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'error_rate',
            'start_date',
            'end_date',
        ]);

        // Should be ~33% error rate (1 error out of 3 requests)
        $this->assertGreaterThanOrEqual(30.0, $response->json('error_rate'));
        $this->assertLessThanOrEqual(35.0, $response->json('error_rate'));
    }

    public function test_overview_filters_by_date_range(): void
    {
        $result = $this->apiKeyService->createKey('Test Key', $this->freePlan->id);
        $apiKey = $result['apiKey'];

        $this->usageTracker->trackRequest($apiKey, '/api/v1/movies', $this->freePlan);

        $response = $this->getJson('/api/v1/admin/analytics/overview?start_date=2025-01-01&end_date=2025-12-31');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'usage',
            'revenue',
        ]);
    }
}
