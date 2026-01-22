# Subscription System Documentation

## Overview

MovieMind API uses a **local API key-based subscription system** for portfolio/demo purposes. Subscriptions are managed through API keys assigned to subscription plans.

**Note:** This system is designed for portfolio/demo. For production deployment, billing providers (Stripe, PayPal, etc.) can be integrated.

---

## Architecture

### Components

1. **Subscription Plans** (`SubscriptionPlan` model)
   - Defines plan limits and features
   - Plans: Free, Pro, Enterprise
   - Seeded via `SubscriptionPlanSeeder`

2. **API Keys** (`ApiKey` model)
   - Each API key is assigned to a subscription plan
   - Rate limiting is based on the plan assigned to the API key
   - Created via Admin API or `ApiKeySeeder` (demo)

3. **Subscriptions** (`Subscription` model)
   - Optional: Tracks subscription periods and status
   - Can be created manually for billing tracking
   - Not required for rate limiting (rate limiting uses `ApiKey->plan_id` directly)

---

## Subscription Plans

### Free Plan
- **Monthly Limit:** 100 requests
- **Rate Limit:** 10 requests/minute
- **Features:** `read`
- **Price:** $0

### Pro Plan
- **Monthly Limit:** 10,000 requests
- **Rate Limit:** 100 requests/minute
- **Features:** `read`, `generate`, `context_tags`
- **Price:** Demo (portfolio)

### Enterprise Plan
- **Monthly Limit:** Unlimited (0 = unlimited)
- **Rate Limit:** 1,000 requests/minute
- **Features:** `read`, `generate`, `context_tags`, `webhooks`, `analytics`
- **Price:** Demo (portfolio)

---

## API Key Management

### Creating API Keys

**Via Admin API:**
```bash
POST /api/v1/admin/api-keys
{
  "name": "My API Key",
  "plan_id": "<plan-uuid>"
}
```

**Via Seeder (Demo):**
```bash
php artisan db:seed --class=ApiKeySeeder
```

This creates demo API keys for each plan:
- `Demo Free Plan Key`
- `Demo Pro Plan Key`
- `Demo Enterprise Plan Key`

### API Key Format

- **Prefix:** `mm_`
- **Format:** `mm_<40-random-chars>`
- **Storage:** Hashed in database (plaintext shown only once on creation)

### Authentication

API keys are authenticated via:
- `X-API-Key: <your-api-key>` (standard)
- `X-RapidAPI-Key: <your-api-key>` (legacy support)
- `Authorization: Bearer <your-api-key>` (alternative)

---

## Rate Limiting

Rate limiting is enforced by `PlanBasedRateLimit` middleware:

1. **Monthly Limit:** Based on `SubscriptionPlan->monthly_limit`
2. **Per-Minute Limit:** Based on `SubscriptionPlan->rate_limit_per_minute`
3. **Usage Tracking:** Tracked via `ApiUsage` model

**Rate Limit Headers:**
- `X-RateLimit-Monthly-Limit`: Maximum requests per month
- `X-RateLimit-Monthly-Used`: Requests used this month
- `X-RateLimit-Monthly-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Timestamp when limit resets

---

## Subscription Model (Optional)

The `Subscription` model is available for tracking subscription periods and status, but is **not required** for rate limiting.

**When to use:**
- Tracking subscription periods (start/end dates)
- Managing subscription status (active, cancelled, expired)
- Future billing provider integration

**Note:** Rate limiting works directly from `ApiKey->plan_id`, so subscriptions are optional.

---

## Demo Setup

### 1. Seed Subscription Plans

```bash
php artisan db:seed --class=SubscriptionPlanSeeder
```

### 2. Seed Demo API Keys

```bash
php artisan db:seed --class=ApiKeySeeder
```

This creates demo API keys for each plan. **Save the plaintext keys** - they're only shown once!

### 3. Use API Keys

```bash
curl -H "X-API-Key: mm_<your-key>" http://localhost:8000/api/v1/movies
```

---

## Production Deployment

For production, you can:

1. **Keep Local API Keys:** Continue using local API key management
2. **Integrate Billing Provider:** Add Stripe/PayPal integration
   - Create subscriptions via billing provider webhooks
   - Link API keys to subscriptions
   - Use `BillingService` for subscription management

**Billing Webhook Endpoint:**
- `POST /api/v1/admin/billing/webhook` (currently returns 501 - prepared for future providers)

---

## Related Documentation

- **API Specification:** `docs/openapi.yaml`
- **Admin API:** Admin endpoints for API key management
- **Rate Limiting:** `docs/knowledge/technical/` (if exists)
- **Billing Service:** `api/app/Services/BillingService.php`

---

**Last Updated:** 2025-01-21  
**Project:** MovieMind API (Portfolio/Demo)
