# Horizon Setup & Troubleshooting

## Access & Authentication

### Endpoints
- **Dashboard**: `/horizon` (e.g., `http://localhost:8000/horizon`)

### Authentication Logic
Authentication is handled via `HorizonBasicAuth` middleware and `HorizonServiceProvider` gate.

1.  **Local / Staging**:
    - BYPASS authentication (no login required).
    - Configured via `.env`: `HORIZON_AUTH_BYPASS_ENVS=local,staging`.

2.  **Production**:
    - Requires **Basic Authentication**.
    - **Username**: Must be one of `HORIZON_ALLOWED_EMAILS` (e.g., `admin@moviemind.local`).
    - **Password**: Defined in `HORIZON_BASIC_AUTH_PASSWORD`.

## Troubleshooting

### Issue: Cannot Log In After "Logging Out"

**Problem**:
Since Basic Auth doesn't have a native "Logout" button, users often "logout" by visiting `http://logout@host`.
This causes the browser to cache the invalid `logout` credential.
Subsequent attempts to log in with valid credentials (e.g., `admin@moviemind.local`) fail immediately with `401 Unauthorized` because the browser keeps sending the cached `logout` user.

**Solutions**:

1.  **Force New Credentials via URL**:
    Use a URL that embeds the correct credentials to overwrite the cache:
    ```
    http://admin%40moviemind.local:password123@localhost:8000/horizon
    ```
    *Note: The `@` in the email must be URL-encoded as `%40`.*

2.  **Incognito / Private Mode**:
    Open the dashboard in an Incognito window, which does not share the cached invalid credentials.

3.  **Clear Browser Data**:
    Clear "Site Data" or "Active Logins" for the domain in your browser's Developer Tools.
