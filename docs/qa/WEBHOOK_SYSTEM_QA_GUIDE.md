# Webhook System - QA Testing Guide

> **For:** QA Engineers, Testers  
> **Related Task:** TASK-008  
> **Last Updated:** 2025-01-27

---

## 📋 Overview

This guide provides comprehensive testing scenarios and manual testing procedures for the Webhook System in MovieMind API.

## 🎯 What to Test

### Core Functionality
- ✅ Webhook event storage
- ✅ Webhook processing
- ✅ Retry mechanism
- ✅ Idempotency (duplicate prevention)
- ✅ Error handling
- ✅ Status tracking

## 🧪 Test Scenarios

### Scenario 1: Successful Webhook Processing

**Objective:** Verify webhook is processed successfully and stored in database.

**Steps:**
1. Send webhook request to `POST /api/v1/webhooks/billing`
2. Verify response is `201 Created` or `200 OK`
3. Check database `webhook_events` table:
   - Status = `processed`
   - `processed_at` is set
   - `attempts` = 0 or 1
   - Payload matches request

**Expected Result:**
- Webhook processed successfully
- Record created in `webhook_events` table
- Status = `processed`

---

### Scenario 2: Idempotency - Duplicate Prevention

**Objective:** Verify duplicate webhooks are not processed twice.

**Steps:**
1. Send webhook with `idempotency_key: "test-key-123"`
2. Verify webhook is processed (status = `processed`)
3. Send **same webhook again** with same `idempotency_key`
4. Check response and database

**Expected Result:**
- Response indicates webhook already processed
- No duplicate processing
- Same `webhook_event` record returned
- Status remains `processed`

---

### Scenario 3: Failed Webhook - Automatic Retry

**Objective:** Verify failed webhooks are automatically retried.

**Steps:**
1. **Simulate failure** (e.g., invalid data, database error)
2. Send webhook that will fail
3. Check database:
   - Status = `failed`
   - `attempts` = 1
   - `next_retry_at` is set (1 minute from now)
   - `error_message` contains error details
4. Wait for retry job to execute (or trigger manually)
5. Check if webhook is retried

**Expected Result:**
- Webhook marked as `failed`
- Retry job scheduled
- After retry delay, webhook is processed again
- `attempts` incremented

---

### Scenario 4: Retry with Exponential Backoff

**Objective:** Verify retry delays increase exponentially.

**Steps:**
1. Send webhook that fails
2. Note `next_retry_at` time (should be ~1 minute)
3. Let it retry and fail again
4. Check new `next_retry_at` (should be ~5 minutes)
5. Let it retry and fail again
6. Check new `next_retry_at` (should be ~15 minutes)

**Expected Result:**
- Attempt 1: 1 minute delay
- Attempt 2: 5 minutes delay
- Attempt 3: 15 minutes delay

---

### Scenario 5: Permanent Failure After Max Attempts

**Objective:** Verify webhook is marked as permanently failed after max attempts.

**Steps:**
1. Send webhook that will always fail (e.g., invalid subscription_id)
2. Let it retry 3 times (max attempts)
3. Check database after all retries

**Expected Result:**
- After 3 failed attempts, status = `permanently_failed`
- `attempts` = 3
- `next_retry_at` = null
- `failed_at` is set

---

### Scenario 6: Webhook Signature Verification

**Objective:** Verify webhook signature validation works.

**Steps:**
1. **With verification enabled:**
   - Send webhook **without** signature header
   - Verify response is `401 Unauthorized`
   - Verify error message: "Invalid signature"

2. **With verification disabled (testing):**
   - Set `RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=false`
   - Send webhook without signature
   - Verify webhook is processed

**Expected Result:**
- Invalid/missing signature → 401 error
- Valid signature → webhook processed
- Verification disabled → webhook processed (testing only)

---

### Scenario 7: Invalid Webhook Structure

**Objective:** Verify invalid webhook structure is rejected.

**Steps:**
1. Send webhook with missing `event` field
2. Send webhook with missing `data` field
3. Send webhook with invalid `event` type

**Expected Result:**
- Response: `422 Unprocessable Entity`
- Error message describes validation errors
- Webhook not stored in database (or stored with `failed` status)

---

### Scenario 8: Multiple Webhook Types

**Objective:** Verify different webhook event types are handled correctly.

**Test Events:**
- `subscription.created`
- `subscription.updated`
- `subscription.cancelled`
- `payment.succeeded`
- `payment.failed`
- `unknown.event` (should be ignored)

**Expected Result:**
- Each event type processed correctly
- Unknown events return `200 OK` with status `ignored`
- Appropriate database records created

---

## 🔧 Manual Testing Setup

### Prerequisites

1. **Docker environment running:**
   ```bash
   docker compose up -d
   ```

2. **Database migrated:**
   ```bash
   docker compose exec php php artisan migrate
   docker compose exec php php artisan db:seed --class=SubscriptionPlanSeeder
   ```

3. **Disable signature verification (for testing):**
   ```bash
   # In .env file
   RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=false
   ```

### Test Tools

- **Postman** - For sending webhook requests
- **Database client** - For checking `webhook_events` table
- **Queue monitor** - For checking retry jobs (Horizon)

---

## 📝 Test Cases Checklist

### Basic Functionality
- [ ] Webhook processed successfully
- [ ] Webhook stored in database
- [ ] Correct status set (`processed`)
- [ ] Timestamps set correctly

