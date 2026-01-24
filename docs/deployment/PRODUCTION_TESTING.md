# Production Deployment & Testing Guide

This document outlines the procedures for deploying the MovieMind API to production/staging environments and performing verification tests.

## 1. Environment Configuration

Ensure the following environment variables are securely configured in your production environment:

### Admin User Seeding
- `ADMIN_EMAIL`: Email address for the admin user (e.g., `admin@yourdomain.com`)
- `ADMIN_NAME`: Full name of the admin user
- `ADMIN_PASSWORD`: Strong password for the admin account

### Horizon Authentication
- `HORIZON_BASIC_AUTH_PASSWORD`: Password for accessing the Horizon dashboard
- `HORIZON_ALLOWED_EMAILS`: Emails allowed to access Horizon

### Feature Flags
- `DEBUG_ENDPOINTS`: Set to `false` in production.

## 2. Deployment Steps

1.  **Build & Push**: Build Docker images.
2.  **Migration & Seeding**:
    Run in production container:
    ```bash
    php artisan migrate --seed --force
    ```

3.  **Restart Services**:
    ```bash
    php artisan horizon:terminate
    ```

## 3. Verification & Smoke Tests

### 3.1 Verify Admin Access
1.  Navigate to `/admin`.
2.  Log in using `ADMIN_EMAIL` and `ADMIN_PASSWORD`.
3.  **Pass Criteria**: Successful login.

### 3.2 Verify Subscription Plans
1.  Go to **Subscription Plans**.
2.  Ensure existing plans (`free`, `pro`, `enterprise`) are present.
3.  **Pass Criteria**: Plans listed correctly.

### 3.3 Verify API Key Management
1.  Go to **API Keys** -> **Create API Key**.
2.  Enter details and create.
3.  **Pass Criteria**:
    - Success notification.
    - **Full API key** displayed once.
    - Key appears in list.

### 3.4 API Health Check
```bash
curl -I https://your-production-url.com/api/v1/health
```
**Pass Criteria**: HTTP `200 OK`.

## 4. Troubleshooting

- **Admin Login Fails**: Check env vars. Reseed/update user if needed.
- **502 Bad Gateway**: Check Nginx/PHP-FPM logs.
