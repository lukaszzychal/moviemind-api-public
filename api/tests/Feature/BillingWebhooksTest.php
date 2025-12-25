<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BillingWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->artisan('db:seed', ['--class' => 'SubscriptionPlanSeeder']);

        // Disable webhook signature verification for testing
        Config::set('rapidapi.verify_webhook_signature', false);
    }

    public function test_webhook_rejects_request_without_signature_when_verification_enabled(): void
    {
        Config::set('rapidapi.verify_webhook_signature', true);
        Config::set('rapidapi.webhook_secret', 'test-secret');

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Invalid signature',
        ]);
    }

    public function test_webhook_accepts_request_when_verification_disabled(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        // Verify plans exist
        $this->assertDatabaseHas('subscription_plans', ['name' => 'free']);
        $this->assertDatabaseHas('subscription_plans', ['name' => 'pro']);
        $this->assertDatabaseHas('subscription_plans', ['name' => 'enterprise']);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        if ($response->status() !== 201) {
            $this->fail('Expected 201 but got '.$response->status().': '.$response->content());
        }

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'status',
            'subscription_id',
        ]);
    }

    public function test_webhook_rejects_invalid_structure(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            // Missing 'data' field
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error',
            'errors',
        ]);
    }

    public function test_webhook_handles_subscription_created_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'rapidapi_user_id' => 'user-123',
            'status' => 'active',
        ]);
    }

    public function test_webhook_handles_subscription_updated_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        // First create a subscription
        $createResponse = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $subscriptionId = $createResponse->json('subscription_id');

        // Then update it
        $updateResponse = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.updated',
            'data' => [
                'subscription_id' => $subscriptionId,
                'plan' => 'pro',
            ],
            'idempotency_key' => 'test-key-2',
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_handles_subscription_cancelled_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        // First create a subscription
        $createResponse = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $subscriptionId = $createResponse->json('subscription_id');
        $this->assertNotNull($subscriptionId);

        // Then cancel it
        $cancelResponse = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.cancelled',
            'data' => [
                'subscription_id' => $subscriptionId,
            ],
            'idempotency_key' => 'test-key-3',
        ]);

        $cancelResponse->assertStatus(200);
        $cancelResponse->assertJson([
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscriptionId,
            'status' => 'cancelled',
        ]);
    }

    public function test_webhook_handles_payment_succeeded_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'payment.succeeded',
            'data' => [
                'subscription_id' => 'test-subscription-id',
                'amount' => 9.99,
            ],
            'idempotency_key' => 'test-key-4',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Payment event logged',
        ]);
    }

    public function test_webhook_handles_payment_failed_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'payment.failed',
            'data' => [
                'subscription_id' => 'test-subscription-id',
                'reason' => 'insufficient_funds',
            ],
            'idempotency_key' => 'test-key-5',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Payment failure event logged',
        ]);
    }

    public function test_webhook_handles_unknown_event(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $response = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'unknown.event',
            'data' => [],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ignored',
        ]);
    }

    public function test_webhook_idempotency_prevents_duplicate_subscriptions(): void
    {
        Config::set('rapidapi.verify_webhook_signature', false);

        $idempotencyKey = 'test-idempotency-key';

        // First request
        $response1 = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $subscriptionId1 = $response1->json('subscription_id');

        // Second request with same idempotency key
        $response2 = $this->postJson('/api/v1/webhooks/billing', [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $subscriptionId2 = $response2->json('subscription_id');

        // Should return the same subscription ID
        $this->assertEquals($subscriptionId1, $subscriptionId2);

        // Should only have one subscription in database
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_webhook_validates_signature_when_enabled(): void
    {
        Config::set('rapidapi.verify_webhook_signature', true);
        Config::set('rapidapi.webhook_secret', 'test-secret');

        $payload = [
            'event' => 'subscription.created',
            'data' => [
                'rapidapi_user_id' => 'user-123',
                'plan' => 'basic',
            ],
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->withHeaders([
            'X-RapidAPI-Signature' => $signature,
        ])->postJson('/api/v1/webhooks/billing', $payload);

        $response->assertStatus(201);
    }
}
