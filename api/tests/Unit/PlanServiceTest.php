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
        $plan = SubscriptionPlan::factory()->create([
            'name' => 'test-plan',
            'display_name' => 'Test Plan',
        ]);

        $result = $this->service->getPlan($plan->id);

        $this->assertNotNull($result);
        $this->assertEquals($plan->id, $result->id);
        $this->assertEquals('test-plan', $result->name);
    }

    public function test_get_plan_returns_null_for_invalid_id(): void
    {
        $result = $this->service->getPlan('00000000-0000-0000-0000-000000000000');

        $this->assertNull($result);
    }

    public function test_get_plan_by_name_returns_plan(): void
    {
        SubscriptionPlan::factory()->create([
            'name' => 'pro',
            'display_name' => 'Pro',
            'is_active' => true,
        ]);

        $result = $this->service->getPlanByName('pro');

        $this->assertNotNull($result);
        $this->assertEquals('pro', $result->name);
    }

    public function test_get_plan_by_name_returns_null_for_inactive_plan(): void
    {
        SubscriptionPlan::factory()->create([
            'name' => 'inactive',
            'display_name' => 'Inactive',
            'is_active' => false,
        ]);

        $result = $this->service->getPlanByName('inactive');

        $this->assertNull($result);
    }

    public function test_get_default_plan_returns_free_plan(): void
    {
        SubscriptionPlan::factory()->create([
            'name' => 'free',
            'display_name' => 'Free',
            'is_active' => true,
        ]);

        $result = $this->service->getDefaultPlan();

        $this->assertNotNull($result);
        $this->assertEquals('free', $result->name);
    }

    public function test_can_use_feature_returns_true_when_plan_has_feature(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['read', 'generate'],
        ]);

        $this->assertTrue($this->service->canUseFeature($plan, 'read'));
        $this->assertTrue($this->service->canUseFeature($plan, 'generate'));
    }

    public function test_can_use_feature_returns_false_when_plan_lacks_feature(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['read'],
        ]);

        $this->assertFalse($this->service->canUseFeature($plan, 'generate'));
    }

    public function test_can_use_feature_returns_false_for_null_plan(): void
    {
        $this->assertFalse($this->service->canUseFeature(null, 'read'));
    }

    public function test_get_rate_limit_returns_plan_rate_limit(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'rate_limit_per_minute' => 100,
        ]);

        $result = $this->service->getRateLimit($plan);

        $this->assertEquals(100, $result);
    }

    public function test_get_rate_limit_returns_default_for_null_plan(): void
    {
        $result = $this->service->getRateLimit(null);

        $this->assertEquals(10, $result); // Default rate limit
    }

    public function test_get_monthly_limit_returns_plan_limit(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 10000,
        ]);

        $result = $this->service->getMonthlyLimit($plan);

        $this->assertEquals(10000, $result);
    }

    public function test_get_monthly_limit_returns_unlimited_for_zero(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 0, // Unlimited
        ]);

        $result = $this->service->getMonthlyLimit($plan);

        $this->assertEquals(0, $result);
    }

    public function test_get_monthly_limit_returns_default_for_null_plan(): void
    {
        $result = $this->service->getMonthlyLimit(null);

        $this->assertEquals(100, $result); // Default monthly limit
    }
}