### Idempotency
- [ ] Duplicate webhook with same idempotency_key not processed twice
- [ ] Same webhook event record returned
- [ ] No duplicate database records

### Error Handling
- [ ] Failed webhook marked as `failed`
- [ ] Error message stored
- [ ] Error context stored
- [ ] Retry scheduled

### Retry Mechanism
- [ ] Retry job dispatched on failure
- [ ] Exponential backoff works (1min, 5min, 15min)
- [ ] Retry attempts incremented
- [ ] Webhook retried automatically

### Permanent Failure
- [ ] After 3 attempts, status = `permanently_failed`
- [ ] No more retries scheduled
- [ ] Error details preserved

### Security
- [ ] Invalid signature rejected (401)
- [ ] Missing signature rejected (401)
- [ ] Valid signature accepted

### Validation
- [ ] Missing `event` field rejected (422)
- [ ] Missing `data` field rejected (422)
- [ ] Invalid event type handled gracefully

---

## 🧪 Example Test Requests

### Test 1: Subscription Created

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{
    "event": "subscription.created",
    "data": {
      "rapidapi_user_id": "user-test-123",
      "plan": "basic"
    },
    "idempotency_key": "test-sub-created-1"
  }'
```

**Expected Response:**
```json
{
  "status": "success",
  "subscription_id": "uuid-here"
}
```

**Database Check:**
```sql
SELECT * FROM webhook_events WHERE idempotency_key = 'test-sub-created-1';
-- Should show: status = 'processed', processed_at is set
```

### Test 2: Duplicate Webhook (Idempotency)

```bash
# Send same webhook again
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{
    "event": "subscription.created",
    "data": {
      "rapidapi_user_id": "user-test-123",
      "plan": "basic"
    },
    "idempotency_key": "test-sub-created-1"
  }'
```

**Expected Response:**
```json
{
  "status": "success",
  "message": "Webhook already processed",
  "webhook_id": "same-uuid-as-before"
}
```

### Test 3: Failed Webhook (Invalid Data)

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{
    "event": "subscription.updated",
    "data": {
      "subscription_id": "non-existent-uuid",
      "plan": "pro"
    },
    "idempotency_key": "test-fail-1"
  }'
```

**Expected Response:**
```json
{
  "error": "Failed to process webhook",
  "message": "Subscription not found",
  "webhook_id": "uuid-here"
}
```

**Database Check:**
```sql
SELECT * FROM webhook_events WHERE idempotency_key = 'test-fail-1';
-- Should show: status = 'failed', attempts = 1, next_retry_at is set
```

---

## 🔍 Monitoring and Verification

### Check Webhook Status

```sql
-- All webhooks
SELECT id, event_type, source, status, attempts, created_at, processed_at, failed_at 
FROM webhook_events 
ORDER BY created_at DESC;

-- Failed webhooks ready for retry
SELECT * FROM webhook_events 
WHERE status = 'failed' 
  AND next_retry_at <= NOW()
ORDER BY next_retry_at ASC;

-- Permanently failed webhooks
SELECT * FROM webhook_events 
WHERE status = 'permanently_failed'
ORDER BY failed_at DESC;
```

### Check Queue Jobs

```bash
# Check Horizon dashboard
http://localhost:8000/horizon

# Or check database
SELECT * FROM jobs WHERE queue = 'default';
```

### Check Logs

```bash
docker compose exec php tail -f storage/logs/laravel.log | grep -i webhook
```

---

## 🐛 Common Issues and Solutions

### Issue: Webhook not retrying

**Check:**
1. Is queue worker running? (`php artisan queue:work`)
2. Is `next_retry_at` in the past?
3. Is webhook status `failed` (not `permanently_failed`)?
4. Are attempts < max_attempts?

**Solution:**
- Start queue worker: `docker compose exec php php artisan queue:work`
- Manually trigger retry: Check Horizon dashboard

### Issue: Webhook marked as permanently_failed too early

**Check:**
- Verify `max_attempts` is set correctly (default: 3)
- Check if webhook actually failed 3 times

**Solution:**
- Reset webhook: Update status to `pending`, attempts to 0

### Issue: Duplicate webhooks processed

**Check:**
- Is `idempotency_key` provided?
- Is `idempotency_key` unique?

**Solution:**
- Always provide unique `idempotency_key` for each webhook event

---

## 📊 Test Report Template

```markdown
## Webhook System Test Report

**Date:** YYYY-MM-DD
**Tester:** Name
**Environment:** local/staging/production

### Test Results

| Scenario | Status | Notes |
|----------|--------|-------|
| Successful Processing | ✅/❌ | |
| Idempotency | ✅/❌ | |
| Retry Mechanism | ✅/❌ | |
| Exponential Backoff | ✅/❌ | |
| Permanent Failure | ✅/❌ | |
| Signature Verification | ✅/❌ | |
| Invalid Structure | ✅/❌ | |
| Multiple Event Types | ✅/❌ | |

### Issues Found

1. [Issue description]
2. [Issue description]

### Recommendations

- [Recommendation]
- [Recommendation]
```

---

## 🔗 Related Documentation

- [Webhook System Technical Guide](../knowledge/technical/WEBHOOK_SYSTEM.md)
- [RapidAPI Webhooks Guide](../../RAPIDAPI_WEBHOOKS.md)
- [ADR-008: Webhook Architecture](../../adr/008-webhook-system-architecture.md)

---

**Last Updated:** 2025-01-27

