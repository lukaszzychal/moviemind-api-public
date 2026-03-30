# Przewodnik Monitoringu Aplikacji

> **Kompleksowy przewodnik po narzędziach i strategiach monitoringu dla MovieMind API**

---

## 📋 Spis Treści

1. [Przegląd](#przegląd)
2. [Narzędzia Monitoringu](#narzędzia-monitoringu)
   - [Laravel Telescope](#laravel-telescope)
   - [Grafana](#grafana)
   - [Loki](#loki)
   - [Datadog](#datadog)
   - [Zabbix](#zabbix)
   - [Stos ELT](#stos-elt)
3. [Strategia Implementacji](#strategia-implementacji)
4. [Metryki do Monitorowania](#metryki-do-monitorowania)
5. [Alerty](#alerty)
6. [Najlepsze Praktyki](#najlepsze-praktyki)

---

## Przegląd

Monitoring jest niezbędny do utrzymania zdrowia, wydajności i niezawodności aplikacji. Ten przewodnik obejmuje różne narzędzia monitoringu i ich zastosowanie w MovieMind API.

### Kluczowe Obszary Monitoringu

- **Wydajność Aplikacji** - Czasy odpowiedzi, przepustowość, wskaźniki błędów
- **Infrastruktura** - CPU, pamięć, dysk, sieć
- **Baza Danych** - Wydajność zapytań, pule połączeń, wolne zapytania
- **Zadania Kolejki** - Czasy przetwarzania zadań, błędy, zaległości
- **Usługi Zewnętrzne** - Opóźnienia API OpenAI, dostępność API TMDb
- **Metryki Biznesowe** - Użycie API, wskaźniki sukcesu generowania, aktywność użytkowników

---

## Narzędzia Monitoringu

### Laravel Telescope

**Cel:** Debugowanie i monitoring aplikacji Laravel w czasie rzeczywistym.

**Zastosowania:**
- Debugowanie żądań, odpowiedzi i wyjątków
- Monitorowanie zapytań do bazy danych i wydajności
- Śledzenie zadań kolejki i błędów
- Przeglądanie operacji cache
- Inspekcja zdarzeń i listenerów

**Instalacja:**

```bash
composer require laravel/telescope
php artisan telescope:install
php artisan migrate
```

**Konfiguracja:**

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

**Kontrola Dostępu:**

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

**URL Dostępu:** `http://localhost:8000/telescope`

**Zalety:**
- ✅ Natywna integracja z Laravel
- ✅ Zero konfiguracji dla podstawowego setupu
- ✅ Debugowanie w czasie rzeczywistym
- ✅ Szczegółowa inspekcja żądań/odpowiedzi

**Wady:**
- ❌ Nie nadaje się do produkcji (wpływ na wydajność)
- ❌ Ograniczone możliwości alertowania
- ❌ Brak długoterminowego przechowywania danych

**Rekomendacja:** Użyj tylko w środowiskach **deweloperskich i staging**.

---

### Grafana

**Cel:** Platforma analityczna i wizualizacyjna open-source dla danych szeregów czasowych.

**Zastosowania:**
- Tworzenie dashboardów dla metryk aplikacji
- Wizualizacja trendów wydajności
- Monitorowanie zasobów infrastruktury
- Śledzenie KPI biznesowych

**Architektura:**

```
Aplikacja → Prometheus → Grafana
           (metryki)    (wizualizacja)
```

**Instalacja (Docker):**

```yaml
# compose.yml
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

**Kluczowe Metryki do Wizualizacji:**

1. **Metryki Aplikacji**
   - Szybkość żądań (żądania/sekundę)
   - Czas odpowiedzi (p50, p95, p99)
   - Wskaźnik błędów (4xx, 5xx)
   - Aktywni użytkownicy

2. **Metryki Kolejki**
   - Zadania przetworzone na minutę
   - Wskaźnik błędów zadań
   - Rozmiar zaległości kolejki
   - Średni czas przetwarzania zadania

3. **Metryki Bazy Danych**
   - Czas wykonania zapytania
   - Wykorzystanie puli połączeń
   - Liczba wolnych zapytań
   - Rozmiar bazy danych

4. **Metryki Infrastruktury**
   - Wykorzystanie CPU
   - Wykorzystanie pamięci
   - I/O dysku
   - Przepustowość sieci

**Przykładowe Zapytania Dashboard:**

```promql
# Szybkość żądań
rate(http_requests_total[5m])

# Wskaźnik błędów
rate(http_requests_total{status=~"5.."}[5m]) / rate(http_requests_total[5m])

# Średni czas odpowiedzi
histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m]))
```

**Zalety:**
- ✅ Bogate opcje wizualizacji
- ✅ Elastyczny język zapytań (PromQL)
- ✅ Wsparcie dla alertów
- ✅ Ekosystem pluginów

**Wady:**
- ❌ Wymaga źródła danych (Prometheus, InfluxDB, itp.)
- ❌ Większa krzywa uczenia
- ❌ Wymagające zasobów

**Rekomendacja:** Użyj do **monitoringu produkcji** z Prometheus jako źródłem danych.

---

### Loki

**Cel:** System agregacji logów zaprojektowany jako opłacalny i łatwy w obsłudze.

**Zastosowania:**
- Centralna kolekcja logów
- Wyszukiwanie i analiza logów
- Korelacja z metrykami
- Rozwiązywanie problemów

**Architektura:**

```
Logi Aplikacji → Promtail → Loki → Grafana
                (agent)    (storage) (zapytanie)
```

**Instalacja (Docker):**

```yaml
# compose.yml
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

**Konfiguracja Promtail:**

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

**Zapytania LogQL:**

```logql
# Logi błędów
{job="moviemind-api"} |= "ERROR"

# Wolne zapytania
{job="moviemind-api"} | json | duration > 1s

# Nieudane zadania
{job="moviemind-api"} |= "Job failed"
```

**Zalety:**
- ✅ Opłacalne (brak indeksowania)
- ✅ Szybkie zapytania
- ✅ Integracja z Grafana
- ✅ Filtrowanie oparte na etykietach

**Wady:**
- ❌ Mniej potężne niż stos ELK
- ❌ Ograniczone wyszukiwanie pełnotekstowe
- ❌ Wymaga agenta Promtail

**Rekomendacja:** Użyj do **agregacji logów** gdy koszt jest istotny.

---

### Datadog

**Cel:** Platforma monitoringu i analityki oparta na chmurze.

**Zastosowania:**
- APM (Application Performance Monitoring)
- Monitorowanie infrastruktury
- Zarządzanie logami
- Alertowanie w czasie rzeczywistym
- Śledzenie rozproszone

**Instalacja:**

```bash
# Zainstaluj Datadog Agent
DD_API_KEY=your_api_key DD_SITE="datadoghq.com" bash -c "$(curl -L https://s3.amazonaws.com/dd-agent/scripts/install_script_agent7.sh)"

# Integracja Laravel
composer require datadog/dd-trace
```

**Konfiguracja:**

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

**Kluczowe Funkcje:**

1. **APM**
   - Śledzenie żądań
   - Śledzenie zapytań do bazy danych
   - Wywołania usług zewnętrznych
   - Śledzenie błędów

2. **Monitorowanie Infrastruktury**
   - Metryki serwera
   - Metryki kontenerów
   - Metryki sieci

3. **Zarządzanie Logami**
   - Centralne logowanie
   - Parsowanie logów
   - Korelacja ze śladami

4. **Alertowanie**
   - Powiadomienia wielokanałowe
   - Grupowanie alertów
   - Zarządzanie on-call

**Zalety:**
- ✅ Rozwiązanie all-in-one
- ✅ Łatwa konfiguracja
- ✅ Doskonały UI/UX
- ✅ Silne alertowanie

**Wady:**
- ❌ Drogie (cennik SaaS)
- ❌ Vendor lock-in
- ❌ Obawy o prywatność danych

**Rekomendacja:** Użyj dla **produkcji enterprise** gdy budżet pozwala.

---

### Zabbix

**Cel:** Enterprise-class rozwiązanie monitoringu open-source.

**Zastosowania:**
- Monitorowanie infrastruktury
- Monitorowanie sieci
- Monitorowanie aplikacji
- Planowanie pojemności

**Architektura:**

```
Zabbix Agent → Zabbix Server → Zabbix Frontend
              (zbieranie danych)  (baza danych)    (UI)
```

**Instalacja (Docker):**

```yaml
# compose.yml
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

**Kluczowe Funkcje:**

1. **Monitoring**
   - Monitorowanie serwerów
   - Monitorowanie urządzeń sieciowych
   - Monitorowanie aplikacji
   - Monitorowanie bazy danych

2. **Alertowanie**
   - Powiadomienia e-mail
   - Powiadomienia SMS
   - Integracje webhook
   - Reguły eskalacji

3. **Wizualizacja**
   - Wykresy
   - Mapy
   - Ekrany
   - Dashboardy

**Zalety:**
- ✅ Open-source
- ✅ Wysoce konfigurowalne
- ✅ Silne alertowanie
- ✅ Dojrzały ekosystem

**Wady:**
- ❌ Złożona konfiguracja
- ❌ Większa krzywa uczenia
- ❌ Wymagające zasobów
- ❌ UI jest przestarzałe

**Rekomendacja:** Użyj do **monitoringu infrastruktury** w środowiskach enterprise.

---

### Stos ELT

**Cel:** Elasticsearch, Logstash i Kibana do analizy i wyszukiwania logów.

**Zastosowania:**
- Agregacja i analiza logów
- Wyszukiwanie pełnotekstowe
- Analiza bezpieczeństwa
- Business intelligence

**Architektura:**

```
Aplikacja → Logstash → Elasticsearch → Kibana
           (ingestia)  (storage)        (wizualizacja)
```

**Instalacja (Docker):**

```yaml
# compose.yml
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

**Pipeline Logstash:**

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

**Zapytania Kibana:**

```json
// Logi błędów
level: "ERROR"

// Wolne żądania
duration: > 1000

// Nieudane zadania
message: "Job failed"
```

**Zalety:**
- ✅ Potężne możliwości wyszukiwania
- ✅ Analiza w czasie rzeczywistym
- ✅ Skalowalne
- ✅ Bogata wizualizacja

**Wady:**
- ❌ Wymagające zasobów
- ❌ Złożona konfiguracja
- ❌ Koszty licencji (dla zaawansowanych funkcji)

**Rekomendacja:** Użyj do **analizy logów** gdy wymagane jest wyszukiwanie pełnotekstowe.

---

## Strategia Implementacji

### Faza 1: Development (Obecna)

**Narzędzia:**
- Laravel Telescope (debugowanie lokalne)
- Laravel Horizon (monitoring kolejki)
- Podstawowe logowanie (logi Laravel)

**Fokus:**
- Debugowanie i rozwój
- Monitoring zadań kolejki
- Podstawowe śledzenie błędów

### Faza 2: Staging

**Narzędzia:**
- Laravel Telescope (środowisko staging)
- Grafana + Prometheus (metryki)
- Loki (agregacja logów)

**Fokus:**
- Monitoring wydajności
- Śledzenie błędów
- Podstawowe alertowanie

### Faza 3: Produkcja

**Narzędzia:**
- Grafana + Prometheus (metryki)
- Loki (agregacja logów)
- AlertManager (alertowanie)
- Opcjonalnie: Datadog (jeśli budżet pozwala)

**Fokus:**
- Monitoring produkcji
- Alertowanie
- Optymalizacja wydajności
- Planowanie pojemności

---

## Metryki do Monitorowania

### Metryki Aplikacji

| Metryka | Opis | Próg |
|---------|------|------|
| Szybkość Żądań | Żądania na sekundę | Alert jeśli > 1000 req/s |
| Czas Odpowiedzi (p95) | 95. percentyl czasu odpowiedzi | Alert jeśli > 500ms |
| Wskaźnik Błędów | Procent nieudanych żądań | Alert jeśli > 1% |
| Aktywni Użytkownicy | Równocześni aktywni użytkownicy | Monitoruj trendy |

### Metryki Kolejki

| Metryka | Opis | Próg |
|---------|------|------|
| Zadania Przetworzone | Zadania przetworzone na minutę | Monitoruj trendy |
| Wskaźnik Błędów Zadań | Procent nieudanych zadań | Alert jeśli > 5% |
| Zaległość Kolejki | Liczba oczekujących zadań | Alert jeśli > 1000 |
| Średni Czas Zadania | Średni czas przetwarzania zadania | Alert jeśli > 30s |

### Metryki Bazy Danych

| Metryka | Opis | Próg |
|---------|------|------|
| Czas Zapytania (p95) | 95. percentyl czasu zapytania | Alert jeśli > 100ms |
| Wykorzystanie Puli Połączeń | Procent wykorzystanych połączeń | Alert jeśli > 80% |
| Wolne Zapytania | Liczba wolnych zapytań na minutę | Alert jeśli > 10 |
| Rozmiar Bazy Danych | Całkowity rozmiar bazy danych | Monitoruj wzrost |

### Metryki Infrastruktury

| Metryka | Opis | Próg |
|---------|------|------|
| Wykorzystanie CPU | Procent wykorzystania CPU | Alert jeśli > 80% |
| Wykorzystanie Pamięci | Procent wykorzystania pamięci | Alert jeśli > 85% |
| Wykorzystanie Dysku | Wykorzystanie przestrzeni dyskowej | Alert jeśli > 90% |
| Przepustowość Sieci | I/O sieci | Monitoruj trendy |

### Metryki Usług Zewnętrznych

| Metryka | Opis | Próg |
|---------|------|------|
| Opóźnienie API OpenAI | Czas odpowiedzi z OpenAI | Alert jeśli > 5s |
| Wskaźnik Błędów API OpenAI | Procent nieudanych wywołań | Alert jeśli > 2% |
| Opóźnienie API TMDb | Czas odpowiedzi z TMDb | Alert jeśli > 2s |
| Wskaźnik Błędów API TMDb | Procent nieudanych wywołań | Alert jeśli > 5% |

---

## Alerty

### Kanały Alertów

1. **E-mail** - Dla alertów niekrytycznych
2. **Slack** - Dla powiadomień zespołu
3. **PagerDuty** - Dla krytycznych alertów on-call
4. **SMS** - Dla alertów awaryjnych

### Poziomy Ważności Alertów

- **Krytyczny** - Wymagana natychmiastowa akcja (usługa niedostępna, utrata danych)
- **Ostrzeżenie** - Wymagana uwaga (degradacja wydajności, wysoki wskaźnik błędów)
- **Informacja** - Informacyjne (planowanie pojemności, trendy)

### Przykładowe Reguły Alertów

```yaml
# Reguły alertów Prometheus
groups:
  - name: moviemind_alerts
    rules:
      - alert: HighErrorRate
        expr: rate(http_requests_total{status=~"5.."}[5m]) > 0.01
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Wykryto wysoki wskaźnik błędów"
          description: "Wskaźnik błędów wynosi {{ $value }}%"

      - alert: SlowResponseTime
        expr: histogram_quantile(0.95, rate(http_request_duration_seconds_bucket[5m])) > 0.5
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Wykryto wolny czas odpowiedzi"
          description: "Czas odpowiedzi P95 wynosi {{ $value }}s"

      - alert: QueueBacklog
        expr: queue_jobs_pending > 1000
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "Duża zaległość kolejki"
          description: "Kolejka ma {{ $value }} oczekujących zadań"
```

---

## Najlepsze Praktyki

### 1. Zacznij Prosto

- Zacznij od podstawowego logowania i śledzenia błędów
- Dodawaj metryki stopniowo
- Skup się najpierw na ścieżkach krytycznych

### 2. Używaj Strukturalnego Logowania

```php
// Dobrze
Log::info('Job processed', [
    'job_id' => $jobId,
    'entity_type' => $entityType,
    'duration' => $duration,
    'status' => 'success',
]);

// Źle
Log::info("Job $jobId processed successfully");
```

### 3. Monitoruj Metryki Biznesowe

- Śledź wzorce użycia API
- Monitoruj wskaźniki sukcesu generowania
- Mierz zaangażowanie użytkowników

### 4. Skonfiguruj Alertowanie Wcześnie

- Skonfiguruj alerty przed wystąpieniem problemów
- Testuj kanały alertów regularnie
- Przeglądaj i dostosowuj progi alertów

### 5. Używaj ID Korelacji

```php
// Dodaj ID żądania do wszystkich logów
Log::withContext(['request_id' => $requestId])
    ->info('Processing request');
```

### 6. Regularne Przeglądy

- Przeglądaj dashboardy co tydzień
- Analizuj trendy co miesiąc
- Dostosowuj progi na podstawie danych

### 7. Optymalizacja Kosztów

- Używaj próbkowania dla metryk o wysokiej objętości
- Archiwizuj stare logi
- Wybierz odpowiednie okresy retencji

---

## Referencje

- [Dokumentacja Laravel Telescope](https://laravel.com/docs/telescope)
- [Dokumentacja Grafana](https://grafana.com/docs/)
- [Dokumentacja Loki](https://grafana.com/docs/loki/)
- [Dokumentacja Datadog](https://docs.datadoghq.com/)
- [Dokumentacja Zabbix](https://www.zabbix.com/documentation)
- [Dokumentacja Stosu ELK](https://www.elastic.co/guide/)

---

**Ostatnia aktualizacja:** 2026-01-21
