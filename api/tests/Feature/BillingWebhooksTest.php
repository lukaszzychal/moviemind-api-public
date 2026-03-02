<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for billing webhooks endpoint.
 *
 * Note: Billing webhooks are currently not implemented (return 501).
 * This endpoint is prepared for future billing providers (Stripe, PayPal, etc.).
 * For portfolio/demo, subscriptions are managed locally via API keys.
 */
class BillingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);
    }

    public function test_webhook_returns_not_implemented(): void
    {
        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'plan' => 'free',
            ],
        ]);

        $response->assertStatus(501);
        $response->assertJson([
            'error' => 'Billing webhooks are not currently implemented',
            'message' => 'This endpoint is prepared for future billing providers (Stripe, PayPal, etc.). For portfolio/demo, subscriptions are managed locally via API keys.',
        ]);
    }
}
