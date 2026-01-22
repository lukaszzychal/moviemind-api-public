# MovieMind API - Subscription Plans

> **For:** Business Stakeholders, Product Managers, Sales  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides detailed information about MovieMind API subscription plans, including features, limits, pricing, and use cases.

**Note:** For portfolio/demo, subscriptions are managed locally via API keys. For production deployment, billing providers (Stripe, PayPal) can be integrated.

---

## 📊 Plan Comparison

| Feature | Free | Pro | Enterprise |
|---------|------|-----|------------|
| **Monthly Requests** | 100 | 10,000 | Unlimited |
| **Rate Limit (per minute)** | 10 | 100 | 1,000 |
| **Read Access** | ✅ | ✅ | ✅ |
| **AI Generation** | ❌ | ✅ | ✅ |
| **Context Tags** | ❌ | ✅ | ✅ |
| **Webhooks** | ❌ | ❌ | ✅ |
| **Analytics** | ❌ | ❌ | ✅ |
| **Bulk Operations** | ❌ | ✅ | ✅ |
| **Priority Support** | ❌ | ❌ | ✅ |
| **Price (Portfolio/Demo)** | $0 | Demo | Demo |
| **Price (Production)** | $0 | TBD | TBD |

---

## 🆓 Free Plan

### Overview

The Free plan is designed for developers, students, and small projects that need basic access to movie and person data.

### Limits

- **Monthly Requests:** 100 requests/month
- **Rate Limit:** 10 requests/minute
- **Burst Limit:** 20 requests (short-term burst allowed)

### Features

- ✅ **Read Access:** Access to all movie, person, TV series, and TV show data
- ✅ **Search:** Full-text search across all entity types
- ✅ **Related Content:** Get related movies, people, etc.
- ✅ **Health Checks:** Monitor API and external service status
- ❌ **AI Generation:** Not available
- ❌ **Context Tags:** Not available
- ❌ **Bulk Operations:** Not available

### Use Cases

- **Personal Projects:** Hobby projects, learning, experimentation
- **Student Projects:** Academic projects, coursework
- **Prototyping:** Early-stage development and testing
- **Low-Volume Applications:** Applications with minimal API usage

### Examples

**Get Movie:**
```bash
GET /api/v1/movies/the-matrix-1999
X-API-Key: mm_free_plan_key_123
```

**Search Movies:**
```bash
GET /api/v1/movies/search?q=matrix
X-API-Key: mm_free_plan_key_123
```

### Limitations

- No AI-generated content
- Limited to 100 requests per month
- No advanced features (bulk, webhooks, analytics)
- No priority support

---

## 💼 Pro Plan

### Overview

The Pro plan is designed for production applications, startups, and businesses that need AI-generated content and higher usage limits.

### Limits

- **Monthly Requests:** 10,000 requests/month
- **Rate Limit:** 100 requests/minute
- **Burst Limit:** 200 requests (short-term burst allowed)

### Features

- ✅ **Read Access:** All Free plan features
- ✅ **AI Generation:** Generate unique descriptions and biographies
- ✅ **Context Tags:** Multiple description styles (modern, critical, humorous, etc.)
- ✅ **Multi-language:** Content generation in 5+ languages
- ✅ **Bulk Operations:** Retrieve multiple entities in single request
- ✅ **Comparison:** Compare multiple entities side-by-side
- ❌ **Webhooks:** Not available
- ❌ **Analytics:** Not available

### Use Cases

- **Production Applications:** Live applications serving real users
- **Startups:** Early-stage companies with growing user base
- **Content Platforms:** Applications that need unique, AI-generated content
- **Multi-language Applications:** Applications serving international audiences

### Examples

