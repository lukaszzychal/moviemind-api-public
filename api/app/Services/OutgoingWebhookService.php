<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutgoingWebhook;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending webhooks to external systems.
 *
 * Responsibilities:
 * - Send HTTP POST requests to configured webhook URLs
 * - Handle retries with exponential backoff
 * - Store outgoing webhook attempts in database
 * - Support webhook signing (HMAC-SHA256)
 */
class OutgoingWebhookService
{
    /**
     * Send a webhook to an external system.
     *
     * @param  string  $eventType  Type of event (e.g., 'generation.completed')
     * @param  array  $payload  Webhook payload data
     * @param  string  $url  Webhook URL to send to
     * @param  OutgoingWebhook|null  $existingWebhook  Optional existing webhook to update instead of creating new
     * @return OutgoingWebhook The created or updated outgoing webhook record
     */
    public function sendWebhook(string $eventType, array $payload, string $url, ?OutgoingWebhook $existingWebhook = null): OutgoingWebhook
    {
        if ($existingWebhook !== null) {
            $webhook = $existingWebhook;
            $requestPayload = $webhook->payload ?? [];
        } else {
            $requestPayload = $this->payloadWithEventInfo($payload, $eventType);
            $webhook = OutgoingWebhook::create([
                'event_type' => $eventType,
                'payload' => $requestPayload,
                'url' => $url,
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => config('webhooks.outgoing_max_attempts', 3),
            ]);
        }

        try {
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'MovieMind-API/1.0',
            ]);

            if (! app()->environment('testing')) {
                $request = $request->timeout(30);
            }

            $secret = config('webhooks.outgoing_secret');
            $body = json_encode($requestPayload);
            if ($secret !== null && $secret !== '') {
                $signature = hash_hmac('sha256', $body, $secret);
                $request = $request->withHeader(config('webhooks.outgoing_signature_header', 'X-MovieMind-Webhook-Signature'), $signature);
            }

            $response = $request->post($url, $requestPayload);

            // Check for connection/timeout errors
            if ($response->clientError() || $response->serverError()) {
                // HTTP error (4xx, 5xx)
                $webhook->markAsFailed(
                    "HTTP {$response->status()}: {$response->body()}",
                    $response->status(),
                    $response->body()
                );

                Log::warning('Outgoing webhook failed with HTTP error', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $eventType,
                    'url' => $url,
                    'response_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            } elseif ($response->successful()) {
                // Success (2xx)
                $responseBody = $response->json();
                $webhook->markAsSent(
                    $response->status(),
                    is_array($responseBody) ? $responseBody : null
                );

                Log::info('Outgoing webhook sent successfully', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $eventType,
                    'url' => $url,
                    'response_code' => $response->status(),
                ]);
            } else {
                // Unknown status - treat as failure
                $webhook->markAsFailed(
                    "Unknown HTTP status: {$response->status()}",
                    $response->status(),
                    $response->body()
                );

                Log::warning('Outgoing webhook failed with unknown status', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $eventType,
                    'url' => $url,
                    'response_code' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            $webhook->markAsFailed(
                $e->getMessage(),
                null,
                null
            );

            Log::error('Outgoing webhook exception', [
                'webhook_id' => $webhook->id,
                'event_type' => $eventType,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return $webhook;
    }

    /**
     * Retry a failed webhook.
     *
     * @param  OutgoingWebhook  $webhook  Webhook to retry
     * @return OutgoingWebhook The updated webhook
     */
    public function retryWebhook(OutgoingWebhook $webhook): OutgoingWebhook
    {
        if (! $webhook->canRetry()) {
            Log::warning('Cannot retry webhook - max attempts reached or not failed', [
                'webhook_id' => $webhook->id,
                'status' => $webhook->status,
                'attempts' => $webhook->attempts,
                'max_attempts' => $webhook->max_attempts,
            ]);

            return $webhook;
        }

        if (! $webhook->shouldRetryNow()) {
            Log::info('Webhook not ready for retry yet', [
                'webhook_id' => $webhook->id,
                'next_retry_at' => $webhook->next_retry_at,
            ]);

            return $webhook;
        }

        Log::info('Retrying outgoing webhook', [
            'webhook_id' => $webhook->id,
            'event_type' => $webhook->event_type,
            'attempts' => $webhook->attempts,
            'max_attempts' => $webhook->max_attempts,
        ]);

        try {
            // Prepare request
            $request = Http::withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'MovieMind-API/1.0',
            ]);

            // Add timeout only in non-test environment
            if (! app()->environment('testing')) {
                $request = $request->timeout(30);
            }

            // Sign request if secret is configured
            $secret = config('webhooks.outgoing_secret');
            $body = json_encode($webhook->payload);
            if ($secret !== null && $secret !== '') {
                $signature = hash_hmac('sha256', $body, $secret);
                $request = $request->withHeader(config('webhooks.outgoing_signature_header', 'X-MovieMind-Webhook-Signature'), $signature);
            }

            // Send webhook
            $response = $request->post($webhook->url, $webhook->payload);

            // Check for connection/timeout errors
            if ($response->clientError() || $response->serverError()) {
                // HTTP error (4xx, 5xx)
                $webhook->markAsFailed(
                    "HTTP {$response->status()}: {$response->body()}",
                    $response->status(),
                    $response->body()
                );

                Log::warning('Outgoing webhook retry failed with HTTP error', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $webhook->event_type,
                    'attempts' => $webhook->attempts,
                    'response_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);
            } elseif ($response->successful()) {
                // Success (2xx)
                $responseBody = $response->json();
                $webhook->markAsSent(
                    $response->status(),
                    is_array($responseBody) ? $responseBody : null
                );

                Log::info('Outgoing webhook retry succeeded', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $webhook->event_type,
                    'attempts' => $webhook->attempts,
                ]);
            } else {
                // Unknown status - treat as failure
                $webhook->markAsFailed(
                    "Unknown HTTP status: {$response->status()}",
                    $response->status(),
                    $response->body()
                );

                Log::warning('Outgoing webhook retry failed with unknown status', [
                    'webhook_id' => $webhook->id,
                    'event_type' => $webhook->event_type,
                    'attempts' => $webhook->attempts,
                    'response_code' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            $webhook->markAsFailed(
                $e->getMessage(),
                null,
                null
            );

            Log::error('Outgoing webhook retry exception', [
                'webhook_id' => $webhook->id,
                'event_type' => $webhook->event_type,
                'error' => $e->getMessage(),
            ]);
        }

        return $webhook;
    }

    /**
     * Get webhooks that are ready for retry.
     *
     * @param  int  $limit  Maximum number of webhooks to retrieve
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWebhooksReadyForRetry(int $limit = 100)
    {
        return OutgoingWebhook::where('status', 'failed')
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
        return OutgoingWebhook::where('status', 'permanently_failed')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Merge payload with event identifier so the receiver can see webhook type (requested, completed, failed).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function payloadWithEventInfo(array $payload, string $eventType): array
    {
        $parts = explode('.', $eventType);
        $eventKind = (string) end($parts);

        return array_merge($payload, [
            'event' => $eventType,
            'event_kind' => $eventKind,
        ]);
    }
}
