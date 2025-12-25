# RapidAPI Webhooks Guide

This guide explains how to configure and handle webhooks from RapidAPI.

## 📡 Webhook Endpoint

**URL**: `POST /api/v1/webhooks/billing`

**Authentication**: HMAC signature verification (no API key required)

## 🔐 Security

### HMAC Signature Verification

All webhook requests include an `X-RapidAPI-Signature` header containing an HMAC-SHA256 signature of the request body.

**Verification Process:**

1. Extract the signature from `X-RapidAPI-Signature` header
2. Calculate expected signature: `HMAC-SHA256(request_body, webhook_secret)`
3. Compare signatures using constant-time comparison

**Configuration:**

Set `RAPIDAPI_WEBHOOK_SECRET` in your `.env` file. This secret must match the one configured in RapidAPI Hub.

**Disable Verification (Testing Only):**

Set `RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=false` in your `.env` file. **Never disable in production.**

## 📨 Webhook Events

### subscription.created

Triggered when a new subscription is created.

**Request Body:**

```json
{
  "event": "subscription.created",
  "data": {
    "rapidapi_user_id": "user-123",
    "plan": "basic",
    "api_key_id": "optional-uuid"
  },
  "idempotency_key": "unique-key-123"
}
```

**Response:**

```json
{
  "status": "success",
  "subscription_id": "uuid-of-created-subscription"
}
```

**Status Code**: `201 Created`

### subscription.updated

Triggered when a subscription plan is upgraded or downgraded.

**Request Body:**

```json
{
  "event": "subscription.updated",
  "data": {
    "subscription_id": "uuid-of-subscription",
    "plan": "pro"
  },
  "idempotency_key": "unique-key-456"
}
```

**Response:**

```json
{
  "status": "success",
  "subscription_id": "uuid-of-updated-subscription"
}
```

**Status Code**: `200 OK`

### subscription.cancelled

Triggered when a subscription is cancelled.

**Request Body:**

```json
{
  "event": "subscription.cancelled",
  "data": {
    "subscription_id": "uuid-of-subscription"
  },
  "idempotency_key": "unique-key-789"
}
```

**Response:**

```json
{
  "status": "success",
  "subscription_id": "uuid-of-cancelled-subscription"
}
```

**Status Code**: `200 OK`

### payment.succeeded

Triggered when a payment is successfully processed.

**Request Body:**

```json
{
  "event": "payment.succeeded",
  "data": {
    "transaction_id": "txn-123",
    "amount": 10.00,
    "currency": "USD"
  },
  "idempotency_key": "unique-key-abc"
}
```

**Response:**

```json
{
  "status": "success",
  "message": "Payment succeeded event acknowledged"
}
```

**Status Code**: `200 OK`

### payment.failed

Triggered when a payment fails.

**Request Body:**

```json
{
  "event": "payment.failed",
  "data": {
    "transaction_id": "txn-456",
    "reason": "insufficient_funds"
  },
  "idempotency_key": "unique-key-def"
}
```

**Response:**

```json
{
  "status": "success",
  "message": "Payment failed event acknowledged"
}
```

**Status Code**: `200 OK`

## 🔄 Idempotency

All webhook events include an `idempotency_key` to prevent duplicate processing.

**Behavior:**

- If a webhook with the same `idempotency_key` is received, the previous response is returned
- No duplicate subscriptions or updates are created
- The original response status code is returned

**Best Practice:**

Always include a unique `idempotency_key` for each webhook event. Use a combination of event type and unique identifier (e.g., `subscription.created-{user_id}-{timestamp}`).

## ❌ Error Handling

### Invalid Signature

**Status Code**: `401 Unauthorized`

**Response:**

```json
{
  "error": "Invalid signature"
}
```

### Invalid Request Structure

**Status Code**: `422 Unprocessable Entity`

**Response:**

```json
{
  "error": "Invalid request structure",
  "errors": {
    "event": ["The event field is required."],
    "data": ["The data field is required."]
  }
}
```

### Processing Error

**Status Code**: `500 Internal Server Error`

**Response:**

```json
{
  "error": "Failed to process webhook",
  "message": "Detailed error message"
}
```

## 🧪 Testing

### Test Webhook Locally

1. Set `RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=false` in `.env`
2. Send test request:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -d '{
    "event": "subscription.created",
    "data": {
      "rapidapi_user_id": "test-user-123",
      "plan": "basic"
    },
    "idempotency_key": "test-key-1"
  }'
```

### Test with Signature Verification

1. Set `RAPIDAPI_WEBHOOK_SECRET=test-secret` in `.env`
2. Calculate signature:

```bash
# Using OpenSSL
echo -n '{"event":"subscription.created","data":{"rapidapi_user_id":"test-user-123","plan":"basic"},"idempotency_key":"test-key-1"}' | \
  openssl dgst -sha256 -hmac "test-secret" -binary | \
  base64
```

3. Send request with signature:

```bash
curl -X POST http://localhost:8000/api/v1/webhooks/billing \
  -H "Content-Type: application/json" \
  -H "X-RapidAPI-Signature: <calculated-signature>" \
  -d '{
    "event": "subscription.created",
    "data": {
      "rapidapi_user_id": "test-user-123",
      "plan": "basic"
    },
    "idempotency_key": "test-key-1"
  }'
```

## 📊 Monitoring

### Log Webhook Events

Webhook events are automatically logged. Check your application logs for:

- Webhook received events
- Signature verification results
- Processing errors
- Idempotency key matches

### Monitor Webhook Health

Use the Analytics API to monitor webhook processing:

```bash
GET /v1/admin/analytics/error-rate?start_date=2025-01-01&end_date=2025-01-31
```

## ✅ Best Practices

1. **Always verify signatures** in production
2. **Use idempotency keys** to prevent duplicate processing
3. **Log all webhook events** for debugging
4. **Handle errors gracefully** and return appropriate status codes
5. **Test webhooks thoroughly** before going live
6. **Monitor webhook delivery** and set up alerts for failures

## 🔗 Related Documentation

- [RapidAPI Setup Guide](RAPIDAPI_SETUP.md)
- [RapidAPI Pricing Guide](RAPIDAPI_PRICING.md)

