# API Calls i Logowanie - Synchroniczne vs Asynchroniczne

## 🎯 Quick Answer

| Operacja | Domyślnie | Może być async? |
|----------|-----------|-----------------|
| **HTTP API Calls** | ✅ Synchroniczne | ✅ Tak (przez Queue/Job) |
| **Logowanie** | ✅ Synchroniczne | ✅ Tak (konfigurowalne) |
| **Database Queries** | ✅ Synchroniczne | ✅ Tak (przez Queue/Job) |

---

## 📡 1. API Calls - Domyślnie SYNCHRONICZNE

### Standardowy HTTP Request w Laravel

```php
// SYNCHRONICZNE - request czeka na odpowiedź
$response = Http::get('https://api.example.com/data');
// ↑ Blokuje wykonanie aż otrzyma odpowiedź

$data = $response->json();
```

**Co się dzieje:**
```
Request → Controller → Http::get() → [czeka...] → Response → Done
         (blokuje request - może trwać sekundy!)
```

---

### Problem: API Calls Blokują Request

```php
// ❌ ŹLE - blokuje request
public function generate() {
    // Wywołanie zewnętrznego API - może trwać 5 sekund!
    $result = Http::timeout(5)->post('https://ai-api.com/generate', [
        'slug' => $slug
    ]);
    
    // Request czeka 5 sekund! ❌
    return response()->json($result);
}
```

**Timeline:**
```
0ms    Request
1ms    Controller
2ms    Http::post() → [czeka na API...]
5000ms API odpowiada
5001ms Response ← REQUEST TRWA 5 SEKUND! ❌
```

---

### Rozwiązanie: API Call w Job (ASYNC)

```php
// ✅ DOBRZE - API call w Job (async)
public function generate() {
    $jobId = Str::uuid();
    
    // Dispatch Job - request zwraca szybko
    GenerateMovieJob::dispatch($slug, $jobId);
    
    return response()->json(['job_id' => $jobId], 202);
    // ↑ Request zwraca natychmiast (202 Accepted)
}

// Job wykonuje API call w tle
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void {
        // API call w tle - request NIE czeka
        $result = Http::timeout(30)->post('https://ai-api.com/generate', [
            'slug' => $this->slug
        ]);
        
        // Może trwać długo - ale w tle!
        Movie::create([...]);
    }
}
```

**Timeline:**
```
0ms    Request
1ms    Controller
2ms    Job::dispatch() → Queue
3ms    Response (202) ← REQUEST KONIECZNY ✅
       ↓
       ↓ ASYNC (w tle)
       ↓
Worker → Job::handle() → Http::post() → [czeka...] → Done
```

---

## 📝 2. Logowanie - Może Być ASYNCHRONICZNE

### Standardowe Logowanie - SYNCHRONICZNE (domyślnie)

```php
// SYNCHRONICZNE - zapisuje natychmiast do pliku
Log::info('Movie generation requested', ['slug' => $slug]);
// ↑ Blokuje request na ~1-5ms (zapis do pliku)
```

**Co się dzieje:**
```
Request → Controller → Log::info() → [zapis do pliku] → Done
         (blokuje request na kilka ms)
```

---

### Async Logging - Dla Wydajności

**W Laravel można skonfigurować async logging:**

#### Opcja 1: Queue Handler (Laravel 10+)

```php
// config/logging.php
'channels' => [
    'single' => [
        'driver' => 'stack',
        'channels' => ['daily', 'queue'],  // ← Queue channel
    ],
    
    'queue' => [
        'driver' => 'stack',
        'channels' => ['daily'],
        'via' => \Illuminate\Log\LogManager::class,
    ],
    
    'daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
],
```

**Efekt:**
```php
Log::info('Movie generation requested');
// ↑ Tylko dispatch do queue - request NIE czeka na zapis do pliku
```

**Timeline:**
```
0ms    Request
1ms    Controller
2ms    Log::info() → Queue (szybko)
3ms    Response ← REQUEST KONIECZNY ✅
       ↓
       ↓ ASYNC (w tle)
       ↓
Worker → Zapis logu do pliku
```

---

#### Opcja 2: Dedicated Log Job

```php
// Utwórz Job dla logowania
class LogMessageJob implements ShouldQueue
{
    public function __construct(
        private string $level,
        private string $message,
        private array $context = []
    ) {}
    
    public function handle(): void
    {
        Log::log($this->level, $this->message, $this->context);
    }
}

// Użycie
LogMessageJob::dispatch('info', 'Movie generation requested', ['slug' => $slug]);
// ↑ ASYNC - request nie czeka
```

---

#### Opcja 3: Async Logger Package

```bash
composer require spatie/laravel-async-queue
```

```php
// Konfiguracja async logging
use Spatie\AsyncQueue\AsyncQueue;

Log::channel('async')->info('Movie generation requested');
// ↑ ASYNC
```

---

## 🔄 3. Pełny Przykład: API Call + Logging

### Scenariusz: Wygeneruj Movie przez External API

#### ❌ SYNCHRONICZNE (Źle):

