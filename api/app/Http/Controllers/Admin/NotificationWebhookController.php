<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\WebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Controller for handling notification webhooks from external systems.
 *
 * Handles:
 * - generation.completed - External system notifies about completed generation
 * - generation.failed - External system notifies about failed generation
 * - user.registered - User registration notification
 * - user.updated - User profile update
 */
class NotificationWebhookController
{
    /**
     * Handle incoming notification webhook from external system.
     *
     * Validates signature and routes to appropriate handler based on event type.
     */
    public function handle(Request $request): JsonResponse
    {
        // Validate webhook signature
        if (! $this->validateSignature($request)) {
            Log::warning('Notification webhook rejected - invalid signature', [
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
            Log::warning('Notification webhook rejected - invalid structure', [
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

        Log::info('Notification webhook received', [
            'event' => $event,
            'idempotency_key' => $idempotencyKey,
        ]);

        // Store webhook event for tracking and retry
        $webhookEvent = WebhookEvent::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'event_type' => 'notification',
                'source' => 'external',
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
                'generation.completed' => $this->handleGenerationCompleted($data, $idempotencyKey),
                'generation.failed' => $this->handleGenerationFailed($data, $idempotencyKey),
                'user.registered' => $this->handleUserRegistered($data, $idempotencyKey),
                'user.updated' => $this->handleUserUpdated($data, $idempotencyKey),
                default => $this->handleUnknownEvent($event, $data),
            };

            // Mark as processed if response is successful
            if ($response->getStatusCode() < 400) {
                $webhookEvent->markAsProcessed();

                // Add webhook_id to response if not already present
                $responseData = $response->getData(true);
                if (! isset($responseData['webhook_id'])) {
                    $responseData['webhook_id'] = $webhookEvent->id;

                    return response()->json($responseData, $response->getStatusCode());
                }
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

            Log::error('Notification webhook processing failed', [
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
     * Handle generation.completed event.
     */
    private function handleGenerationCompleted(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could update job status, send notifications, etc.
        Log::info('Generation completed webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Generation completed event logged',
        ]);
    }

    /**
     * Handle generation.failed event.
     */
    private function handleGenerationFailed(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could update job status, send alerts, etc.
        Log::warning('Generation failed webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Generation failed event logged',
        ]);
    }

    /**
     * Handle user.registered event.
     */
    private function handleUserRegistered(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could create user account, send welcome email, etc.
        Log::info('User registered webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User registered event logged',
        ]);
    }

    /**
     * Handle user.updated event.
     */
    private function handleUserUpdated(array $data, ?string $idempotencyKey): JsonResponse
    {
        // For now, just log the event
        // In the future, this could update user profile, sync data, etc.
        Log::info('User updated webhook received', [
            'data' => $data,
            'idempotency_key' => $idempotencyKey,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'User updated event logged',
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
        Log::warning('Unknown notification webhook event', [
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
     * External systems send webhook signature in X-Notification-Webhook-Signature header.
     * Signature is HMAC-SHA256 of request body using webhook secret.
     *
     * @return bool True if signature is valid, false otherwise
     */
    private function validateSignature(Request $request): bool
    {
        $webhookSecret = config('webhooks.notification_secret');
        $verifyEnabled = config('webhooks.verify_notification_signature', true);

        // If verification is disabled, always return true
        if (! $verifyEnabled) {
            return true;
        }

        // If no secret is configured, verification fails
        if ($webhookSecret === null || $webhookSecret === '') {
            return false;
        }

        // Get signature from header
        $providedSignature = $request->header('X-Notification-Webhook-Signature');
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