**Generate Movie Description:**
```bash
POST /api/v1/generate
X-API-Key: mm_pro_plan_key_123
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

**Bulk Retrieve Movies:**
```bash
POST /api/v1/movies/bulk
X-API-Key: mm_pro_plan_key_123
{
  "slugs": ["the-matrix-1999", "inception-2010", "interstellar-2014"]
}
```

### Benefits

- 100x more requests than Free plan
- AI-generated content for unique descriptions
- Multiple languages and styles
- Bulk operations for efficiency

---

## 🏢 Enterprise Plan

### Overview

The Enterprise plan is designed for large-scale applications, enterprises, and high-volume use cases that need unlimited access and advanced features.

### Limits

- **Monthly Requests:** Unlimited (0 = unlimited)
- **Rate Limit:** 1,000 requests/minute
- **Burst Limit:** 2,000 requests (short-term burst allowed)

### Features

- ✅ **All Pro Plan Features:** Everything in Pro plan
- ✅ **Webhooks:** Real-time notifications for events
- ✅ **Analytics:** Detailed usage and performance analytics
- ✅ **Priority Support:** Dedicated support channel
- ✅ **Custom Integrations:** Support for custom requirements
- ✅ **SLA Guarantees:** Service level agreements (production)

### Use Cases

- **Enterprise Applications:** Large-scale business applications
- **High-Volume Platforms:** Platforms with millions of users
- **Real-time Systems:** Systems requiring webhook notifications
- **Analytics-Driven Applications:** Applications needing detailed analytics

### Examples

**Webhook Configuration:**
```bash
POST /api/v1/admin/webhooks
X-API-Key: mm_enterprise_plan_key_123
{
  "url": "https://example.com/webhook",
  "events": ["subscription.created", "subscription.updated"]
}
```

**Analytics Query:**
```bash
GET /api/v1/admin/analytics/overview
X-API-Key: mm_enterprise_plan_key_123
```

### Benefits

- Unlimited requests
- Highest rate limits
- Advanced features (webhooks, analytics)
- Priority support
- Custom integrations

---

## 🔄 Plan Migration

### Upgrading Plans

**From Free to Pro:**
- Contact admin to upgrade API key plan
- New limits apply immediately
- Previous usage history preserved

**From Pro to Enterprise:**
- Contact admin to upgrade API key plan
- Unlimited requests available immediately
- Webhooks and analytics enabled

### Downgrading Plans

**From Pro to Free:**
- Contact admin to downgrade API key plan
- Limits apply immediately
- AI generation features disabled

**From Enterprise to Pro:**
- Contact admin to downgrade API key plan
- Limits apply immediately
- Webhooks and analytics disabled

---

## 💰 Pricing (Portfolio/Demo)

### Current Pricing

**Portfolio/Demo:**
- **Free:** $0 (always free)
- **Pro:** Demo (no charge for portfolio)
- **Enterprise:** Demo (no charge for portfolio)

**Note:** For portfolio/demo purposes, all plans are available at no charge. API keys are managed locally via Admin API or seeders.

---

## 💰 Pricing (Production - Future)

### Planned Pricing

**Production (when billing provider integrated):**
- **Free:** $0/month (always free)
- **Pro:** $29/month (estimated)
- **Enterprise:** Custom pricing (contact sales)

**Billing:**
- Monthly subscription
- Billed via Stripe/PayPal (when integrated)
- Automatic renewal
- Cancel anytime

**Note:** Production pricing is subject to change. Contact sales for current pricing and enterprise quotes.

---

## 📈 Usage Scenarios

### Scenario 1: Personal Project (Free Plan)

**Use Case:**  
Building a personal movie recommendation app.

**Monthly Usage:** ~50 requests
- 20 movie searches
- 20 movie details
- 10 related movies

**Plan:** Free (100 requests/month) ✅

---

### Scenario 2: Startup MVP (Pro Plan)

**Use Case:**  
Launching a movie review platform with AI-generated descriptions.

**Monthly Usage:** ~5,000 requests
- 2,000 movie searches
- 2,000 movie details (with AI generation)
- 500 bulk operations
- 500 related content queries

**Plan:** Pro (10,000 requests/month) ✅

---

### Scenario 3: Enterprise Platform (Enterprise Plan)

**Use Case:**  
Large-scale content platform serving millions of users.

**Monthly Usage:** 500,000+ requests
- 200,000 movie searches
- 200,000 movie details
- 50,000 bulk operations
- 50,000 webhook events

**Plan:** Enterprise (unlimited) ✅

---

## 🔐 API Key Management

### Creating API Keys

**Via Admin API:**
```bash
POST /api/v1/admin/api-keys
Authorization: Basic <admin-credentials>
{
  "name": "My Production API Key",
  "plan_id": "<pro-plan-uuid>"
}
```

**Response:**
```json
{
  "id": "api-key-uuid",
  "name": "My Production API Key",
  "key": "mm_abc123def456...",
  "plan": "pro",
  "created_at": "2026-01-21T10:00:00Z"
}
```

**⚠️ Important:** The API key is only shown once during creation. Store it securely.

### Revoking API Keys

```bash
POST /api/v1/admin/api-keys/{id}/revoke
Authorization: Basic <admin-credentials>
```

### Regenerating API Keys

```bash
POST /api/v1/admin/api-keys/{id}/regenerate
Authorization: Basic <admin-credentials>
```

**Response:**
```json
{
  "id": "api-key-uuid",
  "name": "My Production API Key",
  "key": "mm_new_key_xyz789...",
  "plan": "pro",
  "created_at": "2026-01-21T10:00:00Z"
}
```

---

## 📊 Rate Limiting

### How Rate Limiting Works

Rate limits are enforced per API key based on the subscription plan:

1. **Per-Minute Limit:** Maximum requests allowed per minute
2. **Monthly Limit:** Maximum requests allowed per month
3. **Burst Limit:** Short-term burst allowance (2x per-minute limit)

### Rate Limit Headers

All API responses include rate limit information:

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642684800
```

### Rate Limit Exceeded

When rate limit is exceeded:

**Response:** `429 Too Many Requests`
```json
{
  "error": "Rate limit exceeded",
  "message": "You have exceeded your rate limit of 100 requests/minute",
  "retry_after": 60
}
```

### Best Practices

- **Monitor Headers:** Check `X-RateLimit-Remaining` to avoid hitting limits
- **Implement Backoff:** Use exponential backoff when rate limited
- **Cache Responses:** Cache API responses to reduce requests
- **Use Bulk Operations:** Use bulk endpoints when retrieving multiple entities

---

## 🎯 Choosing the Right Plan

### Choose Free If:
- ✅ Building personal/hobby projects
- ✅ Learning and experimentation
- ✅ Low-volume usage (<100 requests/month)
- ✅ Don't need AI generation

### Choose Pro If:
- ✅ Building production applications
- ✅ Need AI-generated content
- ✅ Moderate usage (1,000-10,000 requests/month)
- ✅ Need multiple languages and styles

### Choose Enterprise If:
- ✅ High-volume applications (10,000+ requests/month)
- ✅ Need webhooks for real-time notifications
- ✅ Need detailed analytics
- ✅ Require priority support
- ✅ Need custom integrations

---

## 📞 Support

### Free Plan
- Community support (GitHub issues)
- Documentation and guides

### Pro Plan
- Email support (response within 48 hours)
- Documentation and guides
- Community support

### Enterprise Plan
- Priority email support (response within 24 hours)
- Dedicated support channel
- Custom integration support
- SLA guarantees

---

## 🔗 Related Documentation

- [Subscription System](SUBSCRIPTION_SYSTEM.md) - Technical documentation
- [Features](FEATURES.md) - Complete feature list
- [Requirements](REQUIREMENTS.md) - Requirements specification

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project
