# Refaktoryzacja: Service vs Events/Jobs - Szczegółowe Porównanie

## 📊 Analiza Obecnej Architektury

### Obecny Flow:

```
GenerateController::generate()
  ↓
$this->ai->queueMovieGeneration($slug, $jobId)
  ↓
MockAiService::queueMovieGeneration()
  ↓ sprawdza czy istnieje → cache → return
  ↓ jeśli nie istnieje → Cache::put(PENDING)
  ↓ Bus::dispatch(function() { ... 70+ linii kodu ... })
  ↓
Queue Worker wykonuje closure
```

### Problemy:

1. ❌ **Duże closure (70+ linii)** - trudne do utrzymania
2. ❌ **Brak retry logic** - closure nie może mieć `$tries`, `$timeout`
3. ❌ **Trudne testowanie** - nie można mockować closure jako klasy
4. ❌ **Mieszanie odpowiedzialności** - Service robi: cache, queue, logika biznesowa
5. ❌ **Brak event-driven extensibility** - trudno dodać hooki
6. ❌ **Tight coupling** - Controller zależny od AiServiceInterface

---

## ✅ Proponowana Architektura: Events + Jobs

### Nowy Flow:

```
GenerateController::generate()
  ↓ sprawdza feature flag
  ↓ sprawdza czy istnieje → return 200
  ↓ Cache::put(PENDING)
  ↓ event(new MovieGenerationRequested($slug, $jobId))
  ↓
EventServiceProvider → QueueMovieGenerationJob listener
  ↓
GenerateMovieJob::dispatch($slug, $jobId)
  ↓
Queue Worker wykonuje Job
  ↓ retry, timeout, failed() handling
```

### Zalety:

1. ✅ **Dedykowane klasy** - każda klasa ma jedną odpowiedzialność
2. ✅ **Retry logic** - `$tries = 3`, `$timeout = 90`, `$backoff`
3. ✅ **Łatwe testowanie** - mock Events, Jobs osobno
4. ✅ **Separation of concerns** - Event, Listener, Job, Service
5. ✅ **Event-driven** - łatwo dodać więcej listenerów
6. ✅ **Loose coupling** - Controller tylko emituje event
7. ✅ **Horizon support** - lepsze monitorowanie (widzisz nazwy Job classes)

---

## 📈 Porównanie Kodowe

### PRZED (Service + Closure):

```php
// Controller
$this->ai->queueMovieGeneration($slug, $jobId);

// Service
public function queueMovieGeneration(string $slug, string $jobId): void
{
    $already = Movie::where('slug', $slug)->first();
    if ($already) {
        Cache::put(...); // DONE
        return;
    }
    
    Cache::put(...); // PENDING
    
    Bus::dispatch(function () use ($slug, $jobId) {
        try {
            sleep(3);
            // ... 70 linii kodu ...
            Cache::put(...); // DONE/FAILED
        } catch (\Throwable $e) {
            Log::error(...);
            Cache::put(...); // FAILED
        }
    });
}
```

**Problemy:**
- Closure nie może mieć `$tries`, `$timeout`
- Trudne testowanie
- Mieszana odpowiedzialność

### PO (Events + Jobs):

```php
// Controller
event(new MovieGenerationRequested($slug, $jobId));

// Event
class MovieGenerationRequested {
    public function __construct(public string $slug, public string $jobId) {}
}

// Listener
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// Job
class GenerateMovieJob implements ShouldQueue {
    public $tries = 3;      // ✅ Retry
    public $timeout = 90;   // ✅ Timeout
    
    public function handle(): void {
        // ... logika biznesowa ...
    }
    
    public function failed(\Throwable $exception): void {
        // ✅ Dedicated failed handling
    }
}
```

**Korzyści:**
- ✅ Retry, timeout, backoff
- ✅ Łatwe testowanie
- ✅ Separation of concerns
- ✅ Horizon monitoring

---

## 🎯 Kiedy Używać Każdego Podejścia?

### Service Approach - Użyj gdy:

- ✅ Prosta logika (1-2 linie)
- ✅ Nie potrzebujesz retry/timeout
- ✅ Nie potrzebujesz event-driven extensibility
- ✅ MVP/prototyp

### Events + Jobs - Użyj gdy:

