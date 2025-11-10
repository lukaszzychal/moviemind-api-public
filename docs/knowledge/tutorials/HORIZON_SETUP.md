# 🚀 Laravel Horizon - Setup i Konfiguracja

**Data:** 2025-11-04  
**Wersja:** Laravel Horizon 5.38

---

## 📋 **Co to jest Laravel Horizon?**

**Laravel Horizon** to dashboard i code-driven configuration dla Laravel queues. Zapewnia:

- ✅ **Dashboard** - monitoring queue jobs w czasie rzeczywistym
- ✅ **Code-driven configuration** - konfiguracja workers w kodzie
- ✅ **Auto-balancing** - automatyczna równowaga obciążenia
- ✅ **Metrics** - statystyki i metryki
- ✅ **Failed Jobs** - monitoring failed jobs

**Wymagania:**
- Laravel 11+
- Redis (wymagany dla Horizon)
- PHP 8.2+

---

## 🔧 **Instalacja**

### **Krok 1: Instalacja przez Composer**

```bash
composer require laravel/horizon
```

### **Krok 2: Publikacja Assets**

```bash
php artisan horizon:install
```

**To utworzy:**
- `config/horizon.php` - konfiguracja Horizon
- `app/Providers/HorizonServiceProvider.php` - service provider

### **Krok 3: Migracja (jeśli potrzebna)**

```bash
php artisan migrate
```

**Horizon używa Redis, więc nie potrzebuje migracji bazy danych.**

---

## ⚙️ **Konfiguracja**

### **1. Environment Variables**

**`.env`:**
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_CLIENT=predis
HORIZON_TIMEOUT=120
HORIZON_TRIES=3
HORIZON_ALLOWED_EMAILS=admin@example.com,ops@example.com
HORIZON_AUTH_BYPASS_ENVS=local,staging
```

**Dla Docker / CI:**
```env
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_CLIENT=predis
HORIZON_TIMEOUT=120
HORIZON_TRIES=3
HORIZON_ALLOWED_EMAILS=
HORIZON_AUTH_BYPASS_ENVS=local,staging
```

### **2. Horizon Configuration**

**`config/horizon.php`:**
[
    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => env('HORIZON_BALANCE', 'auto'),
            'autoScalingStrategy' => env('HORIZON_AUTOSCALING_STRATEGY', 'time'),
            'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 1),
            'tries' => (int) env('HORIZON_TRIES', 3),
            'timeout' => (int) env('HORIZON_TIMEOUT', 120),
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_PROD_MAX_PROCESSES', 10),
                'balanceMaxShift' => (int) env('HORIZON_PROD_BALANCE_MAX_SHIFT', 1),
                'balanceCooldown' => (int) env('HORIZON_PROD_BALANCE_COOLDOWN', 3),
                'tries' => (int) env('HORIZON_PROD_TRIES', env('HORIZON_TRIES', 3)),
                'timeout' => (int) env('HORIZON_PROD_TIMEOUT', env('HORIZON_TIMEOUT', 120)),
        ],
    ],
    'local' => [
        'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_LOCAL_MAX_PROCESSES', 3),
                'tries' => (int) env('HORIZON_LOCAL_TRIES', env('HORIZON_TRIES', 3)),
                'timeout' => (int) env('HORIZON_LOCAL_TIMEOUT', env('HORIZON_TIMEOUT', 120)),
            ],
        ],
    ],
    'auth' => [
        'bypass_environments' => explode(',', env('HORIZON_AUTH_BYPASS_ENVS', 'local,staging')),
        'allowed_emails' => array_filter(array_map('trim', explode(',', env('HORIZON_ALLOWED_EMAILS', '')))),
        ],
];
```

---

## 🚀 **Uruchomienie**

### **Lokalnie (Development):**

```bash
# Opcja 1: Artisan command
php artisan horizon

# Opcja 2: Docker Compose
docker compose up horizon
```

### **Production:**

```bash
# Użyj process manager (Supervisor, systemd, etc.)
# Przykład z Supervisor:
[program:horizon]
process_name=%(program_name)s
command=php /path/to/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/horizon.log
```

---

## 📊 **Dashboard**

### **Dostęp:**

**URL:** `http://localhost:8000/horizon`

**Autoryzacja:**
- W `local` environment: dostęp dla wszystkich
- W `production`: wymaga autoryzacji (gate `viewHorizon`)

### **Co zobaczysz:**

1. **Dashboard** - overview wszystkich workers
2. **Jobs** - lista przetwarzanych jobs
3. **Failed Jobs** - lista failed jobs
4. **Metrics** - statystyki (throughput, wait time, etc.)
5. **Workers** - lista aktywnych workers

---

## 🔍 **Monitoring**

### **Podstawowe Metryki:**

- **Throughput** - liczba jobs przetworzonych na sekundę
- **Wait Time** - średni czas oczekiwania w kolejce
- **Runtime** - średni czas wykonania job
- **Failed Jobs** - liczba failed jobs

