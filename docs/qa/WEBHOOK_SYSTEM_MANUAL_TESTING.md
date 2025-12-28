# Webhook System - Manual Testing Guide

> **For:** QA Engineers, Manual Testers  
> **Related Task:** TASK-008  
> **Last Updated:** 2025-01-27

---

## 🎯 Quick Start

### Prerequisites

1. **Start Docker environment:**
   ```bash
   docker compose up -d
   ```

2. **Run migrations:**
   ```bash
   docker compose exec php php artisan migrate
   docker compose exec php php artisan db:seed --class=SubscriptionPlanSeeder
   ```

3. **Disable signature verification (for testing):**
   ```bash
   # Edit api/.env
   RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=false
   ```

4. **Start queue worker (for retry testing):**
   ```bash
   docker compose exec php php artisan queue:work
   ```

---

## 🧪 Test Scenarios

### Test 1: Basic Webhook Processing ✅

**Goal:** Verify webhook is received and processed successfully.

**Steps:**
1. Send POST request to `http://localhost:8000/api/v1/webhooks/billing`
2. Use Postman or curl (see examples below)
3. Check response status (should be `201` or `200`)
4. Check database `webhook_events` table

**Request:**
```json
{
  "event": "subscription.created",
  "data": {
    "rapidapi_user_id": "user-test-001",
    "plan": "basic"
  },
  "idempotency_key": "test-001"
}
```

**Expected Response:**
- Status: `201 Created`
- Body: `{"status": "success", "subscription_id": "..."}`

**Database Verification:**
```sql
SELECT * FROM webhook_events WHERE idempotency_key = 'test-001';
-- Should show: status = 'processed', processed_at is set
```

**✅ Pass Criteria:**
- Response is `201 Created`
- Webhook stored in database
- Status = `processed`
- Subscription created in `subscriptions` table

---

### Test 2: Idempotency (Duplicate Prevention) 🔄

**Goal:** Verify same webhook is not processed twice.

**Steps:**
1. Send webhook with `idempotency_key: "test-dup-001"`
2. Verify it's processed (status = `processed`)
3. **Send exact same webhook again** with same idempotency_key
4. Check response and database

**Request (send twice):**
```json
{
  "event": "subscription.created",
  "data": {
    "rapidapi_user_id": "user-test-002",
    "plan": "basic"
  },
  "idempotency_key": "test-dup-001"
}
```

**Expected Response (2nd request):**
- Status: `200 OK`
- Body: `{"status": "success", "message": "Webhook already processed", "webhook_id": "..."}`

**Database Verification:**
```sql
SELECT COUNT(*) FROM webhook_events WHERE idempotency_key = 'test-dup-001';
-- Should be: 1 (only one record)
```

**✅ Pass Criteria:**
- Only ONE webhook event record in database
- Second request returns "already processed" message
- No duplicate subscription created

---

### Test 3: Failed Webhook - Automatic Retry 🔁

**Goal:** Verify failed webhooks are automatically retried.

**Steps:**
1. Send webhook with **invalid data** (e.g., non-existent subscription_id)
2. Check database - should be marked as `failed`
3. Check `next_retry_at` - should be ~1 minute from now
4. Wait 1 minute (or manually trigger queue worker)
5. Check if webhook was retried

**Request (will fail):**
```json
{
  "event": "subscription.updated",
  "data": {
    "subscription_id": "00000000-0000-0000-0000-000000000000",
    "plan": "pro"
  },
  "idempotency_key": "test-retry-001"
}
```

**Expected Response:**
- Status: `500 Internal Server Error`
- Body: `{"error": "Failed to process webhook", "message": "...", "webhook_id": "..."}`

**Database Check (immediately after):**
```sql
SELECT status, attempts, next_retry_at, error_message 
FROM webhook_events 
WHERE idempotency_key = 'test-retry-001';
-- Should show: status = 'failed', attempts = 1, next_retry_at is set
```

**After Retry (1 minute later):**
```sql
SELECT status, attempts FROM webhook_events WHERE idempotency_key = 'test-retry-001';
-- Should show: attempts = 2 (if retry also failed)
```

