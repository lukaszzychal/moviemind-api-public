# RabbitMQ Setup dla Laravel

## 📦 Instalacja

### 1. Zainstaluj pakiet Laravel Queue RabbitMQ

```bash
cd api
composer require vladimir-yuldashev/laravel-queue-rabbitmq
```

### 2. Opublikuj konfigurację (opcjonalnie)

```bash
php artisan vendor:publish --provider="VladimirYuldashev\LaravelQueueRabbitMQ\LaravelQueueRabbitMQServiceProvider"
```

## ⚙️ Konfiguracja

### 1. Dodaj konfigurację RabbitMQ do `api/config/queue.php`

```php
'connections' => [
    // ... istniejące połączenia ...
    
    'rabbitmq' => [
        'driver' => 'rabbitmq',
        'queue' => env('RABBITMQ_QUEUE', 'default'),
        'connection' => PhpAmqpLib\Connection\AMQPLazyConnection::class,
        
        'hosts' => [
            [
                'host' => env('RABBITMQ_HOST', '127.0.0.1'),
                'port' => env('RABBITMQ_PORT', 5672),
                'user' => env('RABBITMQ_USER', 'guest'),
                'password' => env('RABBITMQ_PASSWORD', 'guest'),
                'vhost' => env('RABBITMQ_VHOST', '/'),
            ],
        ],
        
        'options' => [
            'ssl_options' => [
                'cafile' => env('RABBITMQ_SSL_CAFILE', null),
                'local_cert' => env('RABBITMQ_SSL_LOCALCERT', null),
                'local_key' => env('RABBITMQ_SSL_LOCALKEY', null),
                'verify_peer' => env('RABBITMQ_SSL_VERIFY_PEER', true),
                'passphrase' => env('RABBITMQ_SSL_PASSPHRASE', null),
            ],
            'queue' => [
                'job' => VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob::class,
            ],
        ],
        
        'worker' => env('RABBITMQ_WORKER', 'default'),
    ],
],
```

### 2. Dodaj zmienne środowiskowe do `.env`

```env
# Queue Configuration
QUEUE_CONNECTION=rabbitmq

# RabbitMQ Configuration
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_USER=moviemind
RABBITMQ_PASSWORD=moviemind
RABBITMQ_VHOST=/
RABBITMQ_QUEUE=default
```

### 3. Dodaj RabbitMQ do `compose.yml`

```yaml
services:
  # ... istniejące serwisy ...
  
  rabbitmq:
    image: rabbitmq:3-management-alpine
    container_name: moviemind-rabbitmq
    ports:
      - "5672:5672"    # AMQP port
      - "15672:15672"  # Management UI
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER:-moviemind}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD:-moviemind}
    volumes:
      - rabbitmq_data:/var/lib/rabbitmq
    healthcheck:
      test: rabbitmq-diagnostics -q ping
      interval: 30s
      timeout: 10s
      retries: 3

volumes:
  # ... istniejące volumes ...
  rabbitmq_data:
```

## 🚀 Użycie

### 1. Uruchom RabbitMQ

```bash
docker compose up -d rabbitmq
```

### 2. Uruchom Queue Worker

```bash
php artisan queue:work rabbitmq
```

Lub dla Horizon (wymaga modyfikacji):

```bash
php artisan horizon
```

**Uwaga:** Horizon domyślnie nie wspiera RabbitMQ bezpośrednio - trzeba użyć standardowego `queue:work`.

### 3. Monitorowanie - RabbitMQ Management UI

Dostępne pod: `http://localhost:15672`

- **Login:** `moviemind` (lub wartość z `RABBITMQ_USER`)
- **Password:** `moviemind` (lub wartość z `RABBITMQ_PASSWORD`)

## 🔄 Migracja z Redis do RabbitMQ

### Opcja 1: Zmiana konfiguracji (prosta)

```env
# Zmień w .env
QUEUE_CONNECTION=rabbitmq  # było: redis
```

### Opcja 2: Wsparcie wielu queue connections

Możesz mieć oba jednocześnie i wybierać per-job:

```php
// Dla AI generation - RabbitMQ
Bus::dispatch(new GenerateMovieJob($slug))
    ->onConnection('rabbitmq');

// Dla innych zadań - Redis
Bus::dispatch(new SendEmailJob($user))
    ->onConnection('redis');
```

## 📊 Porównanie: Redis vs RabbitMQ

### Redis + Horizon (obecne)

**Zalety:**
- ✅ Prostsze setup
- ✅ Mniejsze zużycie zasobów
- ✅ Horizon dashboard wbudowany
- ✅ Szybkie dla prostych przypadków

**Wady:**
- ❌ Mniej funkcji (priorities, routing, exchanges)
- ❌ Brak GUI (bez Horizon)
- ❌ Mniej skalowalne dla złożonych scenariuszy

### RabbitMQ