- ✅ Złożona logika (10+ linii)
- ✅ Potrzebujesz retry/timeout
- ✅ Potrzebujesz event-driven extensibility
- ✅ Produkcja/enterprise
- ✅ Monitoring (Horizon)

---

## 📝 Implementacja - Krok po Kroku

### Krok 1: Utworzono (już zrobione)

✅ Events: `MovieGenerationRequested`, `PersonGenerationRequested`
✅ Listeners: `QueueMovieGenerationJob`, `QueuePersonGenerationJob`
✅ Jobs: `GenerateMovieJob`, `GeneratePersonJob`
✅ EventServiceProvider: Zarejestrowany w `bootstrap/providers.php`

### Krok 2: Aktualizuj Controllery

**GenerateController.php:**
```php
// PRZED
$this->ai->queueMovieGeneration($slug, $jobId);

// PO
event(new MovieGenerationRequested($slug, $jobId));
```

**MovieController.php** (linia 77):
```php
// PRZED
$this->ai->queueMovieGeneration($slug, $jobId);

// PO
Cache::put("ai_job:{$jobId}", ['status' => 'PENDING', ...], 15);
event(new MovieGenerationRequested($slug, $jobId));
```

**PersonController.php** (linia 35):
```php
// PRZED
$this->ai->queuePersonGeneration($slug, $jobId);

// PO
Cache::put("ai_job:{$jobId}", ['status' => 'PENDING', ...], 15);
event(new PersonGenerationRequested($slug, $jobId));
```

### Krok 3: Usuń Dependency Injection

```php
// PRZED
public function __construct(private readonly AiServiceInterface $ai) {}

// PO
// Usuń - nie potrzebne!
```

### Krok 4: Testy

```php
// Test Event dispatch
Event::fake();
event(new MovieGenerationRequested('the-matrix', 'job-123'));
Event::assertDispatched(MovieGenerationRequested::class);

// Test Job dispatch
Queue::fake();
event(new MovieGenerationRequested('the-matrix', 'job-123'));
Queue::assertPushed(GenerateMovieJob::class);

// Test Job execution
$job = new GenerateMovieJob('the-matrix', 'job-123');
$job->handle();
// Assertions...
```

---

## 🔄 Migracja - Stopniowa

### Opcja 1: Hybrid (Backward Compatible)

Utrzymaj Service, ale niech dispatchuje Event:

```php
// MockAiService - refactored
public function queueMovieGeneration(string $slug, string $jobId): void
{
    event(new MovieGenerationRequested($slug, $jobId));
}
```

**Zalety:** Controller nie trzeba zmieniać od razu

### Opcja 2: Pełna Refaktoryzacja (Rekomendowane)

1. Dodaj Events/Jobs/Listeners ✅ (zrobione)
2. Zmień Controller na `event(...)` 
3. Usuń `AiServiceInterface` dependency
4. Testuj
5. Usuń Service code

---

## ✅ Rekomendacja

**REFAKTORYZUJ na Events + Jobs** ✅

**Powody:**
1. ✅ To jest Laravel best practice
2. ✅ Lepsza testowalność
3. ✅ Retry/timeout out-of-the-box
4. ✅ Horizon monitoring
5. ✅ Event-driven extensibility
6. ✅ Loose coupling

**Plan:**
1. Utworzono Events/Jobs/Listeners ✅
2. Zmień Controller na `event(...)` 
3. Testuj
4. Usuń Service (opcjonalnie, można zostawić jako fallback)

---

## 📚 Dokumentacja

- `docs/ARCHITECTURE_ANALYSIS.md` - Analiza obecnej architektury
- `docs/REFACTOR_PROPOSAL.md` - Szczegółowa propozycja z przykładami
- `docs/REFACTOR_COMPARISON.md` - Ten dokument

---

## 🚀 Szybki Start

**Gotowe do użycia:**
- ✅ Events utworzone
- ✅ Jobs utworzone
- ✅ Listeners utworzone
- ✅ EventServiceProvider zarejestrowany

**Do zrobienia:**
1. Zmień Controller: `$this->ai->queue...()` → `event(new ...())`
2. Usuń `AiServiceInterface` z constructor
3. Testuj

**Przykład refaktoryzowanego Controller:**
Zobacz: `app/Http/Controllers/Api/GenerateController.refactored.php`

