# Notification Webhooks - Technical Guide

> **Related Task:** TASK-008  
> **Status:** Implemented  
> **Created:** 2026-01-07

---

## Overview

Notification webhooks enable bidirectional communication between MovieMind API and external systems:

- **Incoming:** External systems can send notification webhooks to MovieMind API
- **Outgoing:** MovieMind API can send webhooks to external systems when events occur

## Incoming Notification Webhooks

### Endpoint

```
POST /api/v1/webhooks/notification
```

### Authentication

Notification webhooks use HMAC-SHA256 signature verification instead of API keys. The signature is sent in the `X-Notification-Webhook-Signature` header.

### Request Format

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

### Supported Event Types

| Event Type | Description | Required Data Fields |
|------------|-------------|---------------------|
| `generation.completed` | External system notifies about completed generation | `entity_type`, `entity_id`, `job_id` |
| `generation.failed` | External system notifies about failed generation | `entity_type`, `entity_id`, `job_id`, `error` |
| `user.registered` | User registration notification | `user_id`, `email` |
| `user.updated` | User profile update | `user_id`, `email` (optional) |

### Signature Verification

1. Calculate HMAC-SHA256 of request body:
   ```php
   $signature = hash_hmac('sha256', $requestBody, $secret);
   ```

2. Send signature in header:
   ```
   X-Notification-Webhook-Signature: <signature>
   ```

3. Server verifies signature using `NOTIFICATION_WEBHOOK_SECRET`

### Response Format

**Success (200):**
```json
{
  "status": "success",
  "message": "Generation completed event logged",
  "webhook_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

**Error (401):**
```json
{
  "error": "Invalid signature"
}
```

**Error (422):**
```json
{
  "error": "Invalid request structure",
  "errors": {
    "event": ["The event field is required."]
  }
}
```

### Idempotency

Notification webhooks support idempotency via `idempotency_key`. If the same key is received twice, the system returns the cached response without reprocessing.

### Configuration

```env
# Secret key for verifying incoming webhook signatures
NOTIFICATION_WEBHOOK_SECRET=your-secret-key

# Enable/disable signature verification (default: true)
# Set to false only for testing/development
WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true
WEBHOOK_VERIFY_NOTIFICATION_SIGNATURE=true
```

## Outgoing Notification Webhooks

### Overview

MovieMind API automatically sends webhooks to configured URLs when events occur (e.g., generation requested, generation completed).

### Event Types

| Event Type | Description | When Dispatched |
|------------|-------------|----------------|
| `movie.generation.requested` | Movie generation requested | When `MovieGenerationRequested` event is dispatched |
| `person.generation.requested` | Person generation requested | When `PersonGenerationRequested` event is dispatched |
| `movie.generation.completed` | Movie generation completed | (Future) When generation job completes successfully |
| `person.generation.completed` | Person generation completed | (Future) When generation job completes successfully |
| `movie.generation.failed` | Movie generation failed | (Future) When generation job fails |
| `person.generation.failed` | Person generation failed | (Future) When generation job fails |

### Webhook Payload Format

**Movie Generation Requested:**
```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "locale": "en-US",
  "context_tag": "modern"
}
```

**Person Generation Requested:**
```json
{
  "entity_type": "PERSON",
  "slug": "keanu-reeves",
  "job_id": "550e8400-e29b-41d4-a716-446655440000",
  "locale": "en-US",
  "context_tag": "modern"
}
```

### Configuration

Configure webhook URLs in `config/webhooks.php` or via environment variables:

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

### Signature Verification

Outgoing webhooks are signed with HMAC-SHA256 and sent in `X-MovieMind-Webhook-Signature` header:

```php
$signature = hash_hmac('sha256', json_encode($payload), $secret);
```

**Verification on receiving end:**
```php
$providedSignature = $request->header('X-MovieMind-Webhook-Signature');
$expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

