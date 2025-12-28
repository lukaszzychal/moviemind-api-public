<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\RetryWebhookJob;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling webhook events with retry and error handling.
 *
 * Responsibilities:
 * - Store webhook events
 * - Process webhook events
 * - Handle retry logic
 * - Track webhook status
 */
class WebhookService
{
    /**
     * Store and process a webhook event.
     *
     * @param  string  $eventType  Type of webhook (billing, notification, etc.)
     * @param  string  $source  Source of webhook (rapidapi, stripe, etc.)
     * @param  array  $payload  Webhook payload data
     * @param  string|null  $idempotencyKey  Idempotency key to prevent duplicates
     * @param  callable  $processor  Callback function to process the webhook
     * @param  int  $maxAttempts  Maximum retry attempts (default: 3)
     * @return WebhookEvent The stored webhook event
     */
    public function processWebhook(
        string $eventType,
        string $source,
        array $payload,
        ?string $idempotencyKey,
        callable $processor,
        int $maxAttempts = 3
    ): WebhookEvent {
        // Check for existing webhook with same idempotency key
        if ($idempotencyKey !== null) {
            $existing = WebhookEvent::where('idempotency_key', $idempotencyKey)->first();
            if ($existing !== null) {
                Log::info('Webhook skipped - duplicate idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'existing_webhook_id' => $existing->id,
                    'status' => $existing->status,
                ]);

                // If existing webhook failed and can be retried, retry it
                if ($existing->canRetry() && $existing->shouldRetryNow()) {
                    $this->retryWebhook($existing, $processor);
                }

                return $existing;
            }
        }