```php
public function generate() {
    // 1. Logowanie - SYNC (~2ms)
    Log::info('Movie generation requested', ['slug' => $slug]);
    
    // 2. API Call - SYNC (blokuje request!)
    $result = Http::timeout(10)->post('https://ai-api.com/generate', [
        'slug' => $slug
    ]);
    
    // 3. Zapisz do DB - SYNC (~10ms)
    Movie::create([...]);
    
    // Request trwa ~10 sekund! ❌
    return response()->json($result);
}
```

**Timeline:**
```
0ms     Request
1ms     Controller
2ms     Log::info() [czeka na zapis] (~2ms)
4ms     Http::post() [czeka na API...]
10000ms API odpowiada
10001ms Movie::create() (~10ms)
10011ms Response ← REQUEST TRWA 10 SEKUND! ❌
```

---

#### ✅ ASYNCHRONICZNE (Dobrze):

```php
public function generate() {
    $jobId = Str::uuid();
    
    // 1. Logowanie - ASYNC (jeśli skonfigurowane)
    Log::channel('queue')->info('Movie generation requested', ['slug' => $slug]);
    // ↑ Tylko dispatch - request nie czeka
    
    // 2. Dispatch Job - ASYNC
    GenerateMovieJob::dispatch($slug, $jobId);
    
    // Request zwraca natychmiast!
    return response()->json(['job_id' => $jobId], 202);
}

// Job wykonuje wszystko w tle
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void {
        // 1. Logowanie w Job - SYNC (ale w tle, więc OK)
        Log::info('Starting movie generation', ['slug' => $this->slug]);
        
        // 2. API Call - SYNC (ale w tle, więc OK)
        $result = Http::timeout(30)->post('https://ai-api.com/generate', [
            'slug' => $this->slug
        ]);
        
        // 3. Zapisz do DB - SYNC (ale w tle, więc OK)
        Movie::create([...]);
        
        Log::info('Movie generation completed', ['slug' => $this->slug]);
    }
}
```

**Timeline:**
```
0ms     Request
1ms     Controller
2ms     Log::channel('queue')->info() → Queue
3ms     GenerateMovieJob::dispatch() → Queue
4ms     Response (202) ← REQUEST KONIECZNY ✅
       ↓
       ↓ ASYNC (w tle)
       ↓
Worker → Job::handle()
         → Log::info() (~2ms)
         → Http::post() [czeka...] (~10s)
         → Movie::create() (~10ms)
         → Done
```

**Kluczowe:** Request zwraca natychmiast, wszystko wykonuje się w tle!

---

## 📊 Porównanie: Sync vs Async dla API Calls

### SYNCHRONICZNE API Call:

```php
// ❌ Blokuje request
public function generate() {
    $result = Http::get('https://api.example.com/data');
    // ↑ Request czeka na odpowiedź (może trwać sekundy)
    return response()->json($result);
}
```

**Kiedy używać:**
- ✅ Potrzebujesz odpowiedzi natychmiast
- ✅ Szybkie API (<100ms)
- ✅ Synchronous workflow (potrzebujesz danych do kontynuacji)

---

### ASYNCHRONICZNE API Call:

```php
// ✅ Nie blokuje request
public function generate() {
    ApiCallJob::dispatch($slug);
    return response()->json(['status' => 'processing'], 202);
}

class ApiCallJob implements ShouldQueue {
    public function handle(): void {
        $result = Http::get('https://api.example.com/data');
        // ↑ Wykonuje się w tle, request nie czeka
    }
}
```

**Kiedy używać:**
- ✅ Długie API calls (>100ms)
- ✅ Nie potrzebujesz odpowiedzi natychmiast
- ✅ Fire-and-forget pattern
- ✅ Background processing

---

## 📝 Porównanie: Sync vs Async dla Logowania

### SYNCHRONICZNE Logowanie (domyślne):

```php
// ✅ Szybko (~1-5ms), OK dla większości przypadków
Log::info('Message');
// ↑ Zapisuje natychmiast do pliku
```

**Kiedy używać:**
- ✅ Ważne logi (errors, critical)
- ✅ Potrzebujesz gwarancji zapisu
- ✅ Szybkie logowanie (nie wpływa na wydajność)

---

### ASYNCHRONICZNE Logowanie:

```php
// ✅ Nie blokuje request (dla wydajności)
Log::channel('queue')->info('Message');
// ↑ Tylko dispatch do queue, zapis w tle
```

**Kiedy używać:**
- ✅ Wysoka częstotliwość logów (performance critical)
- ✅ Debug logi (nie krytyczne)
- ✅ High-traffic endpoints
- ✅ Logging w hot paths

---

## 🎯 Best Practices

### 1. API Calls - Kiedy Async?

**Użyj async gdy:**
- ✅ API call może trwać >100ms
- ✅ Nie potrzebujesz odpowiedzi w request
- ✅ External services (AI, third-party APIs)
- ✅ Background processing

**Użyj sync gdy:**
- ✅ Szybkie API (<100ms)
- ✅ Potrzebujesz odpowiedzi natychmiast
- ✅ Authentication/Authorization APIs

---

### 2. Logowanie - Kiedy Async?

