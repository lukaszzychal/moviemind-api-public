<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * WebhookEvent model for tracking and retrying webhook events.
 *
 * @property string $id
 * @property string $event_type
 * @property string $source
 * @property array $payload
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $idempotency_key
 * @property string|null $error_message
 * @property array|null $error_context
 * @property \Carbon\Carbon|null $processed_at
 * @property \Carbon\Carbon|null $failed_at
 * @property \Carbon\Carbon|null $next_retry_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'event_type',
        'source',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'idempotency_key',
        'error_message',
        'error_context',
        'processed_at',
        'failed_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'error_context' => 'array',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    /**
     * Check if webhook is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if webhook is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if webhook is processed.
     */
    public function isProcessed(): bool
    {
        return $this->status === 'processed';
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
     * Mark webhook as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
        ]);
    }

    /**
     * Mark webhook as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'next_retry_at' => null,
        ]);
    }

    /**
     * Mark webhook as failed.
     */
    public function markAsFailed(string $errorMessage, ?array $errorContext = null): void
    {
        $this->increment('attempts');
        $shouldRetry = $this->attempts < $this->max_attempts;

        $this->update([
            'status' => $shouldRetry ? 'failed' : 'permanently_failed',
            'error_message' => $errorMessage,
            'error_context' => $errorContext,
            'failed_at' => now(),
            'next_retry_at' => $shouldRetry ? $this->calculateNextRetryTime() : null,
        ]);
    }

    /**
     * Calculate next retry time using exponential backoff.
     */
    private function calculateNextRetryTime(): \Carbon\Carbon
    {
        // Exponential backoff: 1min, 5min, 15min
        $backoffMinutes = match ($this->attempts) {
            1 => 1,
            2 => 5,
            3 => 15,
            default => 15,
        };

        return now()->addMinutes($backoffMinutes);
    }
}
