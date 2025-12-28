<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for retrying failed webhook events.
 *
 * This job is dispatched when a webhook processing fails and needs to be retried.
 */
class RetryWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  string  $webhookEventId  ID of the webhook event to retry
     */
    public function __construct(
        private readonly string $webhookEventId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebhookService $webhookService): void
    {
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if ($webhookEvent === null) {
            Log::warning('RetryWebhookJob: Webhook event not found', [
                'webhook_event_id' => $this->webhookEventId,
            ]);

            return;
        }

        // Check if webhook can still be retried
        if (! $webhookEvent->canRetry()) {
            Log::info('RetryWebhookJob: Webhook cannot be retried', [
                'webhook_event_id' => $this->webhookEventId,
                'status' => $webhookEvent->status,
                'attempts' => $webhookEvent->attempts,
                'max_attempts' => $webhookEvent->max_attempts,
            ]);

            return;
        }

        // Check if it's time to retry
        if (! $webhookEvent->shouldRetryNow()) {
            Log::info('RetryWebhookJob: Webhook not ready for retry yet', [
                'webhook_event_id' => $this->webhookEventId,
                'next_retry_at' => $webhookEvent->next_retry_at,
            ]);

            // Reschedule for later
            if ($webhookEvent->next_retry_at !== null) {
                self::dispatch($this->webhookEventId)
                    ->delay($webhookEvent->next_retry_at);
            }

            return;
        }

        // Retry webhook (WebhookService will get default processor)
        $webhookService->retryWebhook($webhookEvent);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $webhookEvent = WebhookEvent::find($this->webhookEventId);

        if ($webhookEvent !== null) {
            Log::error('RetryWebhookJob failed', [
                'webhook_event_id' => $this->webhookEventId,
                'error' => $exception->getMessage(),
            ]);

            // Mark as permanently failed if max attempts reached
            if ($webhookEvent->attempts >= $webhookEvent->max_attempts) {
                $webhookEvent->update([
                    'status' => 'permanently_failed',
                    'failed_at' => now(),
                ]);
            }
        }
    }
}
