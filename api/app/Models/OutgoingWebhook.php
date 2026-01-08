<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * OutgoingWebhook model for tracking outgoing webhook delivery attempts.
 *
 * @property string $id
 * @property string $event_type
 * @property array $payload
 * @property string $url
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property int|null $response_code
 * @property array|null $response_body
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $sent_at
 * @property \Carbon\Carbon|null $next_retry_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class OutgoingWebhook extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_type',
        'payload',
        'url',
        'status',
        'attempts',
        'max_attempts',
        'response_code',
        'response_body',
        'error_message',
        'sent_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_body' => 'array',
        'sent_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'response_code' => 'integer',
    ];

    /**
     * Check if webhook is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if webhook is sent.
     */
    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    /**
     * Check if webhook failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if webhook is permanently failed.
     */
    public function isPermanentlyFailed(): bool
    {
        return $this->status === 'permanently_failed';
    }

    /**
     * Check if webhook can be retried.
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->attempts < $this->max_attempts;
    }

    /**
     * Check if webhook should be retried now.
     */
    public function shouldRetryNow(): bool
    {
        return $this->canRetry()
            && $this->next_retry_at !== null
            && $this->next_retry_at->isPast();
    }

    /**
     * Mark webhook as sent.
     */
    public function markAsSent(int $responseCode, ?array $responseBody = null): void
    {
        $this->increment('attempts');
        $this->refresh(); // Refresh model to get updated attempts value

        $this->update([
            'status' => 'sent',
            'response_code' => $responseCode,
            'response_body' => $responseBody,
            'sent_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $errorMessage, ?int $responseCode = null, ?string $responseBody = null): void
    {
        $this->increment('attempts');
        $this->refresh(); // Refresh model to get updated attempts value
        $shouldRetry = $this->attempts < $this->max_attempts;

        // Parse response body if it's a JSON string
        $parsedResponseBody = null;
        if ($responseBody !== null) {
            $decoded = json_decode($responseBody, true);
            $parsedResponseBody = json_last_error() === JSON_ERROR_NONE ? $decoded : ['raw' => $responseBody];
        }

        $this->update([
            'status' => $shouldRetry ? 'failed' : 'permanently_failed',
            'error_message' => $errorMessage,
            'response_code' => $responseCode,
            'response_body' => $parsedResponseBody,
            'next_retry_at' => $shouldRetry ? $this->calculateNextRetryTime() : null,
        ]);
    }

    /**
     * Calculate next retry time using exponential backoff.
     */
    public function calculateNextRetryTime(): \Carbon\Carbon
    {
        $retryDelays = config('webhooks.outgoing_retry_delays', [
            1 => 1,
            2 => 5,
            3 => 15,
        ]);

        $backoffMinutes = $retryDelays[$this->attempts] ?? 15;

        return now()->addMinutes($backoffMinutes);
    }
}
