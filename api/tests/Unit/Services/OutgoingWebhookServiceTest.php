<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\OutgoingWebhook;
use App\Services\OutgoingWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OutgoingWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private OutgoingWebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = app(OutgoingWebhookService::class);
    }

    public function test_send_webhook_creates_outgoing_webhook_record(): void
    {
        // ARRANGE: Mock HTTP client
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: ['entity_type' => 'MOVIE', 'entity_id' => 'the-matrix-1999'],
            url: 'https://example.com/webhook'
        );

        // ASSERT: Outgoing webhook was created
        $this->assertInstanceOf(OutgoingWebhook::class, $webhook);
        $this->assertEquals('generation.completed', $webhook->event_type);
        $this->assertEquals('https://example.com/webhook', $webhook->url);
        $this->assertEquals('sent', $webhook->status);
        $this->assertNotNull($webhook->sent_at);
    }

    public function test_send_webhook_stores_payload_correctly(): void
    {
        // ARRANGE: Mock HTTP client
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $payload = [
            'entity_type' => 'MOVIE',
            'entity_id' => 'the-matrix-1999',
            'job_id' => '550e8400-e29b-41d4-a716-446655440000',
        ];

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: $payload,
            url: 'https://example.com/webhook'
        );

        // ASSERT: Payload stored correctly
        $this->assertEquals($payload, $webhook->payload);
    }

    public function test_send_webhook_signs_request_when_secret_configured(): void
    {
        // ARRANGE: Configure webhook secret
        config(['webhooks.outgoing_secret' => 'test-secret']);
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        $payload = ['entity_type' => 'MOVIE'];

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: $payload,
            url: 'https://example.com/webhook'
        );

        // ASSERT: Request was signed
        Http::assertSent(function ($request) {
            $signature = $request->header('X-MovieMind-Webhook-Signature');
            $this->assertNotNull($signature);
            // Header can be array or string, normalize to string
            $signature = is_array($signature) ? $signature[0] : $signature;
            $body = $request->body();
            $expectedSignature = hash_hmac('sha256', $body, 'test-secret');

            return hash_equals($expectedSignature, $signature);
        });
    }

    public function test_send_webhook_marks_as_failed_on_http_error(): void
    {
        // ARRANGE: Mock HTTP client to return error
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Internal Server Error'], 500),
        ]);

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: ['entity_type' => 'MOVIE'],
            url: 'https://example.com/webhook'
        );

        // ASSERT: Webhook marked as failed
        $this->assertEquals('failed', $webhook->status);
        $this->assertEquals(500, $webhook->response_code);
        $this->assertNotNull($webhook->error_message);
        $this->assertNotNull($webhook->next_retry_at);
    }

    public function test_send_webhook_marks_as_failed_on_network_error(): void
    {
        // ARRANGE: Mock HTTP client to throw exception
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
        });

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: ['entity_type' => 'MOVIE'],
            url: 'https://example.com/webhook'
        );

        // ASSERT: Webhook marked as failed
        $this->assertEquals('failed', $webhook->status);
        $this->assertNotNull($webhook->error_message);
        $this->assertNotNull($webhook->next_retry_at);
    }

    public function test_send_webhook_stores_response_body(): void
    {
        // ARRANGE: Mock HTTP client
        $responseBody = ['status' => 'ok', 'message' => 'Received'];
        Http::fake([
            'https://example.com/webhook' => Http::response($responseBody, 200),
        ]);

        // ACT: Send webhook
        $webhook = $this->service->sendWebhook(
            eventType: 'generation.completed',
            payload: ['entity_type' => 'MOVIE'],
            url: 'https://example.com/webhook'
        );

        // ASSERT: Response body stored
        $this->assertEquals(200, $webhook->response_code);
        $this->assertNotNull($webhook->response_body);
    }

    public function test_retry_webhook_resends_failed_webhook(): void
    {
        // ARRANGE: Create failed webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'error_message' => 'Network error',
            'next_retry_at' => now()->subMinute(), // Ready for retry
        ]);

        // Mock HTTP client to succeed on retry
        Http::fake([
            'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
        ]);

        // ACT: Retry webhook
        $this->service->retryWebhook($webhook);

        // ASSERT: Webhook marked as sent
        $webhook->refresh();
        $this->assertEquals('sent', $webhook->status);
        $this->assertEquals(2, $webhook->attempts);
        $this->assertNotNull($webhook->sent_at);
    }

    public function test_retry_webhook_marks_as_permanently_failed_after_max_attempts(): void
    {
        // ARRANGE: Create webhook at max-1 attempts (so it can be retried one more time)
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 2, // Changed from 3 to 2 (max-1)
            'max_attempts' => 3,
            'error_message' => 'Network error',
            'next_retry_at' => now()->subMinute(),
        ]);

        // Mock HTTP client to fail again
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Server Error'], 500),
        ]);

        // ACT: Retry webhook
        $this->service->retryWebhook($webhook);

        // ASSERT: Webhook marked as permanently failed (attempts will be 3 after retry)
        $webhook->refresh();
        $this->assertEquals('permanently_failed', $webhook->status);
        $this->assertEquals(3, $webhook->attempts); // Now at max attempts
        $this->assertNull($webhook->next_retry_at);
    }

    public function test_get_webhooks_ready_for_retry_returns_failed_webhooks(): void
    {
        // ARRANGE: Create webhooks with different statuses
        OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(), // Ready
        ]);

        OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 2,
            'max_attempts' => 3,
            'next_retry_at' => now()->addMinute(), // Not ready
        ]);

        OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'sent',
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        // ACT: Get webhooks ready for retry
        $webhooks = $this->service->getWebhooksReadyForRetry();

        // ASSERT: Only failed webhooks ready for retry are returned
        $this->assertCount(1, $webhooks);
        $this->assertEquals('failed', $webhooks->first()->status);
        $this->assertTrue($webhooks->first()->next_retry_at->isPast());
    }
}
