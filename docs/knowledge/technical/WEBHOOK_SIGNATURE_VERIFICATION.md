# Webhook Signature Verification

> **Related Task:** TASK-008  
> **Status:** Implemented  
> **Created:** 2026-01-07

---

## Overview

Webhook signature verification is a security mechanism that ensures incoming webhooks are authentic and haven't been tampered with. It uses HMAC-SHA256 to verify that the webhook was sent by an authorized system.

---

## `WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE` - What is it?

`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE` is an environment variable that controls whether signature verification is enabled for incoming notification webhooks.

### Values

- **`true`** (default): Signature verification is **enabled**. All incoming webhooks must include a valid signature header.
- **`false`**: Signature verification is **disabled**. Webhooks are accepted without signature validation (useful for testing/development).

### Default Behavior

If not set in `.env`, the default value is `true` (verification enabled). This ensures security by default.

---

## How It Works

### 1. External System Sends Webhook

When an external system sends a webhook to MovieMind API, it must:

1. Calculate HMAC-SHA256 signature of the request body:
   ```php
   $signature = hash_hmac('sha256', $requestBody, $secret);
   ```

2. Include the signature in the `X-Notification-Webhook-Signature` header:
   ```
   X-Notification-Webhook-Signature: <signature>
   ```

### 2. API Verifies Signature

When MovieMind API receives the webhook:

1. **If verification is enabled** (`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true`):
   - Reads `NOTIFICATION_WEBHOOK_SECRET` from environment
   - Calculates expected signature using HMAC-SHA256
   - Compares provided signature with expected signature (using `hash_equals()` for timing-safe comparison)
   - **If signatures match**: Webhook is processed
   - **If signatures don't match**: Webhook is rejected with `401 Unauthorized`

2. **If verification is disabled** (`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=false`):
   - Webhook is accepted without signature validation
   - ⚠️ **Warning:** This should only be used in development/testing environments

### 3. Response

**Valid Signature (200 OK):**
```json
{
  "status": "success",
  "message": "Generation completed event logged",
  "webhook_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Invalid Signature (401 Unauthorized):**
```json
{
  "error": "Invalid signature"
}
```

**Missing Signature (401 Unauthorized):**
```json
{
  "error": "Invalid signature"
}
```

---

## Configuration

### Environment Variables

```env
# Secret key shared between MovieMind API and external system
# Must match the secret used by external system to sign webhooks
NOTIFICATION_WEBHOOK_SECRET=your-secret-key-here

# Enable/disable signature verification
# true = verification enabled (default, recommended for production)
# false = verification disabled (only for testing/development)
WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true
```

### Configuration File

The configuration is defined in `api/config/webhooks.php`:

```php
'notification_secret' => env('NOTIFICATION_WEBHOOK_SECRET'),
'verify_notification_signature' => env('WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE', true),
```

---

## Security Best Practices

### ✅ DO

1. **Always enable verification in production** (`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true`)
2. **Use strong, random secrets** (at least 32 characters)
3. **Store secrets securely** (environment variables, not in code)
4. **Rotate secrets periodically** (update both API and external system)
5. **Use HTTPS** for webhook endpoints (signatures protect data integrity, not confidentiality)

### ❌ DON'T

1. **Don't disable verification in production** (`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=false`)
2. **Don't commit secrets to version control**
3. **Don't use weak secrets** (e.g., "password123")
4. **Don't share secrets publicly** (keep them confidential)

---

## Testing

### Testing with Verification Enabled

```bash
# Set secret
export NOTIFICATION_WEBHOOK_SECRET="test-secret-12345"
export WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true

# Calculate signature
PAYLOAD='{"event":"generation.completed","data":{"test":"data"}}'
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$NOTIFICATION_WEBHOOK_SECRET" | cut -d' ' -f2)

# Send webhook with valid signature
curl -X POST http://localhost:8000/api/v1/webhooks/notification \
  -H "Content-Type: application/json" \
  -H "X-Notification-Webhook-Signature: $SIGNATURE" \
  -d "$PAYLOAD"
```

### Testing with Verification Disabled

```bash
# Disable verification
export WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=false

# Send webhook without signature (for testing only)
curl -X POST http://localhost:8000/api/v1/webhooks/notification \
  -H "Content-Type: application/json" \
  -d '{"event":"generation.completed","data":{"test":"data"}}'
```

---

## Implementation Details

### Code Location

Signature verification is implemented in:
- **Controller:** `api/app/Http/Controllers/Admin/NotificationWebhookController.php`
- **Method:** `validateSignature(Request $request): bool`

### Algorithm

```php
private function validateSignature(Request $request): bool
{
    $webhookSecret = config('webhooks.notification_secret');
    $verifyEnabled = config('webhooks.verify_notification_signature', true);

    // If verification is disabled, always return true
    if (! $verifyEnabled) {
        return true;
    }

    // If no secret is configured, verification fails
    if ($webhookSecret === null || $webhookSecret === '') {
        return false;
    }

    // Get signature from header
    $providedSignature = $request->header('X-Notification-Webhook-Signature');
    if ($providedSignature === null || $providedSignature === '') {
        return false;
    }

    // Calculate expected signature
    $requestBody = $request->getContent();
    $expectedSignature = hash_hmac('sha256', $requestBody, $webhookSecret);

    // Use hash_equals for timing-safe comparison
    return hash_equals($expectedSignature, trim($providedSignature));
}
```

### Security Features

1. **Timing-safe comparison:** Uses `hash_equals()` to prevent timing attacks
2. **HMAC-SHA256:** Industry-standard algorithm for message authentication
3. **Body-based signature:** Signature is calculated from request body, preventing tampering
4. **Header-based delivery:** Signature is sent in separate header, not in body

---

## Troubleshooting

### Problem: Webhook rejected with "Invalid signature"

**Possible causes:**
1. Secret mismatch between API and external system
2. Signature calculated incorrectly (e.g., using wrong body format)
3. Header name mismatch (should be `X-Notification-Webhook-Signature`)
4. Whitespace in signature (trimmed automatically)

**Solution:**
1. Verify `NOTIFICATION_WEBHOOK_SECRET` matches in both systems
2. Ensure signature is calculated from raw request body (before JSON encoding)
3. Check header name is exactly `X-Notification-Webhook-Signature`
4. Verify signature format (should be hex string, 64 characters)

### Problem: Webhook accepted without signature

**Possible causes:**
1. `WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=false` in `.env`
2. Secret not configured (`NOTIFICATION_WEBHOOK_SECRET` is empty)

**Solution:**
1. Set `WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true` in `.env`
2. Set `NOTIFICATION_WEBHOOK_SECRET` to a valid secret
3. Clear config cache: `php artisan config:clear`

---

## Related Documentation

- [Notification Webhooks Technical Guide](./NOTIFICATION_WEBHOOKS.md)
- [Webhook System Overview](./WEBHOOK_SYSTEM.md)
- [Webhook System Business Documentation](../../business/WEBHOOK_SYSTEM_BUSINESS.md)
- [Notification Webhooks QA Guide](../../qa/NOTIFICATION_WEBHOOKS_QA_GUIDE.md)

---

## Summary

`WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE` is a security control that enables/disables signature verification for incoming webhooks. It should be set to `true` in production to ensure webhook authenticity and prevent unauthorized access.