**✅ Pass Criteria:**
- Webhook marked as `failed` after first attempt
- `next_retry_at` is set (1 minute delay)
- Retry job is dispatched
- Webhook is retried automatically

---

### Test 4: Exponential Backoff ⏱️

**Goal:** Verify retry delays increase exponentially.

**Steps:**
1. Send webhook that will always fail
2. Let it retry 3 times
3. Check `next_retry_at` after each retry

**Request:**
```json
{
  "event": "subscription.cancelled",
  "data": {
    "subscription_id": "invalid-uuid-here"
  },
  "idempotency_key": "test-backoff-001"
}
```

**Timeline:**
- **T+0:** Send webhook → fails → `next_retry_at` = T+1min
- **T+1min:** Retry → fails → `next_retry_at` = T+6min (1+5)
- **T+6min:** Retry → fails → `next_retry_at` = T+21min (6+15)

**Database Check:**
```sql
SELECT attempts, next_retry_at, created_at 
FROM webhook_events 
WHERE idempotency_key = 'test-backoff-001';
```

**✅ Pass Criteria:**
- Attempt 1: `next_retry_at` = ~1 minute
- Attempt 2: `next_retry_at` = ~5 minutes
- Attempt 3: `next_retry_at` = ~15 minutes

---

### Test 5: Permanent Failure After Max Attempts ❌

**Goal:** Verify webhook is marked as permanently failed after 3 attempts.

**Steps:**
1. Send webhook that will always fail
2. Let it retry 3 times (max attempts = 3)
3. Check final status

**Request:**
```json
{
  "event": "subscription.updated",
  "data": {
    "subscription_id": "00000000-0000-0000-0000-000000000000",
    "plan": "pro"
  },
  "idempotency_key": "test-permanent-001"
}
```

**After 3 Failed Attempts:**
```sql
SELECT status, attempts, max_attempts, next_retry_at, failed_at
FROM webhook_events 
WHERE idempotency_key = 'test-permanent-001';
-- Should show: status = 'permanently_failed', attempts = 3, next_retry_at = NULL
```

**✅ Pass Criteria:**
- Status = `permanently_failed`
- Attempts = 3
- `next_retry_at` = NULL (no more retries)
- `failed_at` is set

---

### Test 6: Signature Verification 🔐

**Goal:** Verify webhook signature validation works.

**Test 6a: Invalid Signature (Verification Enabled)**

**Steps:**
1. Set `RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=true` in `.env`
2. Set `RAPIDAPI_WEBHOOK_SECRET=test-secret`
3. Send webhook **without** signature header
4. Check response

**Request:**
```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{"event": "subscription.created", "data": {"rapidapi_user_id": "user-123", "plan": "basic"}}'
```

**Expected Response:**
- Status: `401 Unauthorized`
- Body: `{"error": "Invalid signature"}`

**Test 6b: Valid Signature**

**Steps:**
1. Calculate HMAC-SHA256 signature
2. Send webhook with signature header
3. Check response

**✅ Pass Criteria:**
- Without signature → `401 Unauthorized`
- With valid signature → `201 Created`
- With invalid signature → `401 Unauthorized`

---

### Test 7: Invalid Webhook Structure ⚠️

**Goal:** Verify invalid webhook structure is rejected.

**Test Cases:**

**7a: Missing `event` field**
```json
{
  "data": {"rapidapi_user_id": "user-123", "plan": "basic"}
}
```
**Expected:** `422 Unprocessable Entity`

**7b: Missing `data` field**
```json
{
  "event": "subscription.created"
}
```
**Expected:** `422 Unprocessable Entity`

**7c: Invalid event type**
```json
{
  "event": "unknown.event.type",
  "data": {}
}
```
**Expected:** `200 OK` with status `ignored`

**✅ Pass Criteria:**
- Missing required fields → `422` error
- Invalid event type → `200 OK` with `ignored` status
- Error message describes validation errors

---

### Test 8: Multiple Event Types 📨

**Goal:** Verify all webhook event types are handled correctly.

**Test Events:**

