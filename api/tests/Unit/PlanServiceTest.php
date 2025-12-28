<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\SubscriptionPlan;
use App\Services\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new PlanService;
    }

    public function test_get_plan_returns_plan_by_id(): void
    {
        // ARRANGE: Create a plan
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'test-plan',
            'display_name' => 'Test Plan',
        ]);

        // ACT: Get plan by ID
        $result = $this->service->getPlan($plan->id);

        // ASSERT: Should return the correct plan
        $this->assertNotNull($result);
        $this->assertEquals($plan->id, $result->id);
        $this->assertEquals('test-plan', $result->name);
    }

    public function test_get_plan_returns_null_for_invalid_id(): void
    {
        // ARRANGE: Invalid plan ID

        // ACT: Get plan by invalid ID
        $result = $this->service->getPlan('00000000-0000-0000-0000-000000000000');

        // ASSERT: Should return null
        $this->assertNull($result);
    }

    public function test_get_plan_by_name_returns_plan(): void
    {
        // ARRANGE: Create an active plan
        SubscriptionPlan::factory()->create([
            'name' => 'pro',
            'display_name' => 'Pro',
            'is_active' => true,
        ]);

        // ACT: Get plan by name
        $result = $this->service->getPlanByName('pro');

        // ASSERT: Should return the correct plan
        $this->assertNotNull($result);
        $this->assertEquals('pro', $result->name);
    }

    public function test_get_plan_by_name_returns_null_for_inactive_plan(): void
    {
        // ARRANGE: Create an inactive plan
        SubscriptionPlan::factory()->create([
            'name' => 'inactive',
            'display_name' => 'Inactive',
            'is_active' => false,
        ]);

        // ACT: Get plan by name
        $result = $this->service->getPlanByName('inactive');

        // ASSERT: Should return null for inactive plan
        $this->assertNull($result);
    }

    public function test_get_default_plan_returns_free_plan(): void
    {
        // ARRANGE: Create a free plan
        SubscriptionPlan::factory()->create([
            'name' => 'free',
            'display_name' => 'Free',
            'is_active' => true,
        ]);

        // ACT: Get default plan
        $result = $this->service->getDefaultPlan();

        // ASSERT: Should return the free plan
        $this->assertNotNull($result);
        $this->assertEquals('free', $result->name);
    }

    public function test_can_use_feature_returns_true_when_plan_has_feature(): void
    {
        // ARRANGE: Create plan with features
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['read', 'generate'],
        ]);

        // ACT & ASSERT: Should return true for features in plan
        $this->assertTrue($this->service->canUseFeature($plan, 'read'));
        $this->assertTrue($this->service->canUseFeature($plan, 'generate'));
    }

    public function test_can_use_feature_returns_false_when_plan_lacks_feature(): void
    {
        // ARRANGE: Create plan without the feature
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['read'],
        ]);

        // ACT: Check for feature not in plan
        $result = $this->service->canUseFeature($plan, 'generate');

        // ASSERT: Should return false
        $this->assertFalse($result);
    }

    public function test_can_use_feature_returns_false_for_null_plan(): void
    {
        // ARRANGE: Null plan

        // ACT: Check feature for null plan
        $result = $this->service->canUseFeature(null, 'read');

        // ASSERT: Should return false
        $this->assertFalse($result);
    }

    public function test_get_rate_limit_returns_plan_rate_limit(): void
    {
        // ARRANGE: Create plan with rate limit
        $plan = SubscriptionPlan::factory()->create([
            'rate_limit_per_minute' => 100,
        ]);

        // ACT: Get rate limit
        $result = $this->service->getRateLimit($plan);

        // ASSERT: Should return plan's rate limit
        $this->assertEquals(100, $result);
    }

    public function test_get_rate_limit_returns_default_for_null_plan(): void
    {
        // ARRANGE: Null plan

        // ACT: Get rate limit for null plan
        $result = $this->service->getRateLimit(null);

        // ASSERT: Should return default rate limit
        $this->assertEquals(10, $result);
    }

    public function test_get_monthly_limit_returns_plan_limit(): void
    {
        // ARRANGE: Create plan with monthly limit
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 10000,
        ]);

        // ACT: Get monthly limit
        $result = $this->service->getMonthlyLimit($plan);

        // ASSERT: Should return plan's monthly limit
        $this->assertEquals(10000, $result);
    }

    public function test_get_monthly_limit_returns_unlimited_for_zero(): void
    {
        // ARRANGE: Create plan with unlimited monthly limit (0)
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 0, // Unlimited
        ]);

        // ACT: Get monthly limit
        $result = $this->service->getMonthlyLimit($plan);

        // ASSERT: Should return 0 (unlimited)
        $this->assertEquals(0, $result);
    }

    public function test_get_monthly_limit_returns_default_for_null_plan(): void
    {
        // ARRANGE: Null plan

        // ACT: Get monthly limit for null plan
        $result = $this->service->getMonthlyLimit(null);

        // ASSERT: Should return default monthly limit
        $this->assertEquals(100, $result);
    }
}
