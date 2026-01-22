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
    source VARCHAR(100) NOT NULL,          -- stripe, paypal, etc.
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
    source: 'stripe', // or 'paypal', etc.
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

## Notification Webhooks

### Overview

Notification webhooks support both **incoming** (external systems sending webhooks to MovieMind API) and **outgoing** (MovieMind API sending webhooks to external systems) webhooks.

### Incoming Notification Webhooks

External systems can send notification webhooks to MovieMind API at `POST /api/v1/webhooks/notification`.

#### Supported Event Types

- `generation.completed` - External system notifies about completed generation
- `generation.failed` - External system notifies about failed generation
- `user.registered` - User registration notification
- `user.updated` - User profile update

#### Request Format

```json
{
  "event": "generation.completed",
  "data": {
    "entity_type": "MOVIE",
    "entity_id": "the-matrix-1999",
    "job_id": "550e8400-e29b-41d4-a716-446655440000"
  },
  "idempotency_key": "unique-key-123"
}
```

#### Signature Verification

Notification webhooks support HMAC-SHA256 signature verification via `X-Notification-Webhook-Signature` header:

```php
$signature = hash_hmac('sha256', $requestBody, $secret);
```

#### Configuration

```env
NOTIFICATION_WEBHOOK_SECRET=your-secret-key
WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true
```

### Outgoing Notification Webhooks

MovieMind API can send webhooks to external systems when events occur (e.g., generation completed, generation failed).

#### Components

1. **OutgoingWebhook Model** - Stores outgoing webhook delivery attempts
2. **OutgoingWebhookService** - Handles webhook delivery with retry support
3. **SendOutgoingWebhookJob** - Queue job for async webhook delivery
4. **SendOutgoingWebhookListener** - Listens to generation events and dispatches webhooks

#### Supported Event Types

- `movie.generation.requested` - Movie generation requested
- `person.generation.requested` - Person generation requested
- `movie.generation.completed` - Movie generation completed (future)
- `person.generation.completed` - Person generation completed (future)
- `movie.generation.failed` - Movie generation failed (future)
- `person.generation.failed` - Person generation failed (future)

#### Configuration

```env
# Webhook URLs per event type
WEBHOOK_URL_MOVIE_GENERATION_COMPLETED=https://example.com/webhook
WEBHOOK_URL_PERSON_GENERATION_COMPLETED=https://example.com/webhook

# Outgoing webhook secret (for signing)
OUTGOING_WEBHOOK_SECRET=your-secret-key

# Retry configuration
WEBHOOK_OUTGOING_MAX_ATTEMPTS=3
WEBHOOK_RETRY_DELAY_1=1
WEBHOOK_RETRY_DELAY_2=5
WEBHOOK_RETRY_DELAY_3=15
```

#### Webhook Payload Format

```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "locale": "en-US",
  "context_tag": "modern"
}
```

#### Signature Header

Outgoing webhooks are signed with HMAC-SHA256 and sent in `X-MovieMind-Webhook-Signature` header:

```php
$signature = hash_hmac('sha256', json_encode($payload), $secret);
```

#### Database Schema

```sql
CREATE TABLE outgoing_webhooks (
    id UUID PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    url VARCHAR(500) NOT NULL,
    status ENUM('pending', 'sent', 'failed', 'permanently_failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    max_attempts TINYINT DEFAULT 3,
    response_code SMALLINT,
    response_body JSON,
    error_message TEXT,
    sent_at TIMESTAMP,
    next_retry_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Usage Example

```php
use App\Services\OutgoingWebhookService;

$webhookService = app(OutgoingWebhookService::class);

$webhook = $webhookService->sendWebhook(
    eventType: 'movie.generation.completed',
    payload: [
        'entity_type' => 'MOVIE',
        'slug' => 'the-matrix-1999',
        'job_id' => '550e8400-e29b-41d4-a716-446655440000',
    ],
    url: 'https://example.com/webhook'
);

// Check status
if ($webhook->isSent()) {
    // Success
} elseif ($webhook->isFailed()) {
    // Failed, will retry automatically
}
```

#### Retry Mechanism

Outgoing webhooks use the same exponential backoff strategy as incoming webhooks:
- **Attempt 1:** 1 minute delay
- **Attempt 2:** 5 minutes delay
- **Attempt 3:** 15 minutes delay

Failed webhooks are automatically retried via `SendOutgoingWebhookJob`.

### Feature Flag

Notification webhooks are controlled by the `webhook_notifications` feature flag:

```php
use Laravel\Pennant\Feature;

if (Feature::active('webhook_notifications')) {
    // Webhook functionality enabled
}
```

## Related Documentation

- [ADR-008: Webhook System Architecture](../../adr/008-webhook-system-architecture.md)
- [Billing Webhooks Guide](../../business/SUBSCRIPTION_SYSTEM.md)
- [QA Testing Guide](../../qa/WEBHOOK_SYSTEM_QA_GUIDE.md)
- [Manual Testing Guide](../../qa/WEBHOOK_SYSTEM_MANUAL_TESTING.md)
- [Business Documentation](../../business/WEBHOOK_SYSTEM_BUSINESS.md)
- [Notification Webhooks Guide](./NOTIFICATION_WEBHOOKS.md)

---

**Last updated:** 2026-01-07

