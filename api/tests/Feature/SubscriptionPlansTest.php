<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionPlansTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_seeder_creates_three_plans(): void
    {
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        $plans = SubscriptionPlan::all();

        $this->assertCount(3, $plans);
        $this->assertTrue($plans->contains('name', 'free'));
        $this->assertTrue($plans->contains('name', 'pro'));
        $this->assertTrue($plans->contains('name', 'enterprise'));
    }

    public function test_free_plan_has_correct_limits(): void
    {
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        $freePlan = SubscriptionPlan::where('name', 'free')->first();

        $this->assertNotNull($freePlan);
        $this->assertEquals(100, $freePlan->monthly_limit);
        $this->assertEquals(10, $freePlan->rate_limit_per_minute);
        $this->assertTrue($freePlan->hasFeature('read'));
        $this->assertFalse($freePlan->hasFeature('generate'));
    }

    public function test_pro_plan_has_correct_limits(): void
    {
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        $proPlan = SubscriptionPlan::where('name', 'pro')->first();

        $this->assertNotNull($proPlan);
        $this->assertEquals(10000, $proPlan->monthly_limit);
        $this->assertEquals(100, $proPlan->rate_limit_per_minute);
        $this->assertTrue($proPlan->hasFeature('read'));
        $this->assertTrue($proPlan->hasFeature('generate'));
        $this->assertTrue($proPlan->hasFeature('context_tags'));
    }

    public function test_enterprise_plan_has_unlimited_requests(): void
    {
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        $enterprisePlan = SubscriptionPlan::where('name', 'enterprise')->first();

        $this->assertNotNull($enterprisePlan);
        $this->assertEquals(0, $enterprisePlan->monthly_limit); // 0 = unlimited
        $this->assertTrue($enterprisePlan->isUnlimited());
        $this->assertEquals(1000, $enterprisePlan->rate_limit_per_minute);
        $this->assertTrue($enterprisePlan->hasFeature('webhooks'));
        $this->assertTrue($enterprisePlan->hasFeature('analytics'));
    }

    public function test_plan_has_feature_method_works(): void
    {
        $plan = SubscriptionPlan::factory()->create([
            'features' => ['read', 'generate'],
        ]);

        $this->assertTrue($plan->hasFeature('read'));
        $this->assertTrue($plan->hasFeature('generate'));
        $this->assertFalse($plan->hasFeature('webhooks'));
    }

    public function test_plan_is_unlimited_method_works(): void
    {
        $limitedPlan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 1000,
        ]);

        $unlimitedPlan = SubscriptionPlan::factory()->create([
            'monthly_limit' => 0,
        ]);

        $this->assertFalse($limitedPlan->isUnlimited());
        $this->assertTrue($unlimitedPlan->isUnlimited());
    }
}
