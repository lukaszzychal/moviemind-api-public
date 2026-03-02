# Logging and Observability

> **Last updated:** 2025-02-21

---

## Purpose

This document describes the current logging setup, requirements, and options for log aggregation and observability (including containers and application health). It does not cover implementation of new tools; see the linked tasks for that.

---

## Current Application Logging

### Configuration

- **Framework:** Laravel uses Monolog for logging.
- **Config:** `api/config/logging.php`.
- **Default channel:** `LOG_CHANNEL` env (default `stack`).
- **Level:** `LOG_LEVEL` env (e.g. `debug`, `info`, `warning`, `error`).
- **Channels:** `stack`, `single`, `daily`, `slack`, etc. `single` and `daily` write to `storage/logs/laravel.log`; `daily` supports log rotation by day (`LOG_DAILY_DAYS`, default 14).

### Request correlation

- **RequestIdMiddleware** (`api/app/Http/Middleware/RequestIdMiddleware.php`) assigns a correlation ID per request so that log lines from the same request can be grouped (e.g. in an aggregated log system).

### Production log collection (simple setup)

- **Channel `production`** in `api/config/logging.php`: stack of `daily` + `stderr`. Set `LOG_CHANNEL=production` and `LOG_LEVEL=warning` (or `error`) so logs go to rotated files and to container stderr (e.g. `docker compose logs`). See [PRODUCTION_LOGGING.md](../../deployment/PRODUCTION_LOGGING.md).

### What is not defined yet

- No written **policy** for what to log, at which level, retention, or what must never be logged (e.g. secrets). See **TASK-LOG-001**.
- No **central aggregation** of logs (e.g. Loki, ELK, CloudWatch, Papertrail). See **TASK-LOG-002**.
- **Structured (JSON) logging** is not mandated for production; it would ease parsing and aggregation. See **TASK-LOG-003** (optional).

---

## Requirements (Target)

- **Traceability:** Correlation ID on all request-scoped logs.
- **Levels:** Consistent use of `debug` / `info` / `warning` / `error` (and appropriate `LOG_LEVEL` per environment).
- **Security:** No secrets, tokens, or full request/response bodies with sensitive data in logs.
- **Retention and rotation:** Defined retention and rotation (e.g. daily files, max days) to avoid unbounded disk usage.

---

## Aggregation and Analysis

- **Current:** Logs stay on the host (e.g. in `storage/logs/`); no central aggregation.
- **Options (for future evaluation):** Loki, ELK stack, AWS CloudWatch, Papertrail, or similar – to collect, search, and dashboard logs from the application and, if needed, from containers. **TASK-LOG-002** covers requirements, options, and a recommendation.

---

## Application and Container State

- **Health:** `/api/v1/health` endpoint exists for basic application health checks.
- **Queues:** Laravel Horizon is used for queue monitoring and worker state.
- **Not yet in place:**
  - No application-level metrics (e.g. Prometheus) exposing rate limits, external API errors, or 429s.
  - No unified view of logs from all containers (e.g. PHP-FPM, Nginx, app) in one pipeline; Docker logs and host logs are separate unless an aggregation pipeline is added.

---

## Recommendations

1. **Policy:** Define and document a logging policy (format, levels, retention, what not to log) and add it to this document. **TASK-LOG-001**.
2. **Aggregation:** Evaluate log aggregation and container log collection (Loki/ELK/CloudWatch, etc.) and document requirements and a recommended option. **TASK-LOG-002**.
3. **Structured logging:** Consider JSON (structured) logging for production and document the format; implement and/or add aggregation pipeline after the decision. **TASK-LOG-003** (optional).
4. **Metrics (optional):** Longer term, consider application metrics (e.g. Prometheus) and alerts (e.g. on 429s or repeated failures from external APIs).

---

## References

- `api/config/logging.php` – Laravel log channels and levels.
- `api/app/Http/Middleware/RequestIdMiddleware.php` – correlation ID.
- Health endpoint: `/api/v1/health`.
- Horizon: queue and worker monitoring.
