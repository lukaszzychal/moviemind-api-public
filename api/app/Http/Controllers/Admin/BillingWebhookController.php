<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\WebhookEvent;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling RapidAPI billing webhooks.
 *
 * Handles:
 * - subscription.created
 * - subscription.updated
 * - subscription.cancelled
 * - payment.succeeded
 * - payment.failed
 */
class BillingWebhookController
{
    public function __construct(
        private readonly BillingService $billingService
    ) {}

    /**
     * Handle incoming webhook from RapidAPI.
     *
     * Validates signature and routes to appropriate handler based on event type.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook signature
        if (! $this->validateSignature($request)) {
            Log::warning('Billing webhook rejected - invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Invalid signature',
            ], 401);
        }

        // Validate request structure
        $validator = Validator::make($request->all(), [
            'event' => 'required|string',
            'data' => 'present|array', // Allow empty array
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            Log::warning('Billing webhook rejected - invalid structure', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'error' => 'Invalid request structure',
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = $request->input('event');
        $data = $request->input('data');
        $idempotencyKey = $request->input('idempotency_key');

        Log::info('Billing webhook received', [
            'event' => $event,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Store webhook event for tracking and retry
        $webhookEvent = WebhookEvent::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'event_type' => 'billing',
                'source' => 'rapidapi',
                'payload' => [
                    'event' => $event,
                    'data' => $data,
                    'idempotency_key' => $idempotencyKey,
                ],
                'status' => 'pending',
                'max_attempts' => 3,
            ]
        );

        // If webhook was already processed successfully, return cached response
        if ($webhookEvent->isProcessed()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Webhook already processed',
                'webhook_id' => $webhookEvent->id,
            ]);
        }

        // If webhook failed but can be retried, retry it now
        if ($webhookEvent->isFailed() && $webhookEvent->canRetry() && $webhookEvent->shouldRetryNow()) {
            // Update payload in case it changed
            $webhookEvent->update([
                'payload' => [
                    'event' => $event,
                    'data' => $data,
                    'idempotency_key' => $idempotencyKey,
                ],
            ]);
        }

        // Process webhook with retry support
        try {
            $webhookEvent->markAsProcessing();

            // Route to appropriate handler
            $response = match ($event) {
                'subscription.created' => $this->handleSubscriptionCreated($data, $idempotencyKey),
                'subscription.updated' => $this->handleSubscriptionUpdated($data, $idempotencyKey),
                'subscription.cancelled' => $this->handleSubscriptionCancelled($data, $idempotencyKey),
                'payment.succeeded' => $this->handlePaymentSucceeded($data, $idempotencyKey),
                'payment.failed' => $this->handlePaymentFailed($data, $idempotencyKey),
                default => $this->handleUnknownEvent($event, $data),
            };

            // Mark as processed if response is successful
            if ($response->getStatusCode() < 400) {
                $webhookEvent->markAsProcessed();
            } else {
                throw new \RuntimeException('Webhook handler returned error status: '.$response->getStatusCode());
            }

            return $response;
        } catch (\Exception $e) {
            // Mark as failed and schedule retry
            $webhookEvent->markAsFailed($e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Schedule retry if possible
            if ($webhookEvent->canRetry() && $webhookEvent->next_retry_at !== null) {
                \App\Jobs\RetryWebhookJob::dispatch($webhookEvent->id)
                    ->delay($webhookEvent->next_retry_at);
            }

            Log::error('Billing webhook processing failed', [
                'webhook_id' => $webhookEvent->id,
                'event' => $event,
                'error' => $e->getMessage(),
                'attempts' => $webhookEvent->attempts,
            ]);

            return response()->json([
                'error' => 'Failed to process webhook',
                'message' => $e->getMessage(),
                'webhook_id' => $webhookEvent->id,
            ], 500);
        }
    }

    /**
     * Handle subscription.created event.
     */
    private function handleSubscriptionCreated(array $data, ?string $idempotencyKey): JsonResponse
    {
        $validator = Validator::make($data, [
            'rapidapi_user_id' => 'required|string',
            'plan' => 'required|string|in:basic,pro,ultra',
            'api_key_id' => 'nullable|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid subscription data',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mappedPlan = app(\App\Services\RapidApiService::class)->mapRapidApiPlan($data['plan']);
            if ($mappedPlan === null) {
                throw new \InvalidArgumentException("Unknown RapidAPI plan: {$data['plan']}");
            }

            $subscription = $this->billingService->createSubscription(
                $data['rapidapi_user_id'],
                $mappedPlan,
                $data['api_key_id'] ?? null,
                $idempotencyKey
            );

            return response()->json([
                'status' => 'success',
                'subscription_id' => $subscription->id,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create subscription from webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'error' => 'Failed to create subscription',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle subscription.updated event.
     */
    private function handleSubscriptionUpdated(array $data, ?string $idempotencyKey): JsonResponse
    {
        $validator = Validator::make($data, [
            'subscription_id' => 'required|uuid',
            'plan' => 'required|string|in:basic,pro,ultra',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid subscription data',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mappedPlan = app(\App\Services\RapidApiService::class)->mapRapidApiPlan($data['plan']);
            if ($mappedPlan === null) {
                throw new \InvalidArgumentException("Unknown plan: {$data['plan']}");
            }

            $subscription = $this->billingService->updateSubscription(
                $data['subscription_id'],
                $mappedPlan,
                $idempotencyKey
            );

            return response()->json([
                'status' => 'success',
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update subscription from webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'error' => 'Failed to update subscription',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle subscription.cancelled event.
     */
    private function handleSubscriptionCancelled(array $data, ?string $idempotencyKey): JsonResponse
    {
        $validator = Validator::make($data, [
            'subscription_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid subscription data',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $subscription = $this->billingService->cancelSubscription(
                $data['subscription_id'],
                $idempotencyKey
            );

            return response()->json([
                'status' => 'success',
                'subscription_id' => $subscription->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription from webhook', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'error' => 'Failed to cancel subscription',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle payment.succeeded event.
     */
    private function handlePaymentSucceeded(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could update subscription period, send notifications, etc.
        Log::info('Payment succeeded webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment event logged',
        ]);
    }

    /**
     * Handle payment.failed event.
     */
    private function handlePaymentFailed(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could mark subscription as expired, send notifications, etc.
        Log::warning('Payment failed webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment failure event logged',
        ]);
    }

    /**
     * Handle unknown event type.
     *
     * @param  string  $event  Event type
     * @param  array  $data  Event data (unused, but required for signature)
     */
    private function handleUnknownEvent(string $event, array $data = []): JsonResponse
    {
        Log::warning('Unknown billing webhook event', [
            'event' => $event,
            'data' => $data,
        ]);

        return response()->json([
            'status' => 'ignored',
            'message' => "Unknown event type: {$event}",
        ], 200);
    }

    /**
     * Validate webhook signature using HMAC.
     *
     * RapidAPI sends webhook signature in X-RapidAPI-Signature header.
     * Signature is HMAC-SHA256 of request body using webhook secret.
     *
     * @return bool True if signature is valid, false otherwise
     */
    private function validateSignature(Request $request): bool
    {
        $webhookSecret = config('rapidapi.webhook_secret');
        $verifyEnabled = config('rapidapi.verify_webhook_signature', true);

        // If verification is disabled, always return true
        if (! $verifyEnabled) {
            return true;
        }

        // If no secret is configured, verification fails
        if ($webhookSecret === null || $webhookSecret === '') {
            return false;
        }

        // Get signature from header
        $providedSignature = $request->header('X-RapidAPI-Signature');
        if ($providedSignature === null || $providedSignature === '') {
            return false;
        }

        // Calculate expected signature
        $requestBody = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $requestBody, $webhookSecret);

        // Use hash_equals for timing-safe comparison
        return hash_equals($expectedSignature, trim($providedSignature));
    }
}
