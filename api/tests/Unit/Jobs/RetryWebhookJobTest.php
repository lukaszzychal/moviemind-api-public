<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\RetryWebhookJob;
use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RetryWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // Prevent infinite recursion from self::dispatch() in RetryWebhookJob
        $this->artisan('migrate');
    }

    public function test_retry_webhook_job_processes_webhook(): void
    {
        // ARRANGE: Create failed webhook ready for retry
        $webhookEvent = WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'test',
            'payload' => [
                'event' => 'subscription.created',
                'data' => ['plan' => 'free'],
            ],
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);

        // WebhookService has no default processor for 'billing' - mock retryWebhook to simulate success
        $webhookService = $this->createMock(WebhookService::class);
        $webhookService->method('retryWebhook')->willReturnCallback(function (WebhookEvent $event) {
            $event->update(['status' => 'processed']);
        });

        // ACT: Dispatch and handle retry job
        $job = new RetryWebhookJob($webhookEvent->id);
        $job->handle($webhookService);

        // ASSERT: Webhook should be processed
        $webhookEvent->refresh();
        $this->assertSame('processed', $webhookEvent->status);
    }

    public function test_retry_webhook_job_skips_if_webhook_not_found(): void
    {
        // ARRANGE: Non-existent webhook ID
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // ACT: Dispatch and handle retry job
        $job = new RetryWebhookJob($nonExistentId);
        $job->handle(app(WebhookService::class));

        // ASSERT: No exception thrown, job completes silently
        $this->assertTrue(true);
    }

    public function test_retry_webhook_job_skips_if_cannot_retry(): void
    {
        // ARRANGE: Permanently failed webhook
        $webhookEvent = WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'permanently_failed',
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        // ACT: Dispatch and handle retry job
        $job = new RetryWebhookJob($webhookEvent->id);
        $job->handle(app(WebhookService::class));

        // ASSERT: Webhook status unchanged
        $webhookEvent->refresh();
        $this->assertTrue($webhookEvent->isPermanentlyFailed());
    }

    public function test_retry_webhook_job_reschedules_if_not_ready(): void
    {
        // ARRANGE: Failed webhook not ready for retry yet
        $webhookEvent = WebhookEvent::create([
            'event_type' => 'billing',
            'source' => 'test',
            'payload' => ['test' => 'data'],
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->addMinutes(5), // Not ready yet
        ]);

        // ACT: Dispatch and handle retry job
        $job = new RetryWebhookJob($webhookEvent->id);
        $job->handle(app(WebhookService::class));

        // ASSERT: Job rescheduled for later
        Queue::assertPushed(RetryWebhookJob::class);
    }
}
