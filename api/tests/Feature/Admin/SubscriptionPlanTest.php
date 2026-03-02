<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-admin-token';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('admin.api_token', $this->token);

        // Bypass Admin API auth for tests (so requests without token also work in CI)
        config(['app.env' => 'testing']);
        config(['admin.auth.bypass_environments' => ['local', 'staging', 'testing']]);
    }

    public function test_list_plans(): void
    {
        SubscriptionPlan::factory()->create(['name' => 'Plan A']);
        SubscriptionPlan::factory()->create(['name' => 'Plan B']);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson('/api/v1/admin/subscription-plans');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_show_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['name' => 'Plan A']);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->getJson("/api/v1/admin/subscription-plans/{$plan->id}");

        $response->assertOk()
            ->assertJsonPath('name', 'Plan A');
    }

    public function test_add_feature_to_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['features' => ['read']]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson("/api/v1/admin/subscription-plans/{$plan->id}/features", [
                'feature' => 'write',
            ]);

        $response->assertOk();
        $this->assertTrue($plan->refresh()->hasFeature('write'));
        $this->assertTrue($plan->hasFeature('read'));
    }

    public function test_add_feature_checks_duplicates(): void
    {
        $plan = SubscriptionPlan::factory()->create(['features' => ['read']]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->postJson("/api/v1/admin/subscription-plans/{$plan->id}/features", [
                'feature' => 'read',
            ]);

        $response->assertOk();
        $this->assertCount(1, $plan->refresh()->features); // Should still be 1
    }

    public function test_remove_feature_from_plan(): void
    {
        $plan = SubscriptionPlan::factory()->create(['features' => ['read', 'write']]);

        $response = $this->withHeader('X-Admin-Token', $this->token)
            ->deleteJson("/api/v1/admin/subscription-plans/{$plan->id}/features/write");

        $response->assertOk();
        $this->assertFalse($plan->refresh()->hasFeature('write'));
        $this->assertTrue($plan->hasFeature('read'));
    }
}