if (!hash_equals($expectedSignature, $providedSignature)) {
    // Invalid signature
}
```

### Retry Mechanism

Outgoing webhooks use exponential backoff for retries:

- **Attempt 1:** 1 minute delay
- **Attempt 2:** 5 minutes delay
- **Attempt 3:** 15 minutes delay

Failed webhooks are automatically retried via `SendOutgoingWebhookJob`.

### Status Tracking

Outgoing webhooks are tracked in the `outgoing_webhooks` table:

- `pending` - Webhook created, not yet sent
- `sent` - Webhook successfully delivered
- `failed` - Webhook delivery failed, will retry
- `permanently_failed` - Webhook failed after max attempts

### Multiple URLs

You can configure multiple URLs per event type. All URLs will receive the webhook:

```php
'outgoing_urls' => [
    'movie.generation.completed' => [
        'https://webhook1.example.com',
        'https://webhook2.example.com',
    ],
],
```

## Feature Flag

Notification webhooks are controlled by the `webhook_notifications` feature flag:

```php
use Laravel\Pennant\Feature;

if (Feature::active('webhook_notifications')) {
    // Webhook functionality enabled
}
```

## Code Examples

### Sending Outgoing Webhook

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

if ($webhook->isSent()) {
    // Success
} elseif ($webhook->isFailed()) {
    // Failed, will retry automatically
}
```

### Retrying Failed Webhook

```php
use App\Jobs\SendOutgoingWebhookJob;

$webhook = OutgoingWebhook::find($id);

if ($webhook->shouldRetryNow()) {
    SendOutgoingWebhookJob::dispatch($webhook->id)
        ->delay($webhook->next_retry_at);
}
```

### Getting Webhooks Ready for Retry

```php
use App\Services\OutgoingWebhookService;

$webhookService = app(OutgoingWebhookService::class);
$webhooks = $webhookService->getWebhooksReadyForRetry(limit: 100);

foreach ($webhooks as $webhook) {
    $webhookService->retryWebhook($webhook);
}
```

## Testing

### Testing Incoming Webhooks

```php
use Tests\TestCase;

class NotificationWebhooksTest extends TestCase
{
    public function test_webhook_handles_generation_completed_event(): void
    {
        Config::set('webhooks.verify_notification_signature', false);

        $response = $this->postJson('/api/v1/webhooks/notification', [
            'event' => 'generation.completed',
            'data' => [
                'entity_type' => 'MOVIE',
                'entity_id' => 'the-matrix-1999',
            ],
            'idempotency_key' => 'test-key-1',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }
}
```

### Testing Outgoing Webhooks

**Manual testing:** To receive and inspect outgoing webhook requests without running your own server, you can use:

- **[Webhook.cool](https://webhook.cool/)** – generates a unique URL; requests are visible in the browser (URLs expire after 7 days of inactivity).
- **[Webhook.site](https://webhook.site/)** – similar service; view request history, optional account for more requests.

Set `WEBHOOK_URL_MOVIE_GENERATION_COMPLETED` (or another `WEBHOOK_URL_*`) in `.env` to the URL provided by either service, then trigger a generation; the POST will appear in the tester.

**Automated testing (fake HTTP):**

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'https://example.com/webhook' => Http::response(['status' => 'ok'], 200),
]);

Event::dispatch(new MovieGenerationRequested(
    slug: 'the-matrix-1999',
    jobId: '550e8400-e29b-41d4-a716-446655440000'
));

Http::assertSent(function ($request) {
    return $request->url() === 'https://example.com/webhook';
});
```

## Best Practices

1. **Always use idempotency keys** - Prevents duplicate processing
2. **Verify signatures** - Always verify webhook signatures for security
3. **Handle errors gracefully** - Return appropriate HTTP status codes
4. **Monitor failed webhooks** - Set up alerts for permanently failed webhooks
5. **Test retry mechanism** - Ensure retry works correctly
6. **Use HTTPS** - Always use HTTPS for webhook URLs
7. **Log webhook events** - Log all webhook events for debugging

## Related Documentation

- [Webhook System Architecture](./WEBHOOK_SYSTEM.md)
- [QA Testing Guide](../../qa/NOTIFICATION_WEBHOOKS_QA_GUIDE.md)
- [Business Documentation](../../business/WEBHOOK_SYSTEM_BUSINESS.md)

---

**Last updated:** 2026-01-07

