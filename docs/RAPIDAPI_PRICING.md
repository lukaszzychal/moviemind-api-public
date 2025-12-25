# RapidAPI Pricing Guide

This document outlines the pricing structure for MovieMind API on RapidAPI Hub.

## 💰 Subscription Plans

### Free Plan (Basic)

**Price**: Free

**Limits:**
- Monthly requests: 100
- Rate limit: 10 requests/minute
- Features: All core endpoints

**Best for**: Testing, development, low-volume applications

### Pro Plan

**Price**: $X/month (to be determined)

**Limits:**
- Monthly requests: 10,000
- Rate limit: 60 requests/minute
- Features: All core endpoints, priority support

**Best for**: Production applications, moderate traffic

### Enterprise Plan (Ultra)

**Price**: $Y/month (to be determined)

**Limits:**
- Monthly requests: Unlimited
- Rate limit: Unlimited
- Features: All core endpoints, priority support, custom integrations

**Best for**: High-volume applications, enterprise customers

## 📊 Plan Comparison

| Feature | Free | Pro | Enterprise |
|---------|------|-----|------------|
| Monthly Requests | 100 | 10,000 | Unlimited |
| Rate Limit (per minute) | 10 | 60 | Unlimited |
| API Endpoints | All | All | All |
| Priority Support | ❌ | ✅ | ✅ |
| Custom Integrations | ❌ | ❌ | ✅ |
| Analytics Dashboard | ❌ | ✅ | ✅ |

## 🔄 Plan Mapping

RapidAPI plans are automatically mapped to our internal plans:

- **RapidAPI `basic`** → **Internal `free`**
- **RapidAPI `pro`** → **Internal `pro`**
- **RapidAPI `ultra`** → **Internal `enterprise`**

## 💳 Billing

### Payment Methods

RapidAPI handles all billing and payments. Users subscribe through RapidAPI Hub and are charged according to their selected plan.

### Billing Cycle

- Plans are billed monthly
- Billing starts when subscription is created
- Cancellations take effect at the end of the billing period

### Refunds

Refund policy is determined by RapidAPI's terms of service.

## 📈 Usage Tracking

Usage is tracked per API key and subscription plan. You can monitor usage through:

1. **RapidAPI Dashboard**: View usage statistics in RapidAPI Hub
2. **Analytics API**: Use our analytics endpoints to get detailed statistics
3. **Admin Dashboard**: Access detailed analytics via `/v1/admin/analytics/*`

## ⚠️ Rate Limit Exceeded

When rate limits are exceeded, the API returns:

```json
{
  "error": "Rate limit exceeded",
  "message": "You have exceeded your rate limit. Please try again later.",
  "retry_after": 60
}
```

With HTTP status code `429 Too Many Requests`.

Response headers include:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: 0 (no requests remaining)
- `X-RateLimit-Reset`: Timestamp when limit resets

## 🔗 Related Documentation

- [RapidAPI Setup Guide](RAPIDAPI_SETUP.md)
- [RapidAPI Webhooks Guide](RAPIDAPI_WEBHOOKS.md)

