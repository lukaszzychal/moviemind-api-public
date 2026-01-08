<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SendOutgoingWebhookJob;
use App\Models\OutgoingWebhook;
use App\Services\OutgoingWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendOutgoingWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        Http::preventStrayRequests();
    }

    public function test_job_sends_webhook_successfully(): void
    {
        // ARRANGE: Create pending webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // Mock HTTP client - use wildcard to catch all requests
        Http::fake([
            '*' => Http::response(['status' => 'ok'], 200),
        ]);

        // ACT: Execute job
        $job = new SendOutgoingWebhookJob($webhook->id);
        $job->handle(app(OutgoingWebhookService::class));

        // ASSERT: Webhook marked as sent
        $webhook->refresh();
        $this->assertEquals('sent', $webhook->status);
        $this->assertNotNull($webhook->sent_at);
    }

    public function test_job_handles_failed_webhook(): void
    {
        // Mock HTTP client to return error - BEFORE creating webhook
        Http::fake([
            'https://example.com/webhook' => Http::response(['error' => 'Server Error'], 500),
        ]);

        // ARRANGE: Create pending webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
        ]);

        // ACT: Execute job
        $job = new SendOutgoingWebhookJob($webhook->id);
        $job->handle(app(OutgoingWebhookService::class));

        // ASSERT: Webhook marked as failed
        $webhook->refresh();
        $this->assertEquals('failed', $webhook->status);
        $this->assertNotNull($webhook->error_message);
        $this->assertNotNull($webhook->next_retry_at);
    }

    public function test_job_skips_if_webhook_not_found(): void
    {
        // ARRANGE: Non-existent webhook ID
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // ACT: Execute job
        $job = new SendOutgoingWebhookJob($nonExistentId);
        $job->handle(app(OutgoingWebhookService::class));

        // ASSERT: No exception thrown (job handles gracefully)
        $this->assertTrue(true);
    }

    public function test_job_skips_if_webhook_cannot_retry(): void
    {
        // ARRANGE: Permanently failed webhook
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'permanently_failed',
            'attempts' => 3,
            'max_attempts' => 3,
        ]);

        // ACT: Execute job
        $job = new SendOutgoingWebhookJob($webhook->id);
        $job->handle(app(OutgoingWebhookService::class));

        // ASSERT: Webhook status unchanged
        $webhook->refresh();
        $this->assertEquals('permanently_failed', $webhook->status);
    }

    public function test_job_reschedules_if_not_ready_for_retry(): void
    {
        // ARRANGE: Failed webhook not ready for retry yet
        $webhook = OutgoingWebhook::create([
            'event_type' => 'generation.completed',
            'payload' => ['entity_type' => 'MOVIE'],
            'url' => 'https://example.com/webhook',
            'status' => 'failed',
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->addMinute(), // Not ready yet
        ]);

        // ACT: Execute job
        $job = new SendOutgoingWebhookJob($webhook->id);
        $job->handle(app(OutgoingWebhookService::class));

        // ASSERT: Webhook status unchanged (not retried yet)
        $webhook->refresh();
        $this->assertEquals('failed', $webhook->status);
        $this->assertEquals(1, $webhook->attempts);
    }
}
