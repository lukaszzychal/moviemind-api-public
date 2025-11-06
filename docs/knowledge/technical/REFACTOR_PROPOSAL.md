# Propozycja Refaktoryzacji: Service → Events + Jobs

## 🎯 Cel Refaktoryzacji

Zamiana `AiServiceInterface` + `Bus::dispatch(closure)` na **Events + Jobs** dla lepszej architektury Laravel.

---

## 📐 Nowa Architektura

### Struktura:

```
app/
├── Events/
│   ├── MovieGenerationRequested.php
│   └── PersonGenerationRequested.php
├── Listeners/
│   ├── QueueMovieGenerationJob.php
│   └── QueuePersonGenerationJob.php
├── Jobs/
│   ├── GenerateMovieJob.php (implements ShouldQueue)
│   └── GeneratePersonJob.php (implements ShouldQueue)
```

---

## 🔄 Porównanie: Przed vs Po

### PRZED (Service Approach):

```php
// Controller
$this->ai->queueMovieGeneration($slug, $jobId);

// Service
Bus::dispatch(function () use ($slug, $jobId) {
    // 70+ linii kodu w closure
    sleep(3);
    // ... logika ...
});
```

### PO (Event + Job Approach):

```php
// Controller
event(new MovieGenerationRequested($slug, $jobId));

// Event
class MovieGenerationRequested {
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
}

// Listener
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// Job
class GenerateMovieJob implements ShouldQueue {
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
    
    public $tries = 3;
    public $timeout = 90;
    
    public function handle(): void {
        // ... logika z MockAiService ...
    }
}
```

---

## ✅ Zalety Nowego Podejścia

### 1. **Separation of Concerns**

- **Event** - komunikuje że coś się stało
- **Listener** - decyduje co zrobić (queue job)
- **Job** - wykonuje pracę

### 2. **Testowalność**

```php
// Test Event
Event::fake();
event(new MovieGenerationRequested('the-matrix', 'job-123'));
Event::assertDispatched(MovieGenerationRequested::class);

// Test Job
Queue::fake();
GenerateMovieJob::dispatch('the-matrix', 'job-123');
Queue::assertPushed(GenerateMovieJob::class);
```

### 3. **Retry & Timeout**

```php
class GenerateMovieJob implements ShouldQueue {
    public $tries = 3;           // ✅ Automatyczne retry
    public $timeout = 90;        // ✅ Timeout handling
    public $backoff = [10, 30];  // ✅ Exponential backoff
}
```

### 4. **Monitoring**

- Horizon dashboard pokazuje Job classes
- Lepsze logowanie (nazwa klasy zamiast closure)
- Metryki per Job class

### 5. **Extensibility**

```php
// Łatwo dodać więcej listenerów
class SendNotificationOnGenerationRequested {
    public function handle(MovieGenerationRequested $event): void {
        // Wyślij email/Slack
    }
}

// Zarejestruj w EventServiceProvider
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,
        SendNotificationOnGenerationRequested::class, // ← Nowy
    ],
];
```

### 6. **Loose Coupling**

Controller nie zna implementacji - tylko emituje event:

```php
// Controller
event(new MovieGenerationRequested($slug, $jobId));
// Nie wie czy to Job, Email, czy coś innego!
```

---

## 📝 Implementacja

### 1. Event: `MovieGenerationRequested`

```php
<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MovieGenerationRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
}
```

### 2. Listener: `QueueMovieGenerationJob`

```php
<?php

namespace App\Listeners;

use App\Events\MovieGenerationRequested;
use App\Jobs\GenerateMovieJob;

class QueueMovieGenerationJob
{
    public function handle(MovieGenerationRequested $event): void
    {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}
```

### 3. Job: `GenerateMovieJob`

```php
<?php

namespace App\Jobs;

use App\Models\Movie;
use App\Models\MovieDescription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;
    
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}

    public function handle(): void
    {
        try {
            // Check if already exists
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);
                return;
            }

            // Simulate AI generation
            sleep(3);

            // Double-check (race condition protection)
            $existing = Movie::where('slug', $this->slug)->first();
            if ($existing) {
                $this->updateCache('DONE', $existing->id);
                return;
            }

            // Parse and generate
            $parsed = Movie::parseSlug($this->slug);
            $title = $parsed['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
            $releaseYear = $parsed['year'] ?? 1999;
            $director = $parsed['director'] ?? 'Mock AI Director';
            $uniqueSlug = Movie::generateSlug($title, $releaseYear, $director);

            // Create movie
            $movie = Movie::create([
                'title' => (string) $title,
                'slug' => $uniqueSlug,
                'release_year' => $releaseYear,
                'director' => $director,
                'genres' => ['Sci-Fi', 'Action'],
            ]);

            // Create description
            $desc = MovieDescription::create([
                'movie_id' => $movie->id,
                'locale' => 'en_US',
                'text' => "Generated description for {$title}. This text was produced by MockAiService.",
                'context_tag' => 'DEFAULT',
                'origin' => 'GENERATED',
                'ai_model' => 'mock-ai-1',
            ]);

            $movie->default_description_id = $desc->id;
            $movie->save();

            $this->updateCache('DONE', $movie->id, $uniqueSlug);
        } catch (\Throwable $e) {
            Log::error('GenerateMovieJob failed', [
                'slug' => $this->slug,
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->updateCache('FAILED');
            
            throw $e; // Re-throw for retry mechanism
        }
    }

    private function updateCache(string $status, ?int $id = null, ?string $slug = null): void
    {
        Cache::put("ai_job:{$this->jobId}", [
            'job_id' => $this->jobId,
            'status' => $status,
            'entity' => 'MOVIE',
            'slug' => $slug ?? $this->slug,
            'id' => $id,
        ], now()->addMinutes(15));
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateMovieJob permanently failed', [
            'slug' => $this->slug,
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
        ]);
        
        Cache::put("ai_job:{$this->jobId}", [
            'job_id' => $this->jobId,
            'status' => 'FAILED',
            'entity' => 'MOVIE',
            'slug' => $this->slug,
            'error' => $exception->getMessage(),
        ], now()->addMinutes(15));
    }
}
```

