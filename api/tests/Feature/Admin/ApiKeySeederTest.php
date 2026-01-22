<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\ApiKey;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test for ApiKeySeeder (demo API keys).
 *
 * Tests that seeder creates demo API keys for each subscription plan.
 */
class ApiKeySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_seeder_creates_demo_api_keys_for_each_plan(): void
    {
        // Given: Subscription plans exist
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // When: Running ApiKeySeeder
        $this->artisan('db:seed', ['--class' => 'ApiKeySeeder']);

        // Then: Should create API keys for each plan
        $freePlan = SubscriptionPlan::where('name', 'free')->first();
        $proPlan = SubscriptionPlan::where('name', 'pro')->first();
        $enterprisePlan = SubscriptionPlan::where('name', 'enterprise')->first();

        $freeKeys = ApiKey::where('plan_id', $freePlan->id)->get();
        $proKeys = ApiKey::where('plan_id', $proPlan->id)->get();
        $enterpriseKeys = ApiKey::where('plan_id', $enterprisePlan->id)->get();

        $this->assertGreaterThanOrEqual(1, $freeKeys->count(), 'Should have at least one Free plan API key');
        $this->assertGreaterThanOrEqual(1, $proKeys->count(), 'Should have at least one Pro plan API key');
        $this->assertGreaterThanOrEqual(1, $enterpriseKeys->count(), 'Should have at least one Enterprise plan API key');
    }

    public function test_seeder_creates_active_api_keys(): void
    {
        // Given: Subscription plans exist
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // When: Running ApiKeySeeder
        $this->artisan('db:seed', ['--class' => 'ApiKeySeeder']);

        // Then: All created keys should be active
        $apiKeys = ApiKey::all();
        foreach ($apiKeys as $apiKey) {
            $this->assertTrue($apiKey->is_active, "API key {$apiKey->id} should be active");
        }
    }

    public function test_seeder_creates_api_keys_with_valid_prefixes(): void
    {
        // Given: Subscription plans exist
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // When: Running ApiKeySeeder
        $this->artisan('db:seed', ['--class' => 'ApiKeySeeder']);

        // Then: All keys should have valid prefixes
        $apiKeys = ApiKey::all();
        foreach ($apiKeys as $apiKey) {
            $this->assertNotNull($apiKey->key_prefix, "API key {$apiKey->id} should have a prefix");
            $this->assertGreaterThanOrEqual(8, strlen($apiKey->key_prefix), "API key {$apiKey->id} prefix should be at least 8 characters");
        }
    }

    public function test_seeder_creates_api_keys_with_descriptive_names(): void
    {
        // Given: Subscription plans exist
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // When: Running ApiKeySeeder
        $this->artisan('db:seed', ['--class' => 'ApiKeySeeder']);

        // Then: Keys should have descriptive names
        $apiKeys = ApiKey::all();
        foreach ($apiKeys as $apiKey) {
            $this->assertNotNull($apiKey->name, "API key {$apiKey->id} should have a name");
            $this->assertNotEmpty($apiKey->name, "API key {$apiKey->id} name should not be empty");
        }
    }
}
