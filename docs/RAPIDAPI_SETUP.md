# RapidAPI Setup Guide

This guide explains how to set up and configure MovieMind API for publication on RapidAPI Hub.

## 📋 Prerequisites

- MovieMind API deployed and accessible
- RapidAPI Hub account
- Admin access to MovieMind API configuration

## 🔧 Configuration Steps

### 1. Environment Variables

Add the following environment variables to your `.env` file:

```bash
# RapidAPI Proxy Secret (obtained from RapidAPI Hub)
RAPIDAPI_PROXY_SECRET=your-proxy-secret-here

# RapidAPI Webhook Secret (for webhook signature verification)
RAPIDAPI_WEBHOOK_SECRET=your-webhook-secret-here

# Enable proxy secret verification (default: true)
RAPIDAPI_VERIFY_PROXY_SECRET=true

# Enable webhook signature verification (default: true)
RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE=true

# Log RapidAPI requests (default: false, useful for debugging)
RAPIDAPI_LOG_REQUESTS=false
```

### 2. RapidAPI Hub Configuration

#### 2.1. Register Your API

1. Log in to [RapidAPI Hub](https://rapidapi.com/hub)
2. Navigate to "My APIs" → "Add New API"
3. Fill in the API details:
   - **Name**: MovieMind API
   - **Description**: AI-powered movie, TV series, and people descriptions API
   - **Base URL**: Your production API URL (e.g., `https://api.moviemind.com/api`)
   - **OpenAPI Spec**: Upload `docs/openapi.yaml`

#### 2.2. Configure Subscription Plans

In RapidAPI Hub, configure the following plans:

- **Basic Plan** (maps to our Free plan)
  - Monthly limit: 100 requests
  - Rate limit: 10 requests/minute
  - Price: Free

- **Pro Plan** (maps to our Pro plan)
  - Monthly limit: 10,000 requests
  - Rate limit: 60 requests/minute
  - Price: $X/month (set your price)

- **Ultra Plan** (maps to our Enterprise plan)
  - Monthly limit: Unlimited
  - Rate limit: Unlimited
  - Price: $Y/month (set your price)

**Important**: Plan names in RapidAPI Hub must match exactly:
- `basic` → maps to our `free` plan
- `pro` → maps to our `pro` plan
- `ultra` → maps to our `enterprise` plan

#### 2.3. Configure Webhooks

1. In RapidAPI Hub, navigate to "Webhooks" section
2. Add webhook endpoint: `https://your-api.com/api/v1/webhooks/billing`
3. Configure events to subscribe to:
   - `subscription.created`
   - `subscription.updated`
   - `subscription.cancelled`
   - `payment.succeeded`
   - `payment.failed`
4. Copy the webhook secret and add it to your `.env` file as `RAPIDAPI_WEBHOOK_SECRET`

### 3. API Endpoints Configuration

#### 3.1. Public Endpoints (Require API Key)

All public endpoints require API key authentication via one of:
- Header: `X-RapidAPI-Key: <your-api-key>`
- Header: `Authorization: Bearer <your-api-key>`

**Endpoints:**
- `GET /v1/movies` - List movies
- `GET /v1/movies/{slug}` - Get movie by slug
- `GET /v1/movies/search` - Search movies
- `GET /v1/people` - List people
- `GET /v1/people/{slug}` - Get person by slug
- `GET /v1/tv-series` - List TV series
- `GET /v1/tv-series/{slug}` - Get TV series by slug
- `GET /v1/tv-shows` - List TV shows
- `GET /v1/tv-shows/{slug}` - Get TV show by slug
- `POST /v1/generate` - Queue AI content generation
- `GET /v1/jobs/{id}` - Get job status

#### 3.2. Admin Endpoints (Protected by Basic Auth)

Admin endpoints are protected by HTTP Basic Authentication and should NOT be exposed through RapidAPI.

**Endpoints:**
- `GET /v1/admin/analytics/*` - Analytics endpoints
- `GET /v1/admin/api-keys/*` - API key management
- `GET /v1/admin/flags/*` - Feature flag management

### 4. Rate Limiting

Rate limits are automatically enforced based on the subscription plan:

- **Free Plan**: 100 requests/month, 10 requests/minute
- **Pro Plan**: 10,000 requests/month, 60 requests/minute
- **Enterprise Plan**: Unlimited requests

Rate limit information is returned in response headers:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current period
- `X-RateLimit-Reset`: Timestamp when rate limit resets

### 5. Testing

#### 5.1. Test in Staging

1. Set up a staging environment with RapidAPI integration
2. Test API key authentication
3. Test rate limiting
4. Test webhook delivery
5. Verify plan mapping

#### 5.2. Test Webhooks

Use RapidAPI's webhook testing tool or send test requests:

```bash
curl -X POST https://your-api.com/api/v1/webhooks/billing \
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

### 6. Monitoring

#### 6.1. Enable Request Logging

Set `RAPIDAPI_LOG_REQUESTS=true` in your `.env` to log all RapidAPI requests.

#### 6.2. Monitor Usage

Use the Analytics API endpoints to monitor usage:
- `GET /v1/admin/analytics/overview` - Overall statistics
- `GET /v1/admin/analytics/by-plan` - Usage by plan
- `GET /v1/admin/analytics/by-endpoint` - Top endpoints

#### 6.3. Set Up Alerts

Configure alerts for:
- High error rates
- Rate limit violations
- Webhook delivery failures
- Unusual usage patterns

## ✅ Verification Checklist

- [ ] Environment variables configured
- [ ] API registered in RapidAPI Hub
- [ ] Subscription plans configured
- [ ] Webhooks configured and tested
- [ ] API key authentication working
- [ ] Rate limiting working correctly
- [ ] Plan mapping verified
- [ ] Request logging enabled (optional)
- [ ] Monitoring and alerts configured
- [ ] Documentation updated

## 🔗 Related Documentation

- [RapidAPI Pricing Guide](RAPIDAPI_PRICING.md)
- [RapidAPI Webhooks Guide](RAPIDAPI_WEBHOOKS.md)
- [OpenAPI Specification](openapi.yaml)

