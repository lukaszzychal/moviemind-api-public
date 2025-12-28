# Webhook System Architecture

> **Related Task:** TASK-008  
> **Status:** Implemented  
> **Created:** 2025-01-27

---

## Overview

MovieMind API implements a robust webhook system for handling external events (billing, notifications, etc.) with automatic retry, error handling, and event tracking.

## Architecture

### Components

1. **WebhookEvent Model** - Stores all webhook events in database
2. **WebhookService** - Core webhook processing logic with retry support
3. **RetryWebhookJob** - Queue job for retrying failed webhooks
4. **Webhook Controllers** - Specific webhook handlers (BillingWebhookController, etc.)

### Flow

```
External Service → Webhook Endpoint → Store in DB → Process Event
                                              ↓
                                    Success → Mark as Processed
                                    Failure → Mark as Failed → Schedule Retry
                                                              ↓
                                                    RetryWebhookJob (exponential backoff)
```

## Database Schema

### webhook_events Table

```sql
CREATE TABLE webhook_events (
    id UUID PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,      -- billing, notification, etc.
    source VARCHAR(100) NOT NULL,          -- rapidapi, stripe, etc.
    payload JSON NOT NULL,                  -- Webhook payload data
    status ENUM(...) DEFAULT 'pending',     -- pending, processing, processed, failed, permanently_failed
    attempts TINYINT DEFAULT 0,            -- Number of processing attempts
    max_attempts TINYINT DEFAULT 3,        -- Maximum retry attempts
    idempotency_key VARCHAR(255) UNIQUE,   -- Prevent duplicate processing
    error_message TEXT,                     -- Error message if failed
    error_context JSON,                     -- Additional error context
    processed_at TIMESTAMP,                 -- When webhook was processed
    failed_at TIMESTAMP,                    -- When webhook failed
    next_retry_at TIMESTAMP,                -- Next retry attempt time
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Retry Strategy

### Exponential Backoff

- **Attempt 1:** 1 minute delay
- **Attempt 2:** 5 minutes delay
- **Attempt 3:** 15 minutes delay
- **Max Attempts:** 3 (configurable)

### Status Flow

```
pending → processing → processed ✅
                    → failed → (retry) → processed ✅
                                 → permanently_failed ❌
```

## Usage

### Processing a Webhook

```php
use App\Services\WebhookService;

$webhookService = app(WebhookService::class);

$webhookEvent = $webhookService->processWebhook(
    eventType: 'billing',
    source: 'rapidapi',
    payload: [
        'event' => 'subscription.created',
        'data' => [...],
    ],
    idempotencyKey: 'unique-key-123',
    processor: function (array $payload) {
        // Process webhook logic
        // Throw exception on failure
    },
    maxAttempts: 3
);
```

### Retrying Failed Webhooks

```php
// Get webhooks ready for retry
$webhooks = $webhookService->getWebhooksReadyForRetry(limit: 100);

foreach ($webhooks as $webhook) {
    $webhookService->retryWebhook($webhook);
}
```

### Manual Retry

```php
use App\Jobs\RetryWebhookJob;

// Dispatch retry job
RetryWebhookJob::dispatch($webhookEvent->id)
    ->delay($webhookEvent->next_retry_at);
```

## Integration with BillingWebhookController

The `BillingWebhookController` automatically:
1. Stores webhook events in database
2. Processes webhooks with retry support
3. Returns appropriate HTTP responses
4. Schedules retry jobs for failed webhooks

## Monitoring

### Get Failed Webhooks

```php
$failedWebhooks = $webhookService->getWebhooksReadyForRetry();
$permanentlyFailed = $webhookService->getPermanentlyFailedWebhooks();
```

### Check Webhook Status

```php
$webhook = WebhookEvent::find($id);

if ($webhook->isProcessed()) {
    // Success
} elseif ($webhook->isFailed()) {
    // Failed, will retry
} elseif ($webhook->isPermanentlyFailed()) {
    // Permanently failed, manual intervention needed
}
```

## Configuration

### Environment Variables

```env
# Webhook retry configuration (optional, defaults shown)
WEBHOOK_MAX_ATTEMPTS=3
WEBHOOK_RETRY_DELAY_1=1      # minutes
WEBHOOK_RETRY_DELAY_2=5      # minutes
WEBHOOK_RETRY_DELAY_3=15     # minutes
```

## Best Practices

1. **Always use idempotency keys** - Prevents duplicate processing
2. **Handle exceptions in processor** - Let WebhookService handle retry
3. **Monitor failed webhooks** - Set up alerts for permanently failed webhooks
4. **Clean up old webhooks** - Archive or delete processed webhooks periodically
5. **Test retry mechanism** - Ensure retry works correctly

## Examples

### Example 1: Simple Webhook Processing

```php
$webhookService->processWebhook(
    eventType: 'notification',
    source: 'internal',
    payload: ['message' => 'User registered'],
    idempotencyKey: 'user-reg-123',
    processor: function (array $payload) {
        // Send notification email
        Mail::send(...);
    }
);
```

### Example 2: Webhook with Validation

```php
$webhookService->processWebhook(
    eventType: 'billing',
    source: 'stripe',
    payload: $stripeEvent,
    idempotencyKey: $stripeEvent['id'],
    processor: function (array $payload) {
        // Validate payload
        if (!isset($payload['type'])) {
            throw new \InvalidArgumentException('Missing event type');
        }
        
        // Process based on type
        match ($payload['type']) {
            'payment.succeeded' => $this->handlePaymentSucceeded($payload),
            'payment.failed' => $this->handlePaymentFailed($payload),
            default => throw new \InvalidArgumentException("Unknown event type: {$payload['type']}"),
        };
    }
);
```

## Related Documentation

- [ADR-008: Webhook System Architecture](../../adr/008-webhook-system-architecture.md)
- [RapidAPI Webhooks Guide](../../../RAPIDAPI_WEBHOOKS.md)
- [QA Testing Guide](../../qa/WEBHOOK_SYSTEM_QA_GUIDE.md)
- [Manual Testing Guide](../../qa/WEBHOOK_SYSTEM_MANUAL_TESTING.md)
- [Business Documentation](../../business/WEBHOOK_SYSTEM_BUSINESS.md)

---

**Last updated:** 2025-01-27

