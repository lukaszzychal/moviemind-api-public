<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\OutgoingWebhook;
use App\Services\OutgoingWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for sending outgoing webhooks asynchronously.
 *
 * This job is dispatched when an event occurs and needs to notify external systems.
 */
class SendOutgoingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  string  $webhookId  ID of the outgoing webhook to send
     */
    public function __construct(
        private readonly string $webhookId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(OutgoingWebhookService $webhookService): void
    {
        $webhook = OutgoingWebhook::find($this->webhookId);

        if ($webhook === null) {
            Log::warning('SendOutgoingWebhookJob: Webhook not found', [
                'webhook_id' => $this->webhookId,
            ]);

            return;
        }

        // If webhook is already sent, skip
        if ($webhook->isSent()) {
            Log::info('SendOutgoingWebhookJob: Webhook already sent', [
                'webhook_id' => $this->webhookId,
            ]);

            return;
        }

        // If webhook is permanently failed, skip
        if ($webhook->isPermanentlyFailed()) {
            Log::info('SendOutgoingWebhookJob: Webhook permanently failed', [
                'webhook_id' => $this->webhookId,
                'status' => $webhook->status,
                'attempts' => $webhook->attempts,
                'max_attempts' => $webhook->max_attempts,
            ]);

            return;
        }

        // If webhook is failed but not ready for retry yet, skip
        if ($webhook->isFailed() && ! $webhook->shouldRetryNow()) {
            Log::info('SendOutgoingWebhookJob: Webhook not ready for retry yet', [
                'webhook_id' => $this->webhookId,
                'next_retry_at' => $webhook->next_retry_at,
            ]);

            return;
        }

        // Send or retry webhook
        if ($webhook->isPending()) {
            // First attempt - send webhook using existing webhook record
            $webhookService->sendWebhook(
                $webhook->event_type,
                $webhook->payload,
                $webhook->url,
                $webhook // Pass existing webhook to update instead of creating new
            );
        } else {
            // Retry failed webhook
            $webhookService->retryWebhook($webhook);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $webhook = OutgoingWebhook::find($this->webhookId);

        if ($webhook !== null) {
            Log::error('SendOutgoingWebhookJob failed', [
                'webhook_id' => $this->webhookId,
                'error' => $exception->getMessage(),
            ]);

            // Mark as failed if not already
            if ($webhook->isPending()) {
                $webhook->markAsFailed(
                    $exception->getMessage(),
                    null,
                    null
                );
            }
        }
    }
}