        // Create new webhook event
        $webhookEvent = WebhookEvent::create([
            'event_type' => $eventType,
            'source' => $source,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Process webhook
        $this->processWebhookEvent($webhookEvent, $processor);

        return $webhookEvent;
    }

    /**
     * Process a webhook event.
     *
     * @param  WebhookEvent  $webhookEvent  Webhook event to process
     * @param  callable  $processor  Callback function to process the webhook
     */
    public function processWebhookEvent(WebhookEvent $webhookEvent, callable $processor): void
    {
        $webhookEvent->markAsProcessing();

        try {
            // Execute processor callback
            $result = $processor($webhookEvent->payload);

            // Mark as processed
            $webhookEvent->markAsProcessed();

            Log::info('Webhook processed successfully', [
                'webhook_id' => $webhookEvent->id,
                'event_type' => $webhookEvent->event_type,
                'source' => $webhookEvent->source,
                'attempts' => $webhookEvent->attempts,
            ]);
        } catch (\Exception $e) {
            // Mark as failed
            $webhookEvent->markAsFailed(
                $e->getMessage(),
                [
                    'exception' => get_class($e),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            Log::error('Webhook processing failed', [
                'webhook_id' => $webhookEvent->id,
                'event_type' => $webhookEvent->event_type,
                'source' => $webhookEvent->source,
                'attempts' => $webhookEvent->attempts,
                'error' => $e->getMessage(),
            ]);

            // Schedule retry if possible
            if ($webhookEvent->canRetry() && $webhookEvent->next_retry_at !== null) {
                RetryWebhookJob::dispatch($webhookEvent->id)
                    ->delay($webhookEvent->next_retry_at);
            }
        }
    }

    /**
     * Retry a failed webhook event.
     *
     * @param  WebhookEvent  $webhookEvent  Webhook event to retry
     * @param  callable|null  $processor  Optional callback function to process the webhook
     */
    public function retryWebhook(WebhookEvent $webhookEvent, ?callable $processor = null): void
    {
        Log::info('Retrying webhook', [
            'webhook_id' => $webhookEvent->id,
            'event_type' => $webhookEvent->event_type,
            'attempts' => $webhookEvent->attempts,
            'max_attempts' => $webhookEvent->max_attempts,
        ]);

        // Use provided processor or get default processor for webhook type
        $processor = $processor ?? $this->getDefaultProcessor($webhookEvent);

        if ($processor === null) {
            throw new \RuntimeException("No processor available for webhook type: {$webhookEvent->event_type}");
        }

        $this->processWebhookEvent($webhookEvent, $processor);
    }

    /**
     * Get default processor for webhook type.
     *
     * @param  WebhookEvent  $webhookEvent  Webhook event
     * @return callable|null Processor function or null if not found
     */
    private function getDefaultProcessor(WebhookEvent $webhookEvent): ?callable
    {
        // For billing webhooks, return a processor that uses BillingService
        if ($webhookEvent->event_type === 'billing' && $webhookEvent->source === 'rapidapi') {
            return function (array $payload) {
                $event = $payload['event'] ?? null;
                $data = $payload['data'] ?? [];
                $idempotencyKey = $payload['idempotency_key'] ?? null;

                if ($event === null) {
                    throw new \InvalidArgumentException('Event type is required in webhook payload');
                }

                $billingService = app(BillingService::class);
                $rapidApiService = app(RapidApiService::class);

                // Process billing webhook events
                match ($event) {
                    'subscription.created' => $this->processSubscriptionCreated($billingService, $rapidApiService, $data, $idempotencyKey),
                    'subscription.updated' => $this->processSubscriptionUpdated($billingService, $rapidApiService, $data, $idempotencyKey),
                    'subscription.cancelled' => $this->processSubscriptionCancelled($billingService, $data, $idempotencyKey),
                    'payment.succeeded' => $this->processPaymentSucceeded($data, $idempotencyKey),
                    'payment.failed' => $this->processPaymentFailed($data, $idempotencyKey),
                    default => throw new \InvalidArgumentException("Unknown billing event type: {$event}"),
                };
            };
        }

        return null;
    }

    /**
     * Process subscription.created event.
     */
    private function processSubscriptionCreated(
        BillingService $billingService,
        RapidApiService $rapidApiService,
        array $data,
        ?string $idempotencyKey
    ): void {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'rapidapi_user_id' => 'required|string',
            'plan' => 'required|string|in:basic,pro,ultra',
            'api_key_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid subscription data: '.$validator->errors()->first());
        }

        $mappedPlan = $rapidApiService->mapRapidApiPlan($data['plan']);
        if ($mappedPlan === null) {
            throw new \InvalidArgumentException("Unknown RapidAPI plan: {$data['plan']}");
        }

        $billingService->createSubscription(
            $data['rapidapi_user_id'],
            $mappedPlan,
            $data['api_key_id'] ?? null,
            $idempotencyKey
        );
    }

    /**
     * Process subscription.updated event.
     */
    private function processSubscriptionUpdated(
        BillingService $billingService,
        RapidApiService $rapidApiService,
        array $data,
        ?string $idempotencyKey
    ): void {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'subscription_id' => 'required|uuid',
            'plan' => 'required|string|in:basic,pro,ultra',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid subscription data: '.$validator->errors()->first());
        }

        $mappedPlan = $rapidApiService->mapRapidApiPlan($data['plan']);
        if ($mappedPlan === null) {
            throw new \InvalidArgumentException("Unknown plan: {$data['plan']}");
        }

        $billingService->updateSubscription(
            $data['subscription_id'],
            $mappedPlan,
            $idempotencyKey
        );
    }

    /**
     * Process subscription.cancelled event.
     */
    private function processSubscriptionCancelled(
        BillingService $billingService,
        array $data,
        ?string $idempotencyKey
    ): void {
        $validator = \Illuminate\Support\Facades\Validator::make($data, [
            'subscription_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid subscription data: '.$validator->errors()->first());
        }

        $billingService->cancelSubscription(
            $data['subscription_id'],
            $idempotencyKey
        );
    }

    /**
     * Process payment.succeeded event.
     */
    private function processPaymentSucceeded(array $data, ?string $idempotencyKey): void
    {
        // For now, just log the event
        // In the future, this could update subscription period, send notifications, etc.
        Log::info('Payment succeeded webhook processed', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Process payment.failed event.
     */
    private function processPaymentFailed(array $data, ?string $idempotencyKey): void
    {
        // For now, just log the event
        // In the future, this could mark subscription as expired, send notifications, etc.
        Log::warning('Payment failed webhook processed', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    /**
     * Get webhook events that are ready for retry.
     *
     * @param  int  $limit  Maximum number of webhooks to retrieve
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWebhooksReadyForRetry(int $limit = 100)
    {
        return WebhookEvent::where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->whereColumn('attempts', '<', 'max_attempts')
            ->orderBy('next_retry_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get permanently failed webhooks.
     *
     * @param  int  $limit  Maximum number of webhooks to retrieve
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPermanentlyFailedWebhooks(int $limit = 100)
    {
        return WebhookEvent::where('status', 'permanently_failed')
            ->orderBy('failed_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
