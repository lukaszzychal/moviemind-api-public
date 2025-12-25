<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Services\ApiKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PlanBasedRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
        $this->apiKeyService = new ApiKeyService;

        // Create a test route protected by rapidapi.auth and plan.rate.limit middleware
        Route::middleware(['rapidapi.auth', 'plan.rate.limit'])->get('/test-plan-rate-limit', function () {
            return response()->json(['message' => 'Success']);
        });
    }

    public function test_free_plan_allows_requests_within_limit(): void
    {
        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $result = $this->apiKeyService->createKey('Free Plan Key', planId: $freePlan->id);
        $apiKey = $result['apiKey'];
        $plaintextKey = $result['key'];

        // Make a request within limit
        $response = $this->withHeader('X-RapidAPI-Key', $plaintextKey)
            ->getJson('/test-plan-rate-limit');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Monthly-Limit', '100');
        $response->assertHeader('X-RateLimit-Monthly-Used', '1');
        $response->assertHeader('X-RateLimit-Monthly-Remaining', '99');
    }

    public function test_free_plan_rejects_requests_when_monthly_limit_exceeded(): void
    {
        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $result = $this->apiKeyService->createKey('Free Plan Key', planId: $freePlan->id);
        $apiKey = $result['apiKey'];
        $plaintextKey = $result['key'];

        // Exceed monthly limit by creating 100 usage records
        for ($i = 0; $i < 100; $i++) {
            \App\Models\ApiUsage::create([
                'api_key_id' => $apiKey->id,
                'plan_id' => $freePlan->id,
                'endpoint' => '/api/v1/movies',
                'method' => 'GET',
                'response_status' => 200,
                'month' => now()->format('Y-m'),
            ]);
        }

        // Next request should be rejected
        $response = $this->withHeader('X-RapidAPI-Key', $plaintextKey)
            ->getJson('/test-plan-rate-limit');

        $response->assertStatus(429)
            ->assertJson(['error' => 'Too many requests'])
            ->assertHeader('X-RateLimit-Monthly-Limit', '100')
            ->assertHeader('X-RateLimit-Monthly-Used', '100')
            ->assertHeader('X-RateLimit-Monthly-Remaining', '0');
    }

    public function test_enterprise_plan_has_unlimited_monthly_requests(): void
    {
        $enterprisePlan = SubscriptionPlan::where('name', 'enterprise')->first();
        $result = $this->apiKeyService->createKey('Enterprise Plan Key', planId: $enterprisePlan->id);
        $plaintextKey = $result['key'];

        // Make multiple requests - should all succeed
        for ($i = 0; $i < 5; $i++) {
            $response = $this->withHeader('X-RapidAPI-Key', $plaintextKey)
                ->getJson('/test-plan-rate-limit');

            $response->assertOk();
            $response->assertHeader('X-RateLimit-Monthly-Limit', 'unlimited');
            $response->assertHeader('X-RateLimit-Monthly-Remaining', 'unlimited');
        }
    }

    public function test_rate_limit_headers_are_present(): void
    {
        $proPlan = SubscriptionPlan::where('name', 'pro')->first();
        $result = $this->apiKeyService->createKey('Pro Plan Key', planId: $proPlan->id);
        $plaintextKey = $result['key'];

        $response = $this->withHeader('X-RapidAPI-Key', $plaintextKey)
            ->getJson('/test-plan-rate-limit');

        $response->assertOk();
        $response->assertHeader('X-RateLimit-Per-Minute-Limit', '100');
        $response->assertHeader('X-RateLimit-Per-Minute-Used');
        $response->assertHeader('X-RateLimit-Per-Minute-Remaining');
    }
}