**Zalety:**
- ✅ Zaawansowane features (exchanges, routing keys, priorities)
- ✅ Management UI (darmowe)
- ✅ Lepsze dla microservices (routing między serwisami)
- ✅ Dead Letter Queues (DLQ)
- ✅ Message TTL
- ✅ Lepsze dla heterogenicznych systemów (Python, Node.js, itp.)

**Wady:**
- ❌ Bardziej skomplikowane
- ❌ Większe zużycie zasobów
- ❌ Wymaga więcej konfiguracji
- ❌ Brak Horizon (ale jest Management UI)

## 🎯 Kiedy użyć RabbitMQ?

**Używaj RabbitMQ gdy:**

1. ✅ Masz wiele serwisów (Laravel API + dodatkowe mikroserwisy w innych językach)
2. ✅ Potrzebujesz zaawansowanego routingu (exchanges, routing keys)
3. ✅ Potrzebujesz priorities w kolejkach
4. ✅ Integracja z zewnętrznymi systemami (AMQP standard)
5. ✅ Potrzebujesz Dead Letter Queues dla failed jobs
6. ✅ Microservices architecture

**Zostań przy Redis gdy:**

1. ✅ Prosty use case (jak teraz)
2. ✅ Tylko Laravel aplikacja
3. ✅ Chcesz Horizon dashboard
4. ✅ Mniejsze zużycie zasobów
5. ✅ Szybsze setup

## 🔧 Przykładowa Konfiguracja dla Twojego Projektu

### `api/config/queue.php` - Dodaj RabbitMQ connection:

```php
'rabbitmq' => [
    'driver' => 'rabbitmq',
    'queue' => env('RABBITMQ_QUEUE', 'ai_generation'),
    'connection' => PhpAmqpLib\Connection\AMQPLazyConnection::class,
    
    'hosts' => [
        [
            'host' => env('RABBITMQ_HOST', 'rabbitmq'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'moviemind'),
            'password' => env('RABBITMQ_PASSWORD', 'moviemind'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
        ],
    ],
    
    'options' => [
        'queue' => [
            'job' => VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob::class,
        ],
    ],
],
```

### Użycie w `MockAiService.php`:

```php
// Opcja 1: Globalnie
// W .env: QUEUE_CONNECTION=rabbitmq

// Opcja 2: Per-job
Bus::dispatch(function () use ($slug, $jobId) {
    // ...
})->onConnection('rabbitmq');
```

## 🐳 Docker Compose - Pełna Konfiguracja

```yaml
rabbitmq:
  image: rabbitmq:3-management-alpine
  container_name: moviemind-rabbitmq
  ports:
    - "5672:5672"    # AMQP
    - "15672:15672"  # Management UI
  environment:
    RABBITMQ_DEFAULT_USER: moviemind
    RABBITMQ_DEFAULT_PASS: moviemind
  volumes:
    - rabbitmq_data:/var/lib/rabbitmq
  healthcheck:
    test: rabbitmq-diagnostics -q ping
    interval: 30s
    timeout: 10s
    retries: 3

# Worker dla RabbitMQ (zamiast Horizon)
queue-worker:
  build:
    context: .
    dockerfile: docker/php/Dockerfile
  container_name: moviemind-queue-worker
  command: sh -lc "php artisan queue:work rabbitmq --verbose --tries=3 --timeout=90"
  working_dir: /var/www/html
  volumes:
    - ./api:/var/www/html:cached
  environment:
    QUEUE_CONNECTION: rabbitmq
    RABBITMQ_HOST: rabbitmq
    RABBITMQ_PORT: 5672
    RABBITMQ_USER: moviemind
    RABBITMQ_PASSWORD: moviemind
    DB_CONNECTION: pgsql
    DB_HOST: db
    # ... reszta ENV ...
  depends_on:
    rabbitmq:
      condition: service_healthy
    db:
      condition: service_started
```

## ✅ Testowanie

### 1. Sprawdź połączenie

```bash
docker compose exec php php artisan queue:listen rabbitmq --verbose
```

### 2. Wysyłaj test job

```php
Bus::dispatch(function () {
    Log::info('RabbitMQ job executed!');
})->onConnection('rabbitmq');
```

### 3. Sprawdź w Management UI

Otwórz: `http://localhost:15672`
- Zobacz queues, messages, connections
- Monitoruj throughput

## 📝 Rekomendacja dla Twojego Projektu

**Obecnie:** Redis + Horizon jest wystarczający dla MVP ✅

**Rozważ RabbitMQ gdy:**
- Dodasz dedykowany mikroserwis (np. Python dla pipeline'ów AI)
- Potrzebujesz integracji z zewnętrznymi systemami
- Masz złożone routing requirements
- Chcesz standardowy AMQP protocol

## 🔗 Przydatne Linki

- [Laravel Queue RabbitMQ Package](https://github.com/vyuldashev/laravel-queue-rabbitmq)
- [RabbitMQ Documentation](https://www.rabbitmq.com/documentation.html)
- [RabbitMQ Management UI](https://www.rabbitmq.com/management.html)

