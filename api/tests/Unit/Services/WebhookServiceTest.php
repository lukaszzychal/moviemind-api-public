<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebhookService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->service = new WebhookService;
    }

    public function test_process_webhook_creates_webhook_event(): void
    {
        // ARRANGE: Webhook payload
        $payload = ['test' => 'data'];
        $idempotencyKey = 'test-key-123';

        // ACT: Process webhook
        $webhookEvent = $this->service->processWebhook(
            eventType: 'billing',
            source: 'rapidapi',
            payload: $payload,
            idempotencyKey: $idempotencyKey,
            processor: fn (array $p) => null, // Successful processor
            maxAttempts: 3
        );

        // ASSERT: Webhook event was created and processed
        $this->assertInstanceOf(WebhookEvent::class, $webhookEvent);
        $this->assertTrue($webhookEvent->isProcessed());
        $this->assertEquals('billing', $webhookEvent->event_type);
        $this->assertEquals('rapidapi', $webhookEvent->source);
        $this->assertEquals($idempotencyKey, $webhookEvent->idempotency_key);
        $this->assertEquals($payload, $webhookEvent->payload);
    }

    public function test_process_webhook_prevents_duplicate_with_idempotency_key(): void
    {
        // ARRANGE: Create first webhook
        $idempotencyKey = 'test-key-123';
        $firstWebhook = $this->service->processWebhook(
            eventType: 'billing',
            source: 'rapidapi',
            payload: ['test' => 'data'],
            idempotencyKey: $idempotencyKey,
            processor: fn (array $p) => null,
        );

        // ACT: Process same webhook again
        $secondWebhook = $this->service->processWebhook(
            eventType: 'billing',
            source: 'rapidapi',
            payload: ['test' => 'different'],
            idempotencyKey: $idempotencyKey,
            processor: fn (array $p) => null,
        );

        // ASSERT: Same webhook event returned
        $this->assertEquals($firstWebhook->id, $secondWebhook->id);
        $this->assertTrue($secondWebhook->isProcessed());
    }

    public function test_process_webhook_marks_as_failed_on_exception(): void
    {
        // ARRANGE: Processor that throws exception
        Queue::fake();

        // ACT: Process webhook with failing processor
        $webhookEvent = $this->service->processWebhook(
            eventType: 'billing',
            source: 'rapidapi',
            payload: ['test' => 'data'],
            idempotencyKey: 'test-key-123',
            processor: fn (array $p) => throw new \RuntimeException('Processing failed'),
        );

        // ASSERT: Webhook marked as failed
        $this->assertTrue($webhookEvent->isFailed());
        $this->assertEquals(1, $webhookEvent->attempts);
        $this->assertNotNull($webhookEvent->error_message);
        $this->assertNotNull($webhookEvent->next_retry_at);
    }

    public function test_process_webhook_schedules_retry_on_failure(): void
    {
        // ARRANGE: Processor that throws exception
        Queue::fake();

        // ACT: Process webhook with failing processor
        $webhookEvent = $this->service->processWebhook(
            eventType: 'billing',
            source: 'rapidapi',
            payload: ['test' => 'data'],
            idempotencyKey: 'test-key-123',
            processor: fn (array $p) => throw new \RuntimeException('Processing failed'),
        );

        // ASSERT: Retry job was dispatched
        Queue::assertPushed(\App\Jobs\RetryWebhookJob::class, function ($job) use ($webhookEvent) {
            return $job->webhookEventId === $webhookEvent->id;
        });
    }

    public function test_retry_webhook_processes_failed_webhook(): void
    {
        // ARRANGE: Create failed webhook
        $webhookEvent = WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'rapidapi',
            'payload' => ['test' => 'data'],
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(), // Ready for retry
        ]);

        // ACT: Retry webhook
        $this->service->retryWebhook($webhookEvent, fn (array $p) => null);

        // ASSERT: Webhook processed successfully
        $webhookEvent->refresh();
        $this->assertTrue($webhookEvent->isProcessed());
        $this->assertEquals(1, $webhookEvent->attempts); // Attempts not incremented on success
    }

    public function test_get_webhooks_ready_for_retry_returns_failed_webhooks(): void
    {
        // ARRANGE: Create failed webhooks
        WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'rapidapi',
            'payload' => ['test' => 'data1'],
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);
        WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'rapidapi',
            'payload' => ['test' => 'data2'],
            'status' => 'failed',
            'attempts' => 2,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinutes(5),
        ]);
        WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'rapidapi',
            'payload' => ['test' => 'data3'],
            'status' => 'processed',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // ACT: Get webhooks ready for retry
        $webhooks = $this->service->getWebhooksReadyForRetry();

        // ASSERT: Only failed webhooks with past retry time are returned
        $this->assertCount(2, $webhooks);
        $this->assertTrue($webhooks->every(fn ($w) => $w->status === 'failed'));
        $this->assertTrue($webhooks->every(fn ($w) => $w->next_retry_at->isPast()));
    }

    public function test_get_permanently_failed_webhooks_returns_failed_webhooks(): void
    {
        // ARRANGE: Create permanently failed webhook
        WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'rapidapi',
            'payload' => ['test' => 'data'],
            'status' => 'permanently_failed',
            'attempts' => 3,
            'max_attempts' => 3,
            'failed_at' => now(),
        ]);

        // ACT: Get permanently failed webhooks
        $webhooks = $this->service->getPermanentlyFailedWebhooks();

        // ASSERT: Permanently failed webhook is returned
        $this->assertCount(1, $webhooks);
        $this->assertTrue($webhooks->first()->isPermanentlyFailed());
    }
}
