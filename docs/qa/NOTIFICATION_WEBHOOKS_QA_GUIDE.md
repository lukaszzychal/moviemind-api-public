# Notification Webhooks - QA Testing Guide

> **For:** QA Engineers, Testers  
> **Related Task:** TASK-008  
> **Last Updated:** 2026-01-07

---

## Overview

This guide provides test scenarios, edge cases, and manual testing procedures for notification webhooks (incoming and outgoing).

## Test Scenarios

### Incoming Notification Webhooks

#### Test Case 1: Valid Webhook with Signature

**Objective:** Verify that a valid webhook with correct signature is accepted.

**Steps:**
1. Calculate HMAC-SHA256 signature of request body using `NOTIFICATION_WEBHOOK_SECRET`
2. Send POST request to `/api/v1/webhooks/notification` with:
   - Header: `X-Notification-Webhook-Signature: <signature>`
   - Body: Valid webhook payload
3. Verify response status is 200
4. Verify response contains `status: "success"`
5. Verify webhook event is stored in database

**Expected Result:** Webhook is accepted and processed successfully.

#### Test Case 2: Invalid Signature

**Objective:** Verify that webhook with invalid signature is rejected.

**Steps:**
1. Send POST request with invalid signature
2. Verify response status is 401
3. Verify response contains `error: "Invalid signature"`
4. Verify webhook event is NOT stored in database

**Expected Result:** Webhook is rejected with 401 error.

#### Test Case 3: Missing Required Fields

**Objective:** Verify that webhook with missing required fields is rejected.

**Steps:**
1. Send POST request without `event` field
2. Verify response status is 422
3. Verify response contains validation errors

**Expected Result:** Webhook is rejected with 422 error.

#### Test Case 4: Idempotency

**Objective:** Verify that duplicate webhooks (same idempotency_key) are handled correctly.

**Steps:**
1. Send first webhook with `idempotency_key: "test-key-1"`
2. Verify response status is 200
3. Send second webhook with same `idempotency_key`
4. Verify response status is 200
5. Verify response contains `message: "Webhook already processed"`
6. Verify only one webhook event exists in database

**Expected Result:** Duplicate webhook returns cached response without reprocessing.

#### Test Case 5: Unknown Event Type

**Objective:** Verify that unknown event types are handled gracefully.

**Steps:**
1. Send webhook with `event: "unknown.event"`
2. Verify response status is 200
3. Verify response contains `status: "ignored"`

**Expected Result:** Unknown event is ignored without error.

### Outgoing Notification Webhooks

#### Test Case 6: Webhook Sent on Generation Requested

**Objective:** Verify that webhook is sent when generation is requested.

**Prerequisites:**
- Feature flag `webhook_notifications` enabled
- Webhook URL configured: `WEBHOOK_URL_MOVIE_GENERATION_COMPLETED`

**Steps:**
1. Configure webhook URL in environment
2. Enable feature flag
3. Trigger movie generation (e.g., `POST /api/v1/generate`)
4. Verify webhook is sent to configured URL
5. Verify webhook payload contains correct data
6. Verify webhook is stored in `outgoing_webhooks` table with status `sent`

**Expected Result:** Webhook is sent successfully when generation is requested.

#### Test Case 7: Webhook Retry on Failure

**Objective:** Verify that failed webhooks are retried automatically.

**Prerequisites:**
- Feature flag `webhook_notifications` enabled
- Webhook URL configured to return 500 error

**Steps:**
1. Configure webhook URL to return 500 error
2. Trigger movie generation
3. Verify webhook is stored with status `failed`
4. Verify `next_retry_at` is set
5. Wait for retry time
6. Verify webhook is retried automatically
7. Verify `attempts` counter is incremented

**Expected Result:** Failed webhook is retried with exponential backoff.

#### Test Case 8: Webhook Signature

**Objective:** Verify that outgoing webhooks are signed correctly.

**Prerequisites:**
- Feature flag `webhook_notifications` enabled
- `OUTGOING_WEBHOOK_SECRET` configured

**Steps:**
1. Configure webhook secret
2. Trigger movie generation
3. Capture webhook request
4. Verify `X-MovieMind-Webhook-Signature` header is present
5. Verify signature is valid HMAC-SHA256 of payload

**Expected Result:** Webhook is signed correctly.

#### Test Case 9: Multiple Webhook URLs

**Objective:** Verify that webhook is sent to all configured URLs.

**Prerequisites:**
- Feature flag `webhook_notifications` enabled
- Multiple webhook URLs configured

**Steps:**
1. Configure multiple webhook URLs for same event type
2. Trigger movie generation
3. Verify webhook is sent to all configured URLs
4. Verify separate records in `outgoing_webhooks` table for each URL

**Expected Result:** Webhook is sent to all configured URLs.

#### Test Case 10: Feature Flag Disabled

**Objective:** Verify that webhooks are not sent when feature flag is disabled.

**Prerequisites:**
- Feature flag `webhook_notifications` disabled
- Webhook URL configured

**Steps:**
1. Disable feature flag
2. Trigger movie generation
3. Verify no webhook is sent
4. Verify no records in `outgoing_webhooks` table

**Expected Result:** Webhooks are not sent when feature flag is disabled.

## Edge Cases

### Edge Case 1: Network Timeout

**Scenario:** Webhook URL is unreachable or times out.

**Expected Behavior:**
- Webhook is marked as `failed`
- Retry is scheduled with exponential backoff
- After max attempts, webhook is marked as `permanently_failed`

### Edge Case 2: Malformed Payload

**Scenario:** External system sends malformed JSON.