| Event | Request Data | Expected Result |
|-------|--------------|-----------------|
| `subscription.created` | `rapidapi_user_id`, `plan` | Subscription created |
| `subscription.updated` | `subscription_id`, `plan` | Subscription updated |
| `subscription.cancelled` | `subscription_id` | Subscription cancelled |
| `payment.succeeded` | Payment data | Event logged |
| `payment.failed` | Payment data | Event logged |
| `unknown.event` | Any data | Status `ignored` |

**✅ Pass Criteria:**
- Each event type processed correctly
- Appropriate database records created
- Unknown events handled gracefully

---

## 🛠️ Testing Tools

### Postman Collection

Create a Postman collection with all test scenarios:

```json
{
  "name": "Webhook System Tests",
  "requests": [
    {
      "name": "Subscription Created",
      "method": "POST",
      "url": "http://localhost:8000/api/v1/webhooks/billing",
      "body": {
        "event": "subscription.created",
        "data": {"rapidapi_user_id": "user-123", "plan": "basic"},
        "idempotency_key": "test-{{$timestamp}}"
      }
    }
  ]
}
```

### cURL Commands

**Basic Webhook:**
```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{
    "event": "subscription.created",
    "data": {
      "rapidapi_user_id": "user-test-001",
      "plan": "basic"
    },
    "idempotency_key": "test-001"
  }'
```

**Check Response:**
```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '...' \
  -v  # Verbose output
```

### Database Queries

**Check Webhook Status:**
```sql
-- All webhooks
SELECT id, event_type, status, attempts, created_at, processed_at 
FROM webhook_events 
ORDER BY created_at DESC 
LIMIT 10;

-- Failed webhooks
SELECT * FROM webhook_events 
WHERE status = 'failed' 
ORDER BY next_retry_at ASC;

-- Permanently failed
SELECT * FROM webhook_events 
WHERE status = 'permanently_failed';
```

---

## 📊 Test Results Template

```markdown
# Webhook System Manual Test Results

**Date:** 2025-01-27
**Tester:** [Name]
**Environment:** local
**Docker:** Running ✅
**Queue Worker:** Running ✅

## Test Results

| # | Test Scenario | Status | Notes |
|---|---------------|--------|-------|
| 1 | Basic Processing | ✅ PASS | |
| 2 | Idempotency | ✅ PASS | |
| 3 | Automatic Retry | ✅ PASS | |
| 4 | Exponential Backoff | ✅ PASS | |
| 5 | Permanent Failure | ✅ PASS | |
| 6 | Signature Verification | ✅ PASS | |
| 7 | Invalid Structure | ✅ PASS | |
| 8 | Multiple Event Types | ✅ PASS | |

## Issues Found

None

## Recommendations

- All tests passed
- System ready for staging deployment
```

---

## 🔍 Troubleshooting

### Webhook Not Processing

**Check:**
1. Is Docker running? `docker compose ps`
2. Is queue worker running? `docker compose exec php php artisan queue:work`
3. Check logs: `docker compose exec php tail -f storage/logs/laravel.log`
4. Check database: `SELECT * FROM webhook_events ORDER BY created_at DESC LIMIT 5;`

### Retry Not Working

**Check:**
1. Is queue worker running?
2. Is `next_retry_at` in the past?
3. Check queue: `SELECT * FROM jobs WHERE queue = 'default';`
4. Manually trigger: `docker compose exec php php artisan queue:work --once`

### Database Issues

**Check:**
1. Is migration run? `docker compose exec php php artisan migrate:status`
2. Is table created? `SELECT * FROM webhook_events LIMIT 1;`
3. Check connection: `docker compose exec php php artisan tinker` → `DB::connection()->getPdo();`

---

## 📚 Related Documentation

- [QA Testing Guide](./WEBHOOK_SYSTEM_QA_GUIDE.md)
- [Business Documentation](../business/WEBHOOK_SYSTEM_BUSINESS.md)
- [Technical Guide](../knowledge/technical/WEBHOOK_SYSTEM.md)

---

**Last Updated:** 2025-01-27

