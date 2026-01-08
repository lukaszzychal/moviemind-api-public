<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\OutgoingWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutgoingWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    public function test_can_create_outgoing_webhook(): void
    {
        // ACT: Create webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // ASSERT: Webhook created
        $this->assertInstanceOf(OutgoingWebhook::class, $webhook);
        $this->assertEquals('generation.completed', $webhook->event_type);
        $this->assertEquals('pending', $webhook->status);
    }

    public function test_is_pending_returns_true_for_pending_status(): void
    {
        // ARRANGE: Create pending webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
        ]);

        // ASSERT: Is pending
        $this->assertTrue($webhook->isPending());
    }

    public function test_is_sent_returns_true_for_sent_status(): void
    {
        // ARRANGE: Create sent webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // ASSERT: Is sent
        $this->assertTrue($webhook->isSent());
    }

    public function test_is_failed_returns_true_for_failed_status(): void
    {
        // ARRANGE: Create failed webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
        ]);

        // ASSERT: Is failed
        $this->assertTrue($webhook->isFailed());
    }

    public function test_is_permanently_failed_returns_true_for_permanently_failed_status(): void
    {
        // ARRANGE: Create permanently failed webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'permanently_failed',
        ]);

        // ASSERT: Is permanently failed
        $this->assertTrue($webhook->isPermanentlyFailed());
    }

    public function test_can_retry_returns_true_when_failed_and_attempts_less_than_max(): void
    {
        // ARRANGE: Create failed webhook with attempts < max
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        // ASSERT: Can retry
        $this->assertTrue($webhook->canRetry());
    }

    public function test_can_retry_returns_false_when_at_max_attempts(): void
    {
        // ARRANGE: Create failed webhook at max attempts
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        // ASSERT: Cannot retry
        $this->assertFalse($webhook->canRetry());
    }

    public function test_should_retry_now_returns_true_when_ready(): void
    {
        // ARRANGE: Create failed webhook ready for retry
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
        ]);

        // ASSERT: Should retry now
        $this->assertTrue($webhook->shouldRetryNow());
    }

    public function test_should_retry_now_returns_false_when_not_ready(): void
    {
        // ARRANGE: Create failed webhook not ready for retry
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->addMinute(),
        ]);

        // ASSERT: Should not retry now
        $this->assertFalse($webhook->shouldRetryNow());
    }

    public function test_mark_as_sent_updates_status_and_sent_at(): void
    {
        // ARRANGE: Create pending webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
        ]);

        // ACT: Mark as sent
        $webhook->markAsSent(200, ['status' => 'ok']);

        // ASSERT: Status and sent_at updated
        $this->assertEquals('sent', $webhook->status);
        $this->assertNotNull($webhook->sent_at);
        $this->assertEquals(200, $webhook->response_code);
        $this->assertEquals(['status' => 'ok'], $webhook->response_body);
    }

    public function test_mark_as_failed_updates_status_and_calculates_next_retry(): void
    {
        // ARRANGE: Create pending webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // ACT: Mark as failed
        $webhook->markAsFailed('Network error', 500, 'Server Error');

        // ASSERT: Status updated and next retry calculated
        $this->assertEquals('failed', $webhook->status);
        $this->assertEquals(1, $webhook->attempts);
        $this->assertNotNull($webhook->error_message);
        $this->assertNotNull($webhook->next_retry_at);
        $this->assertEquals(500, $webhook->response_code);
    }

    public function test_mark_as_failed_marks_as_permanently_failed_at_max_attempts(): void
    {
        // ARRANGE: Create webhook at max attempts
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 2,
            'max_attempts' => 3,
        ]);

        // ACT: Mark as failed (will reach max attempts)
        $webhook->markAsFailed('Network error', 500, 'Server Error');

        // ASSERT: Marked as permanently failed
        $this->assertEquals('permanently_failed', $webhook->status);
        $this->assertEquals(3, $webhook->attempts);
        $this->assertNull($webhook->next_retry_at);
    }

    public function test_calculate_next_retry_time_uses_exponential_backoff(): void
    {
        // ARRANGE: Create webhook with different attempt counts
        $webhook1 = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
        ]);

        $webhook2 = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => [],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 2,
            'max_attempts' => 3,
        ]);

        // ACT: Calculate next retry times
        $nextRetry1 = $webhook1->calculateNextRetryTime();
        $nextRetry2 = $webhook2->calculateNextRetryTime();

        // ASSERT: Exponential backoff (1min, 5min)
        $this->assertTrue($nextRetry1->isFuture());
        $this->assertTrue($nextRetry2->isFuture());
        $this->assertTrue(abs($nextRetry2->diffInMinutes($nextRetry1)) > 0);
    }
}