**Expected Behavior:**
- Webhook is rejected with 422 error
- Error is logged
- Webhook event is stored with status `failed`

### Edge Case 3: Concurrent Webhooks

**Scenario:** Multiple webhooks sent simultaneously with same idempotency_key.

**Expected Behavior:**
- First webhook is processed
- Subsequent webhooks return cached response
- No duplicate processing occurs

### Edge Case 4: Webhook URL Returns 200 but Invalid Response

**Scenario:** Webhook URL returns 200 but response body is invalid.

**Expected Behavior:**
- Webhook is marked as `sent` (200 status code)
- Response body is stored in `response_body` field
- No retry is scheduled

### Edge Case 5: Webhook Secret Changed

**Scenario:** Webhook secret is changed while webhooks are in flight.

**Expected Behavior:**
- Incoming webhooks with old signature are rejected
- Outgoing webhooks use new secret for signing
- Old webhooks in retry queue may fail (expected)

## Manual Testing Procedures

### Testing Incoming Webhooks

1. **Setup:**
   ```bash
   # Set webhook secret
   export NOTIFICATION_WEBHOOK_SECRET=test-secret
   export WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true
   ```

2. **Generate Signature:**
   ```bash
   # Using PHP
   php -r "echo hash_hmac('sha256', file_get_contents('php://stdin'), 'test-secret');"
   # Then paste webhook payload JSON
   ```

3. **Send Webhook:**
   ```bash
   curl -X POST https://api.example.com/api/v1/webhooks/notification \
     -H "Content-Type: application/json" \
     -H "X-Notification-Webhook-Signature: <signature>" \
     -d '{
       "event": "generation.completed",
       "data": {
         "entity_type": "MOVIE",
         "entity_id": "the-matrix-1999"
       },
       "idempotency_key": "test-key-1"
     }'
   ```

4. **Verify:**
   - Check response status and body
   - Check database for webhook event record
   - Check logs for processing details

### Testing Outgoing Webhooks

1. **Setup Webhook Receiver:**
   ```bash
   # Use ngrok or similar tool to expose local server
   ngrok http 3000
   ```

2. **Configure Webhook URL:**
   ```bash
   export WEBHOOK_URL_MOVIE_GENERATION_COMPLETED=https://your-ngrok-url.ngrok.io/webhook
   export OUTGOING_WEBHOOK_SECRET=test-secret
   ```

3. **Enable Feature Flag:**
   ```php
   Feature::for('default')->activate('webhook_notifications');
   ```

4. **Trigger Generation:**
   ```bash
   curl -X POST https://api.example.com/api/v1/generate \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US"
     }'
   ```

5. **Verify:**
   - Check webhook receiver for incoming request
   - Verify signature header
   - Verify payload structure
   - Check database for outgoing webhook record

## Postman/Newman Tests

### Test Collection

Create Postman collection with following requests:

1. **Incoming Webhook - Valid**
   - Method: POST
   - URL: `/api/v1/webhooks/notification`
   - Headers: `X-Notification-Webhook-Signature`
   - Body: Valid webhook payload

2. **Incoming Webhook - Invalid Signature**
   - Method: POST
   - URL: `/api/v1/webhooks/notification`
   - Headers: `X-Notification-Webhook-Signature: invalid`
   - Body: Valid webhook payload

3. **Incoming Webhook - Missing Fields**
   - Method: POST
   - URL: `/api/v1/webhooks/notification`
   - Body: Missing `event` field

4. **Incoming Webhook - Idempotency**
   - Method: POST
   - URL: `/api/v1/webhooks/notification`
   - Body: Same `idempotency_key` twice

### Running Tests

```bash
# Run Newman tests
newman run notification-webhooks.postman_collection.json \
  --environment production.postman_environment.json
```

## Performance Testing

### Load Testing

1. **Test Incoming Webhooks:**
   - Send 1000 webhooks simultaneously
   - Verify all are processed
   - Check processing time
   - Verify no duplicates

2. **Test Outgoing Webhooks:**
   - Trigger 1000 generations
   - Verify all webhooks are sent
   - Check delivery time
   - Verify retry mechanism works

### Stress Testing

1. **Test with Slow Webhook URLs:**
   - Configure webhook URL with 5-second delay
   - Send multiple webhooks
   - Verify queue doesn't block
   - Verify retries work correctly

## Security Testing

1. **Signature Verification:**
   - Test with invalid signature
   - Test with missing signature
   - Test with tampered payload

2. **SQL Injection:**
   - Test payload with SQL injection attempts
   - Verify payload is sanitized

3. **XSS:**
   - Test payload with XSS attempts
   - Verify payload is sanitized

## Checklist

### Incoming Webhooks
- [ ] Valid webhook with signature accepted
- [ ] Invalid signature rejected
- [ ] Missing fields rejected
- [ ] Idempotency works correctly
- [ ] Unknown events ignored
- [ ] Retry mechanism works
- [ ] Database records created correctly

### Outgoing Webhooks
- [ ] Webhook sent on generation requested
- [ ] Webhook signed correctly
- [ ] Retry on failure works
- [ ] Multiple URLs receive webhooks
- [ ] Feature flag controls webhooks
- [ ] Database records created correctly

## Related Documentation

- [Webhook System Architecture](../../knowledge/technical/WEBHOOK_SYSTEM.md)
- [Notification Webhooks Guide](../../knowledge/technical/NOTIFICATION_WEBHOOKS.md)
- [Business Documentation](../../business/WEBHOOK_SYSTEM_BUSINESS.md)

---

**Last updated:** 2026-01-07