### **Alerty:**

**Konfiguracja w `HorizonServiceProvider`:**
```php
Horizon::routeMailNotificationsTo('admin@example.com');
Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
```

---

## 🐛 **Debugowanie**

### **1. Sprawdzenie Status Horizon:**

```bash
php artisan horizon:status
```

**Output:**
```
Horizon is running.
Workers: 3
```

### **2. Sprawdzenie Logów:**

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Horizon logs (jeśli używasz Supervisor)
tail -f /path/to/horizon.log
```

### **3. Sprawdzenie Failed Jobs:**

```bash
# CLI
php artisan queue:failed

# Dashboard
http://localhost:8000/horizon/failed
```

### **4. Retry Failed Jobs:**

```bash
# Retry wszystkie
php artisan queue:retry all

# Retry konkretny job
php artisan queue:retry {job-id}
```

---

## ⚙️ **Konfiguracja Workers**

### **Auto-balancing:**

**`balance: auto`** - automatyczna równowaga:
- Horizon automatycznie dostosowuje liczbę procesów
- Bazuje na wait time i throughput
- `balanceMaxShift` - maksymalna zmiana procesów na raz
- `balanceCooldown` - czas między zmianami

**`balance: simple`** - prosta równowaga:
- Stała liczba procesów (`maxProcesses`)
- Używane w development

### **Queue Priority:**

```php
'queue' => ['high', 'default', 'low'],
```

Jobs z `high` queue są przetwarzane pierwsze.

---

## 🔐 **Security**

### **Gate Authorization:**

**`app/Providers/HorizonServiceProvider.php`:**
```php
protected function gate(): void
{
    Gate::define('viewHorizon', function ($user = null) {
        // Local: dostęp dla wszystkich
        if (app()->environment('local')) {
            return true;
        }
        
        // Production: tylko autoryzowani użytkownicy
        return in_array(optional($user)->email, [
            'admin@example.com',
        ]);
    });
}
```

---

## 📈 **Performance Tuning**

### **1. Max Processes:**

**Dla production:**
```php
'maxProcesses' => 10, // Zwiększ jeśli potrzeba
```

**Uwaga:** Zbyt wiele procesów może obciążyć serwer.

### **2. Balance Settings:**

```php
'balanceMaxShift' => 1, // Konserwatywna zmiana
'balanceCooldown' => 3, // 3 sekundy między zmianami
```

### **3. Timeout Settings:**

**W Job classes:**
```php
public int $timeout = 120; // 120 sekund
```

**W Horizon config:**
```php
'timeout' => 300, // 5 minut (globalny timeout)
```

---

## 🐳 **Docker Setup**

### **docker-compose.yml:**

```yaml
horizon:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  container_name: moviemind-horizon
  command: sh -lc "php artisan horizon"
  working_dir: /var/www/html
  volumes:
    - ./api:/var/www/html:cached
  environment:
    QUEUE_CONNECTION: redis
    REDIS_HOST: redis
    REDIS_CLIENT: predis
    DB_CONNECTION: pgsql
    DB_HOST: db
  depends_on:
    - php
    - db
    - redis
```

### **Uruchomienie:**

```bash
# Start Horizon
docker compose up horizon

# Logs
docker compose logs -f horizon

# Status
docker compose exec horizon php artisan horizon:status
```

---

## ✅ **Weryfikacja Działania**

### **1. Sprawdź czy Horizon działa:**

```bash
php artisan horizon:status
```

### **2. Sprawdź dashboard:**

Otwórz: `http://localhost:8000/horizon`

### **3. Wyślij test job:**

```bash
php artisan tinker
>>> App\Jobs\MockGenerateMovieJob::dispatch('test-slug', 'test-job-id');
```

### **4. Sprawdź w dashboard:**

- Job powinien pojawić się w "Recent Jobs"
- Status powinien zmienić się z "pending" na "completed"

---

## 🔧 **Troubleshooting**

### **Problem: Horizon nie startuje**

**Rozwiązanie:**
1. Sprawdź `QUEUE_CONNECTION=redis` w `.env`
2. Sprawdź czy Redis działa: `redis-cli ping`
3. Sprawdź logi: `php artisan horizon:status`

### **Problem: Jobs nie są przetwarzane**

**Rozwiązanie:**
1. Sprawdź czy Horizon działa: `php artisan horizon:status`
2. Sprawdź konfigurację queue connection
3. Sprawdź logi Horizon

### **Problem: Dashboard nie działa**

**Rozwiązanie:**
1. Sprawdź czy routes są zarejestrowane: `php artisan route:list | grep horizon`
2. Sprawdź autoryzację (gate)
3. Sprawdź czy `APP_ENV=local` (dla development)

---

## 📚 **Dodatkowe Zasoby**

- [Laravel Horizon Documentation](https://laravel.com/docs/11.x/horizon)
- [Redis Documentation](https://redis.io/docs/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

---

**Ostatnia aktualizacja:** 2025-11-04

