<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class NotificationWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        // Disable webhook signature verification for testing
        Config::set('webhooks.verify_notification_signature', false);
    }

    public function test_webhook_rejects_request_without_signature_when_verification_enabled(): void
    {
        Config::set('webhooks.verify_notification_signature', true);
        Config::set('webhooks.notification_secret', 'test-secret');

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Invalid signature',
        ]);
    }

    public function test_webhook_accepts_request_when_verification_disabled(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
        ]);
    }

    public function test_webhook_rejects_invalid_structure(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            // Missing 'data' field
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error',
            'errors',
        ]);
    }

    public function test_webhook_handles_generation_completed_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
                'job_id' => '550e8400-e29b-41d4-a716-446655440000',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        // Verify webhook event was stored in database
        $this->assertDatabaseHas('webhook_events', [
            'event_type' => 'notification',
            'source' => 'external',
            'status' => 'processed',
        ]);
    }

    public function test_webhook_handles_generation_failed_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.failed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
                'job_id' => '550e8400-e29b-41d4-a716-446655440000',
                'error' => 'AI generation failed',
            ],
            'idempotency_key' => 'test-key-2',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);

        // Verify webhook event was stored
        $this->assertDatabaseHas('webhook_events', [
            'event_type' => 'notification',
            'source' => 'external',
            'status' => 'processed',
        ]);
    }

    public function test_webhook_handles_user_registered_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'user.registered',
            'data' => [
                'user_id' => 'user-123',
                'email' => 'user@example.com',
            ],
            'idempotency_key' => 'test-key-3',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_handles_user_updated_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'user.updated',
            'data' => [
                'user_id' => 'user-123',
                'email' => 'newemail@example.com',
            ],
            'idempotency_key' => 'test-key-4',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
        ]);
    }

    public function test_webhook_handles_unknown_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'unknown.event',
            'data' => [],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'ignored',
        ]);
    }

    public function test_webhook_idempotency_prevents_duplicate_processing(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $idempotencyKey = 'test-idempotency-key';

        // First request
        $response1 = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $webhookId1 = $response1->json('webhook_id');

        // Second request with same idempotency key
        $response2 = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $webhookId2 = $response2->json('webhook_id');

        // Should return the same webhook ID
        $this->assertEquals($webhookId1, $webhookId2);

        // Should only have one webhook event in database
        $this->assertDatabaseCount('webhook_events', 1);
    }

    public function test_webhook_validates_signature_when_enabled(): void
    {
        Config::set('webhooks.verify_notification_signature', true);
        Config::set('webhooks.notification_secret', 'test-secret');

        $payload = [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
        ];

        $body = json_encode($payload);
        $signature = hash_hmac('sha256', $body, 'test-secret');

        $response = $this->withHeaders([
            'X-Notification-Webhook-Signature' => $signature,
        ])->postJson('/api/v1/webhooks/notification', $payload);

        $response->assertStatus(200);
    }

    public function test_webhook_stores_event_in_database(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
            'idempotency_key' => 'test-key-5',
        ]);

        $response->assertStatus(200);

        // Verify webhook event was stored with correct data
        $this->assertDatabaseHas('webhook_events', [
            'event_type' => 'notification',
            'source' => 'external',
            'idempotency_key' => 'test-key-5',
        ]);

        $webhookEvent = WebhookEvent::where('idempotency_key', 'test-key-5')->first();
        $this->assertNotNull($webhookEvent);
        $this->assertEquals('generation.completed', $webhookEvent->payload['event']);
        $this->assertEquals('MOVIE', $webhookEvent->payload['data']['entity_type']);
    }
}
