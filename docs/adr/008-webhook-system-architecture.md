# ADR-008: Webhook System Architecture

**Status:** Accepted  
**Date:** 2025-01-27  
**Related Task:** TASK-008

---

## Context

MovieMind API needs a robust webhook system for handling external events (billing, notifications, etc.). Currently, billing webhooks are implemented (TASK-RAPI-005), but there's no general webhook infrastructure for:
- Retry mechanism for failed webhooks
- Error handling and logging
- Webhook event tracking
- General webhook architecture

## Decision

Implement a general webhook system that:
1. **Stores webhook events** in database for tracking and retry
2. **Provides retry mechanism** for failed webhook processing
3. **Handles errors gracefully** with proper logging
4. **Supports multiple webhook types** (billing, notifications, etc.)
5. **Maintains idempotency** to prevent duplicate processing

## Architecture

### Components

1. **WebhookEvent Model** - Stores all webhook events
   - `event_type` (billing, notification, etc.)
   - `payload` (JSON)
   - `status` (pending, processed, failed)
   - `attempts` (retry count)
   - `idempotency_key` (prevent duplicates)
   - `processed_at`, `failed_at`

2. **WebhookService** - Core webhook processing logic
   - `processWebhook()` - Process webhook event
   - `retryFailedWebhook()` - Retry failed webhook
   - `markAsProcessed()` - Mark webhook as processed
   - `markAsFailed()` - Mark webhook as failed

3. **RetryWebhookJob** - Queue job for retrying failed webhooks
   - Automatic retry with exponential backoff
   - Max retry attempts (configurable)
   - Dead letter queue for permanently failed webhooks

4. **Webhook Controllers** - Specific webhook handlers
   - `BillingWebhookController` - Already exists, will use WebhookService
   - Future: `NotificationWebhookController`, etc.

### Flow

```
External Service → Webhook Endpoint → WebhookService → Process Event
                                              ↓
                                    Success → Mark as Processed
                                    Failure → Store in DB → RetryWebhookJob
```

### Retry Strategy

- **Max Attempts:** 3 (configurable)
- **Backoff:** Exponential (1min, 5min, 15min)
- **Dead Letter:** After max attempts, mark as permanently failed

## Consequences

### Positive
- ✅ Centralized webhook processing
- ✅ Automatic retry for transient failures
- ✅ Full audit trail of webhook events
- ✅ Idempotency guaranteed
- ✅ Extensible for future webhook types

### Negative
- ⚠️ Additional database table (webhook_events)
- ⚠️ Additional queue jobs for retry
- ⚠️ Slightly more complex than direct processing

### Risks
- Database growth (webhook_events table) - mitigated by cleanup job
- Queue load from retries - mitigated by rate limiting

## Implementation Plan

1. Create `WebhookEvent` model and migration
2. Create `WebhookService` with retry logic
3. Create `RetryWebhookJob` for async retry
4. Update `BillingWebhookController` to use `WebhookService`
5. Add tests for retry mechanism
6. Add documentation

---

**Related:**
- TASK-008
- TASK-RAPI-005 (Billing Webhooks)

