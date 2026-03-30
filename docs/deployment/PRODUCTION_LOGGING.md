# Production Logging and Log Collection

> **For:** DevOps, SRE  
> **Related:** [LOGGING_AND_OBSERVABILITY.md](../knowledge/technical/LOGGING_AND_OBSERVABILITY.md)

---

## 1. Recommended production settings

Set in `api/.env` (or your production env source):

```env
LOG_CHANNEL=production
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
```

- **LOG_CHANNEL=production** – Uses the `production` stack in `api/config/logging.php`: writes to **daily** files in `storage/logs/` and to **stderr**, so both file-based and container logs are available.
- **LOG_LEVEL=warning** – Reduces noise (only warning and above). Use `error` for even less volume.
- **LOG_DAILY_DAYS=14** – Keeps 14 days of daily log files; older files are removed automatically.

---

## 2. Where logs are written

| Source              | Location / output        | Notes                                      |
|---------------------|--------------------------|--------------------------------------------|
| Laravel app         | `api/storage/logs/`      | Daily files, e.g. `laravel-2025-02-23.log` |
| Laravel app (stdout)| Container stderr         | When `LOG_CHANNEL=production`              |
| Horizon             | Supervisor / container   | `horizon.log` via Supervisor in same container |
| Nginx               | Container stdout/stderr  | Access via `docker compose logs php`      |

---

## 3. Simple log collection options

### Option A: Docker / Compose (no extra tools)

- **View app + Nginx + Horizon:**  
  `docker compose -f compose.production.yml logs -f php`
- Logs also remain in `storage/logs/` inside the container. To access from the host, mount the log directory:

  In `compose.production.yml` (optional):

  ```yaml
  php:
    volumes:
      - ./logs:/var/www/html/storage/logs
  ```

  Then on the host: `tail -f logs/laravel-*.log` or ship `./logs` with any log shipper.

### Option B: Host volume + logrotate (file-only)

1. Mount `storage/logs` to a host path (as above).
2. Use **logrotate** on the host to rotate/compress files under that path (Laravel daily driver already rotates by day; logrotate can add compression or retention).
3. Optionally run a log shipper (e.g. Filebeat, Fluentd, rsyslog) that reads from that path and sends to your backend (e.g. Loki, CloudWatch, ELK).

### Option C: Papertrail / Syslog (hosted)

1. Set in env: `LOG_CHANNEL=papertrail`, plus `PAPERTRAIL_URL` and `PAPERTRAIL_PORT` (see `api/config/logging.php`).
2. Logs are sent over UDP/TLS to Papertrail; no file collection on the host needed.

### Option D: Stderr only (e.g. Kubernetes)

- Set `LOG_CHANNEL=stderr` so all Laravel logs go to stderr.
- Rely on your cluster’s log collector (e.g. Fluentd, Cloud Logging) to collect stdout/stderr from the container. No need to mount `storage/logs` for collection.

---

## 4. What not to log

- Do **not** log secrets, API keys, passwords, or full request/response bodies with credentials.
- `RequestIdMiddleware` adds a correlation ID; use it when correlating logs with requests.
- For more detail, see [LOGGING_AND_OBSERVABILITY.md](../knowledge/technical/LOGGING_AND_OBSERVABILITY.md).

---

## 5. Retention and disk

- **Daily channel:** Laravel deletes files older than `LOG_DAILY_DAYS` (default 14).
- In the production image, the entrypoint cleans old logs under `storage/logs` (e.g. files older than 7 days) to limit disk use. Adjust or disable that cleanup if you rely on long retention.
