# Application Monitoring Guide

> **Comprehensive guide to monitoring tools and strategies for MovieMind API**

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Monitoring Tools](#monitoring-tools)
   - [Laravel Telescope](#laravel-telescope)
   - [Grafana](#grafana)
   - [Loki](#loki)
   - [Datadog](#datadog)
   - [Zabbix](#zabbix)
   - [ELT Stack](#elt-stack)
3. [Implementation Strategy](#implementation-strategy)
4. [Metrics to Monitor](#metrics-to-monitor)
5. [Alerting](#alerting)
6. [Best Practices](#best-practices)

---

## Overview

Monitoring is essential for maintaining application health, performance, and reliability. This guide covers various monitoring tools and their application to MovieMind API.

### Key Monitoring Areas

- **Application Performance** - Response times, throughput, error rates
- **Infrastructure** - CPU, memory, disk, network
- **Database** - Query performance, connection pools, slow queries
- **Queue Jobs** - Job processing times, failures, backlog
- **External Services** - OpenAI API latency, TMDb API availability
- **Business Metrics** - API usage, generation success rates, user activity

---

## Monitoring Tools

### Laravel Telescope

**Purpose:** Real-time application debugging and monitoring for Laravel applications.

**Use Cases:**
- Debug requests, responses, and exceptions
- Monitor database queries and performance
- Track queue jobs and failures
- View cache operations
- Inspect logged events and listeners

**Installation:**

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Configuration:**

```php
// config/telescope.php
'watchers' => [
    Watchers\CacheWatcher::class => env('TELESCOPE_CACHE_WATCHER', true),
    Watchers\CommandWatcher::class => env('TELESCOPE_COMMAND_WATCHER', true),
    Watchers\DumpWatcher::class => env('TELESCOPE_DUMP_WATCHER', true),
    Watchers\EventWatcher::class => env('TELESCOPE_EVENT_WATCHER', true),
    Watchers\ExceptionWatcher::class => env('TELESCOPE_EXCEPTION_WATCHER', true),
    Watchers\JobWatcher::class => env('TELESCOPE_JOB_WATCHER', true),
    Watchers\LogWatcher::class => env('TELESCOPE_LOG_WATCHER', true),
    Watchers\MailWatcher::class => env('TELESCOPE_MAIL_WATCHER', true),
    Watchers\ModelWatcher::class => env('TELESCOPE_MODEL_WATCHER', true),
    Watchers\NotificationWatcher::class => env('TELESCOPE_NOTIFICATION_WATCHER', true),
    Watchers\QueryWatcher::class => env('TELESCOPE_QUERY_WATCHER', true),
    Watchers\RedisWatcher::class => env('TELESCOPE_REDIS_WATCHER', true),
    Watchers\RequestWatcher::class => env('TELESCOPE_REQUEST_WATCHER', true),
    Watchers\GateWatcher::class => env('TELESCOPE_GATE_WATCHER', true),
    Watchers\ScheduleWatcher::class => env('TELESCOPE_SCHEDULE_WATCHER', true),
    Watchers\ViewWatcher::class => env('TELESCOPE_VIEW_WATCHER', true),
],
```

**Access Control:**

```php
// app/Providers/TelescopeServiceProvider.php
protected function gate(): void
{
    Gate::define('viewTelescope', function ($user) {
        return in_array($user->email, [
            'admin@moviemind.com',
        ]);
    });
}
```

**Access URL:** `http://localhost:8000/telescope`

**Pros:**
- ✅ Native Laravel integration
- ✅ Zero configuration for basic setup
- ✅ Real-time debugging
- ✅ Detailed request/response inspection

**Cons:**
- ❌ Not suitable for production (performance impact)
- ❌ Limited alerting capabilities
- ❌ No long-term data retention

**Recommendation:** Use for **development and staging** environments only.

---

### Grafana

**Purpose:** Open-source analytics and visualization platform for time-series data.

**Use Cases:**
- Create dashboards for application metrics
- Visualize performance trends
- Monitor infrastructure resources
- Track business KPIs

**Architecture:**

```
Application → Prometheus → Grafana
              (metrics)    (visualization)
```

**Installation (Docker):**

```yaml
# docker-compose.yml
services:
  grafana:
    image: grafana/grafana:latest
    ports:
      - "3000:3000"
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=admin
    volumes:
      - grafana-storage:/var/lib/grafana
      - ./grafana/provisioning:/etc/grafana/provisioning
    networks:
      - monitoring

volumes:
  grafana-storage:
```

**Key Metrics to Visualize:**

1. **Application Metrics**
   - Request rate (requests/second)
   - Response time (p50, p95, p99)
   - Error rate (4xx, 5xx)
   - Active users

2. **Queue Metrics**
   - Jobs processed per minute
   - Job failure rate
   - Queue backlog size
   - Average job processing time

3. **Database Metrics**
   - Query execution time
   - Connection pool usage
   - Slow queries count
   - Database size

4. **Infrastructure Metrics**
   - CPU usage
   - Memory usage
   - Disk I/O
   - Network throughput

**Sample Dashboard Queries:**

```promql
# Request rate
rate(http_requests_total[5m])

# Error rate
rate(http_requests_total{status=~"5.."}[5m]) / rate(http_requests_total[5m])

# Average response time
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))
```

**Pros:**
- ✅ Rich visualization options
- ✅ Flexible query language (PromQL)
- ✅ Alerting support
- ✅ Plugin ecosystem

**Cons:**
- ❌ Requires data source (Prometheus, InfluxDB, etc.)
- ❌ Steeper learning curve
- ❌ Resource intensive

**Recommendation:** Use for **production monitoring** with Prometheus as data source.

---

### Loki

**Purpose:** Log aggregation system designed to be cost-effective and easy to operate.

**Use Cases:**
- Centralized log collection
- Log querying and analysis
- Correlation with metrics
- Troubleshooting issues

**Architecture:**

```
Application Logs → Promtail → Loki → Grafana
                  (agent)    (storage) (query)
```

**Installation (Docker):**

```yaml
# docker-compose.yml
services:
  loki:
    image: grafana/loki:latest
    ports:
      - "3100:3100"
    command: -config.file=/etc/loki/local-config.yaml
    volumes:
      - ./loki:/etc/loki
      - loki-storage:/loki
    networks:
      - monitoring

  promtail:
    image: grafana/promtail:latest
    volumes:
      - ./promtail:/etc/promtail
      - /var/log:/var/log:ro
      - /var/lib/docker/containers:/var/lib/docker/containers:ro
    command: -config.file=/etc/promtail/config.yml
    networks:
      - monitoring

volumes:
  loki-storage:
```

**Promtail Configuration:**

```yaml
# promtail/config.yml
server:
  http_listen_port: 9080
  grpc_listen_port: 0

positions:
  filename: /tmp/positions.yaml

clients:
  - url: http://loki:3100/loki/api/v1/push

scrape_configs:
  - job_name: laravel
    static_configs:
      - targets:
          - localhost
        labels:
          job: moviemind-api
          __path__: /var/log/laravel/*.log
```

**LogQL Queries:**

```logql
# Error logs
{job="moviemind-api"} |= "ERROR"

# Slow queries
{job="moviemind-api"} | json | duration > 1s

# Failed jobs
{job="moviemind-api"} |= "Job failed"
```

**Pros:**
- ✅ Cost-effective (no indexing)
- ✅ Fast queries
- ✅ Integrates with Grafana
- ✅ Label-based filtering

**Cons:**
- ❌ Less powerful than ELK stack
- ❌ Limited full-text search
- ❌ Requires Promtail agent

**Recommendation:** Use for **log aggregation** when cost is a concern.

---

### Datadog

**Purpose:** Cloud-based monitoring and analytics platform.

**Use Cases:**
- APM (Application Performance Monitoring)
- Infrastructure monitoring
- Log management
- Real-time alerting
- Distributed tracing

**Installation:**

```bash
# Install Datadog Agent
DD_API_KEY=your_api_key DD_SITE="datadoghq.com" bash -c "$(curl -L https://s3.amazonaws.com/dd-agent/scripts/install_script_agent7.sh)"

# Laravel integration
composer require datadog/dd-trace
```

**Configuration:**

```php
// config/datadog.php
return [
    'enabled' => env('DD_TRACE_ENABLED', true),
    'service' => env('DD_SERVICE', 'moviemind-api'),
    'env' => env('DD_ENV', 'production'),
    'version' => env('DD_VERSION', '1.0.0'),
    'analytics_enabled' => env('DD_TRACE_ANALYTICS_ENABLED', true),
];
```

**Key Features:**

1. **APM**
   - Request tracing
   - Database query tracking
   - External service calls
   - Error tracking

2. **Infrastructure Monitoring**
   - Server metrics
   - Container metrics
   - Network metrics

3. **Log Management**
   - Centralized logging
   - Log parsing
   - Correlation with traces

4. **Alerting**
   - Multi-channel notifications
   - Alert grouping
   - On-call management

**Pros:**
- ✅ All-in-one solution
- ✅ Easy setup
- ✅ Excellent UI/UX
- ✅ Strong alerting

**Cons:**
- ❌ Expensive (SaaS pricing)
- ❌ Vendor lock-in
- ❌ Data privacy concerns

**Recommendation:** Use for **enterprise production** when budget allows.

---

### Zabbix

**Purpose:** Enterprise-class open-source monitoring solution.

**Use Cases:**
- Infrastructure monitoring
- Network monitoring
- Application monitoring
- Capacity planning

**Architecture:**

```
Zabbix Agent → Zabbix Server → Zabbix Frontend
              (data collection)  (database)    (UI)
```

**Installation (Docker):**

```yaml
# docker-compose.yml
services:
  zabbix-server:
    image: zabbix/zabbix-server-pgsql:latest
    environment:
      - DB_SERVER_HOST=postgres
      - POSTGRES_USER=zabbix
      - POSTGRES_PASSWORD=zabbix
      - POSTGRES_DB=zabbix
    ports:
      - "10051:10051"
    networks:
      - monitoring

  zabbix-web:
    image: zabbix/zabbix-web-nginx-pgsql:latest
    environment:
      - DB_SERVER_HOST=postgres
      - POSTGRES_USER=zabbix
      - POSTGRES_PASSWORD=zabbix
      - POSTGRES_DB=zabbix
      - ZBX_SERVER_HOST=zabbix-server
    ports:
      - "8080:8080"
    networks:
      - monitoring
```

**Key Features:**

1. **Monitoring**
   - Server monitoring
   - Network device monitoring
   - Application monitoring
   - Database monitoring

2. **Alerting**
   - Email notifications
   - SMS notifications
   - Webhook integrations
   - Escalation rules

3. **Visualization**
   - Graphs
   - Maps
   - Screens
   - Dashboards

**Pros:**
- ✅ Open-source
- ✅ Highly customizable
- ✅ Strong alerting
- ✅ Mature ecosystem

**Cons:**
- ❌ Complex setup
- ❌ Steep learning curve
- ❌ Resource intensive
- ❌ UI is dated

**Recommendation:** Use for **infrastructure monitoring** in enterprise environments.

---

### ELT Stack

**Purpose:** Elasticsearch, Logstash, and Kibana for log analysis and search.

**Use Cases:**
- Log aggregation and analysis
- Full-text search
- Security analysis
- Business intelligence

**Architecture:**

```
Application → Logstash → Elasticsearch → Kibana
             (ingestion)  (storage)      (visualization)
```

**Installation (Docker):**

```yaml
# docker-compose.yml
services:
  elasticsearch:
    image: docker.elastic.co/elasticsearch/elasticsearch:8.11.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
    ports:
      - "9200:9200"
    volumes:
      - es-data:/usr/share/elasticsearch/data

  logstash:
    image: docker.elastic.co/logstash/logstash:8.11.0
    volumes:
      - ./logstash/pipeline:/usr/share/logstash/pipeline
      - ./logstash/config:/usr/share/logstash/config
    ports:
      - "5044:5044"
      - "9600:9600"

  kibana:
    image: docker.elastic.co/kibana/kibana:8.11.0
    environment:
      - ELASTICSEARCH_HOSTS=http://elasticsearch:9200
    ports:
      - "5601:5601"

volumes:
  es-data:
```

**Logstash Pipeline:**

```ruby
# logstash/pipeline/laravel.conf
input {
  file {
    path => "/var/log/laravel/*.log"
    start_position => "beginning"
    codec => "json"
  }
}

filter {
  if [level] == "ERROR" {
    mutate {
      add_tag => [ "error" ]
    }
  }
  
  date {
    match => [ "timestamp", "ISO8601" ]
  }
}

output {
  elasticsearch {
    hosts => ["elasticsearch:9200"]
    index => "moviemind-api-%{+YYYY.MM.dd}"
  }
}
```

**Kibana Queries:**

```json
// Error logs
level: "ERROR"

// Slow requests
duration: > 1000

// Failed jobs
message: "Job failed"
```

**Pros:**
- ✅ Powerful search capabilities
- ✅ Real-time analysis
- ✅ Scalable
- ✅ Rich visualization

**Cons:**
- ❌ Resource intensive
- ❌ Complex setup
- ❌ Licensing costs (for advanced features)

**Recommendation:** Use for **log analysis** when full-text search is required.

---

## Implementation Strategy

### Phase 1: Development (Current)

**Tools:**
- Laravel Telescope (local debugging)
- Laravel Horizon (queue monitoring)
- Basic logging (Laravel logs)

**Focus:**
- Debugging and development
- Queue job monitoring
- Basic error tracking

### Phase 2: Staging

**Tools:**
- Laravel Telescope (staging environment)
- Grafana + Prometheus (metrics)
- Loki (log aggregation)

**Focus:**
- Performance monitoring
- Error tracking
- Basic alerting

### Phase 3: Production

**Tools:**
- Grafana + Prometheus (metrics)
- Loki (log aggregation)
- AlertManager (alerting)
- Optional: Datadog (if budget allows)

**Focus:**
- Production monitoring
- Alerting
- Performance optimization
- Capacity planning

---

## Metrics to Monitor

### Application Metrics

| Metric | Description | Threshold |
|--------|------------|-----------|
| Request Rate | Requests per second | Alert if > 1000 req/s |
| Response Time (p95) | 95th percentile response time | Alert if > 500ms |
| Error Rate | Percentage of failed requests | Alert if > 1% |
| Active Users | Concurrent active users | Monitor trends |

### Queue Metrics

| Metric | Description | Threshold |
|--------|------------|-----------|
| Jobs Processed | Jobs processed per minute | Monitor trends |
| Job Failure Rate | Percentage of failed jobs | Alert if > 5% |
| Queue Backlog | Number of pending jobs | Alert if > 1000 |
| Average Job Time | Average job processing time | Alert if > 30s |

### Database Metrics

| Metric | Description | Threshold |
|--------|------------|-----------|
| Query Time (p95) | 95th percentile query time | Alert if > 100ms |
| Connection Pool Usage | Percentage of connections used | Alert if > 80% |
| Slow Queries | Number of slow queries per minute | Alert if > 10 |
| Database Size | Total database size | Monitor growth |

### Infrastructure Metrics

| Metric | Description | Threshold |
|--------|------------|-----------|
| CPU Usage | CPU utilization percentage | Alert if > 80% |
| Memory Usage | Memory utilization percentage | Alert if > 85% |
| Disk Usage | Disk space utilization | Alert if > 90% |
| Network Throughput | Network I/O | Monitor trends |

### External Service Metrics

| Metric | Description | Threshold |
|--------|------------|-----------|
| OpenAI API Latency | Response time from OpenAI | Alert if > 5s |
| OpenAI API Error Rate | Percentage of failed calls | Alert if > 2% |
| TMDb API Latency | Response time from TMDb | Alert if > 2s |
| TMDb API Error Rate | Percentage of failed calls | Alert if > 5% |

---

## Alerting

### Alert Channels

1. **Email** - For non-critical alerts
2. **Slack** - For team notifications
3. **PagerDuty** - For critical on-call alerts
4. **SMS** - For emergency alerts

### Alert Severity Levels

- **Critical** - Immediate action required (service down, data loss)
- **Warning** - Attention needed (performance degradation, high error rate)
- **Info** - Informational (capacity planning, trends)

### Sample Alert Rules

```yaml
# Prometheus alert rules
groups:
  - name: moviemind_alerts
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.01
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "High error rate detected"
          description: "Error rate is {{ $value }}%"

      - alert: SlowResponseTime
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 0.5
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Slow response time detected"
          description: "P95 response time is {{ $value }}s"

      - alert: QueueBacklog
        expr: queue_jobs_pending > 1000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Large queue backlog"
          description: "Queue has {{ $value }} pending jobs"
```

---

## Best Practices

### 1. Start Simple

- Begin with basic logging and error tracking
- Add metrics gradually
- Focus on critical paths first

### 2. Use Structured Logging

```php
// Good
Log::info('Job processed', [
    'job_id' => $jobId,
    'entity_type' => $entityType,
    'duration' => $duration,
    'status' => 'success',
]);

// Bad
Log::info("Job $jobId processed successfully");
```

### 3. Monitor Business Metrics

- Track API usage patterns
- Monitor generation success rates
- Measure user engagement

### 4. Set Up Alerting Early

- Configure alerts before issues occur
- Test alert channels regularly
- Review and tune alert thresholds

### 5. Use Correlation IDs

```php
// Add request ID to all logs
Log::withContext(['request_id' => $requestId])
    ->info('Processing request');
```

### 6. Regular Reviews

- Review dashboards weekly
- Analyze trends monthly
- Adjust thresholds based on data

### 7. Cost Optimization

- Use sampling for high-volume metrics
- Archive old logs
- Choose appropriate retention periods

---

## References

- [Laravel Telescope Documentation](https://laravel.com/docs/telescope)
- [Grafana Documentation](https://grafana.com/docs/)
- [Loki Documentation](https://grafana.com/docs/loki/)
- [Datadog Documentation](https://docs.datadoghq.com/)
- [Zabbix Documentation](https://www.zabbix.com/documentation)
- [ELK Stack Documentation](https://www.elastic.co/guide/)

---

**Last updated:** 2026-01-21