### 4. Controller - Nowa Wersja

```php
// GenerateController.php
public function generate(GenerateRequest $request)
{
    $validated = $request->validated();
    $entityType = $validated['entity_type'];
    $slug = (string) $validated['entity_id'];
    $jobId = (string) Str::uuid();

    return match ($entityType) {
        'MOVIE' => $this->handleMovieGeneration($slug, $jobId),
        'PERSON' => $this->handlePersonGeneration($slug, $jobId),
        default => response()->json(['error' => 'Invalid entity type'], 400),
    };
}

private function handleMovieGeneration(string $slug, string $jobId): JsonResponse
{
    if (! Feature::active('ai_description_generation')) {
        return response()->json(['error' => 'Feature not available'], 403);
    }

    // Check if already exists - early return
    $existing = Movie::where('slug', $slug)->first();
    if ($existing) {
        return response()->json([
            'job_id' => $jobId,
            'status' => 'DONE',
            'entity' => 'MOVIE',
            'slug' => $slug,
            'id' => $existing->id,
        ], 200);
    }

    // Emit event instead of calling service
    event(new MovieGenerationRequested($slug, $jobId));

    return $this->queuedResponse($jobId, $slug, 'movie');
}
```

### 5. EventServiceProvider

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,
    ],
    PersonGenerationRequested::class => [
        QueuePersonGenerationJob::class,
    ],
];
```

---

## 🚀 Migracja Krok po Kroku

### Faza 1: Tworzenie struktur (backward compatible)

1. Utwórz Events (`MovieGenerationRequested`, `PersonGenerationRequested`)
2. Utwórz Listeners (`QueueMovieGenerationJob`, `QueuePersonGenerationJob`)
3. Utwórz Jobs (`GenerateMovieJob`, `GeneratePersonJob`)
4. Zarejestruj w `EventServiceProvider`

### Faza 2: Refaktoryzacja Service

1. Przenieś logikę z `MockAiService` do Job classes
2. Listener dispatches Job zamiast `Bus::dispatch(closure)`

### Faza 3: Aktualizacja Controllerów

1. Zamień `$this->ai->queueMovieGeneration()` na `event(new MovieGenerationRequested())`
2. Usuń dependency injection `AiServiceInterface` z controllerów
3. Testuj każdy endpoint

### Faza 4: Usunięcie starego kodu

1. Usuń `AiServiceInterface`
2. Usuń `MockAiService` (lub pozostaw jako fallback)
3. Usuń binding w `AppServiceProvider`

---

## ⚠️ Uwagi Migracyjne

### 1. **Early Return Logic**

Obecnie `MockAiService` sprawdza czy entity istnieje **przed** dispatch. To powinno zostać w Controllerze:

```php
// Controller - sprawdź wcześniej
$existing = Movie::where('slug', $slug)->first();
if ($existing) {
    return response()->json(['status' => 'DONE', ...], 200);
}

// Dopiero potem emit event
event(new MovieGenerationRequested($slug, $jobId));
```

### 2. **Cache Management**

Cache można przenieść do Job lub stworzyć dedykowany service:

```php
// app/Services/JobStatusService.php
class JobStatusService {
    public function markPending(string $jobId, string $entity): void
    public function markDone(string $jobId, string $entity, int $id, ?string $slug = null): void
    public function markFailed(string $jobId, string $entity, ?string $error = null): void
    public function getStatus(string $jobId): ?array
}
```

### 3. **Feature Flags**

Feature flags mogą zostać w Controllerze (jak teraz) lub w Listenerze:

```php
// Listener
public function handle(MovieGenerationRequested $event): void
{
    if (! Feature::active('ai_description_generation')) {
        return; // Skip jeśli feature off
    }
    
    GenerateMovieJob::dispatch($event->slug, $event->jobId);
}
```

---

## 📊 Rekomendacja

**TAK - Refaktoryzuj na Events + Jobs** ✅

**Powody:**
1. ✅ Zgodne z Laravel best practices
2. ✅ Lepsza testowalność
3. ✅ Retry logic out-of-the-box
4. ✅ Lepsze monitorowanie
5. ✅ Łatwiejsze rozszerzanie
6. ✅ Loose coupling

**Kiedy zacząć:**
- Można zrobić to stopniowo (backward compatible)
- Najpierw dodaj Events/Jobs obok Service
- Potem zamień Controller
- Na końcu usuń Service

---

## 🎬 Alternatywa: Tylko Jobs (bez Events)

Jeśli Events wydają się overkill, można użyć tylko Jobs:

```php
// Controller
$jobId = (string) Str::uuid();
Cache::put("ai_job:{$jobId}", ['status' => 'PENDING'], 15);

GenerateMovieJob::dispatch($slug, $jobId);
```

**Zalety:** Prostsze, mniej warstw
**Wady:** Brak event-driven extensibility

**Rekomendacja:** Events + Jobs jest lepsze dla przyszłości, ale Jobs-only też jest OK dla MVP.