**Użyj async gdy:**
- ✅ High-frequency logging (performance critical)
- ✅ Debug/Info logs (nie krytyczne)
- ✅ High-traffic endpoints
- ✅ Logowanie nie jest krytyczne dla requestu

**Zostaw sync gdy:**
- ✅ Error/Critical logs (potrzebujesz gwarancji)
- ✅ Low-frequency logging
- ✅ Security audit logs

---

## 💡 Przykład: Twoja Aplikacja

### Obecna Implementacja:

```php
// GenerateMovieJob
public function handle(): void {
    // 1. Logowanie - SYNC (ale w Job, więc w tle - OK)
    Log::info('Starting movie generation', ['slug' => $this->slug]);
    
    // 2. Symulacja AI (sleep) - SYNC (ale w Job, więc w tle - OK)
    sleep(3);
    
    // 3. Database operations - SYNC (ale w Job, więc w tle - OK)
    Movie::create([...]);
    
    // Jeśli to byłby prawdziwy API call:
    // $result = Http::post('https://ai-api.com/generate', [...]);
    // ↑ To też byłoby OK - w Job (async), więc nie blokuje requestu
}
```

**To jest DOBRZE zaprojektowane! ✅**

**Dlaczego:**
- Request zwraca szybko (202)
- Długie operacje (AI/API) w Job (async)
- Logowanie w Job (nie blokuje requestu)

---

### Jeśli Chcesz Async Logging w Controller:

```php
public function generate() {
    $jobId = Str::uuid();
    
    // Async logging (jeśli skonfigurowane)
    Log::channel('queue')->info('Movie generation requested', [
        'slug' => $slug,
        'job_id' => $jobId
    ]);
    // ↑ Tylko dispatch - request nie czeka na zapis
    
    event(new MovieGenerationRequested($slug, $jobId));
    
    return response()->json(['job_id' => $jobId], 202);
}
```

**Timeline:**
```
0ms    Request
1ms    Controller
2ms    Log::channel('queue')->info() → Queue (szybko)
3ms    event() → Listener → Job::dispatch()
4ms    Response (202) ← REQUEST KONIECZNY ✅
       ↓
       ↓ ASYNC (w tle)
       ↓
Worker → Log zapisany do pliku
       → Job wykonuje AI generation
```

---

## 🔍 Konfiguracja Async Logging w Laravel

### Opcja 1: Queue Log Channel

```php
// config/logging.php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'queue'],  // ← Dodaj queue
    ],
    
    'queue' => [
        'driver' => 'monolog',
        'handler' => \Illuminate\Log\LogManager::class,
        'handler_with' => [
            'via' => \Illuminate\Log\LogManager::class,
        ],
    ],
],
```

**Użycie:**
```php
// Async logging
Log::channel('queue')->info('Message');

// Sync logging (domyślne)
Log::info('Message');
```

---

### Opcja 2: Custom Log Job

```php
// app/Jobs/LogMessageJob.php
class LogMessageJob implements ShouldQueue
{
    public function __construct(
        private string $level,
        private string $message,
        private array $context = []
    ) {}
    
    public function handle(): void
    {
        Log::log($this->level, $this->message, $this->context);
    }
}

// Helper function
function asyncLog(string $level, string $message, array $context = []): void
{
    LogMessageJob::dispatch($level, $message, $context);
}

// Użycie
asyncLog('info', 'Movie generation requested', ['slug' => $slug]);
```

---

## 📊 Podsumowanie

### API Calls:

| Typ | Sync/Async | Kiedy |
|-----|-----------|-------|
| **Standard HTTP** | ✅ SYNC | Szybkie API, potrzebujesz odpowiedzi |
| **W Job** | ✅ ASYNC | Długie API, background processing |
| **External Services** | ✅ ASYNC | AI, third-party APIs |

---

### Logowanie:

| Typ | Sync/Async | Kiedy |
|-----|-----------|-------|
| **Standard Log** | ✅ SYNC | Errors, Critical, Low frequency |
| **Queue Log** | ✅ ASYNC | High frequency, Debug, Performance critical |

---

## 🎯 Rekomendacje dla Twojej Aplikacji

### Obecna Implementacja - DOBRZE ✅

```php
// Controller - szybki
public function generate() {
    event(new MovieGenerationRequested($slug, $jobId));
    return response()->json([...], 202);
}

// Job - długie operacje w tle
class GenerateMovieJob {
    public function handle(): void {
        Log::info(...);           // OK - w Job (w tle)
        sleep(3);                 // OK - symulacja AI w tle
        // Jeśli prawdziwy API:
        // Http::post(...);        // OK - w Job (w tle)
        Movie::create([...]);      // OK - w Job (w tle)
    }
}
```

**Opcjonalne ulepszenia:**
1. **Async logging w Controller** (jeśli dużo requestów):
   ```php
   Log::channel('queue')->info(...);  // Async
   ```

2. **Async logging pozostaje OK w Job** (bo już w tle)

---

## 📚 Dokumentacja

- **Laravel HTTP Client:** https://laravel.com/docs/http-client
- **Laravel Logging:** https://laravel.com/docs/logging
- **Async Logging:** https://laravel.com/docs/logging#creating-custom-channels

