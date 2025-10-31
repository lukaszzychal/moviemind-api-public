# Events, Listeners, Jobs w Laravel - Kompletny Przewodnik

## 🎯 Podstawowe Koncepty

### 1. **Event (Wydarzenie)**

**Co to jest:**
Event to obiekt, który reprezentuje **coś co się stało** w aplikacji. Jest to prosty DTO (Data Transfer Object) z danymi.

**Przykład:**
```php
class MovieGenerationRequested
{
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
}
```

**Kiedy używać:**
- Gdy coś się dzieje (np. "user registered", "movie generation requested")
- Gdy chcesz rozdzielić **co się stało** od **co z tym zrobić**

---

### 2. **Listener (Odbiorca Wydarzenia)**

**Co to jest:**
Listener to klasa, która **reaguje** na Event. Wykonuje akcję gdy Event jest wyemitowany.

**Przykład:**
```php
class QueueMovieGenerationJob
{
    public function handle(MovieGenerationRequested $event): void
    {
        // Reaguje na event - dispatchuje Job
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}
```

**Kiedy używać:**
- Gdy chcesz wykonać akcję w odpowiedzi na Event
- Gdy chcesz mieć wiele akcji dla jednego Eventu

---

### 3. **Job (Zadanie w Kolejce)**

**Co to jest:**
Job to klasa, która reprezentuje **pracę do wykonania w tle** (asynchronicznie). Implementuje `ShouldQueue`.

**Przykład:**
```php
class GenerateMovieJob implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 90;
    
    public function handle(): void
    {
        // Wykonuje pracę (tworzy Movie, opis, itp.)
        // Może trwać długo - wykonuje się w tle
    }
}
```

**Kiedy używać:**
- Gdy masz długie operacje (AI generation, email sending, image processing)
- Gdy nie chcesz blokować requestu
- Gdy potrzebujesz retry/timeout

---

## 🔄 Jak To Działa - Flow

### Przykład: Generowanie Filmu

```
1. Controller
   ↓
   event(new MovieGenerationRequested($slug, $jobId))
   ↓
2. Laravel Event Dispatcher
   ↓
   EventServiceProvider::class sprawdza $listen
   ↓
3. Listener: QueueMovieGenerationJob
   ↓
   handle(MovieGenerationRequested $event)
   ↓
   GenerateMovieJob::dispatch($slug, $jobId)
   ↓
4. Queue System
   ↓
   Job trafia do Redis/Database queue
   ↓
5. Queue Worker (Horizon/queue:work)
   ↓
   GenerateMovieJob::handle()
   ↓
   Wykonuje pracę (tworzy Movie)
```

### Krok po Kroku:

**1. Controller emituje Event:**
```php
event(new MovieGenerationRequested('the-matrix', 'job-123'));
```

**2. Laravel szuka Listenerów:**
```php
// EventServiceProvider.php
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,  // ← Ten listener się wykona
    ],
];
```

**3. Listener reaguje:**
```php
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}
```

**4. Job trafia do kolejki:**
```php
// GenerateMovieJob jest serializowany i zapisany w queue
// Worker odbiera i wykonuje
```

**5. Worker wykonuje Job:**
```php
// W tle (nie blokuje requestu)
$job = new GenerateMovieJob('the-matrix', 'job-123');
$job->handle(); // Tworzy Movie
```

---

## 📊 Porównanie: Laravel vs Symfony

### Laravel Events + Listeners

**Jak działa:**
```php
// 1. Event
class MovieGenerationRequested { ... }

// 2. Listener
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event) { ... }
}

// 3. Rejestracja
// EventServiceProvider.php
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,
    ],
];

// 4. Emisja
event(new MovieGenerationRequested($slug, $jobId));
```

**Charakterystyka:**
- ✅ Prostsze API
- ✅ Auto-discovery możliwe
- ✅ Event + Listener parowane w konfiguracji
- ✅ Event może być DTO lub Model

---

### Symfony EventDispatcher + EventSubscriber

**Jak działa:**
```php
// 1. Event (może mieć stopPropagation)
class MovieGenerationRequested extends Event
{
    private $slug;
    private $jobId;
    
    public function __construct(string $slug, string $jobId)
    {
        $this->slug = $slug;
        $this->jobId = $jobId;
    }
    
    // Symfony Events często mają metody kontrolne
    public function stopPropagation(): void { ... }
}

// 2. Listener (może być callable lub service)
class QueueMovieGenerationJobListener
{
    public function onMovieGenerationRequested(MovieGenerationRequested $event): void
    {
        // ...
    }
}

// 3. Rejestracja
// services.yaml
services:
    App\Listeners\QueueMovieGenerationJobListener:
        tags:
            - { name: kernel.event_listener, event: movie.generation.requested }

// Lub EventSubscriber
class QueueMovieGenerationJobSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MovieGenerationRequested::class => 'onMovieGenerationRequested',
        ];
    }
}

// 4. Emisja
$eventDispatcher->dispatch(new MovieGenerationRequested($slug, $jobId), 'movie.generation.requested');
```

**Charakterystyka:**
- ✅ Bardziej elastyczne (event names, priorities, stopPropagation)
- ✅ EventDispatcher jako serwis
- ✅ Event może zatrzymać propagację
- ⚠️ Więcej konfiguracji

---

## 🔍 Główne Różnice

| Aspekt | Laravel | Symfony |
|--------|---------|---------|
| **API** | `event(new Event())` | `$dispatcher->dispatch($event, $name)` |
| **Event Name** | Klasa Event = nazwa | Osobny parametr `$name` |
| **Propagation** | ❌ Brak | ✅ `stopPropagation()` |
| **Priority** | ⚠️ Kolejność w array | ✅ `priority` w tag |
| **Auto-discovery** | ✅ Tak | ⚠️ EventSubscriber tylko |
| **Rejestracja** | `$listen` array | `services.yaml` tags lub EventSubscriber |

---

## 🎯 Events vs Jobs - Kiedy Czego Używać?

### Events - Kiedy?

**Używaj Events gdy:**
- ✅ Chcesz **poinformować** że coś się stało
- ✅ Chcesz **rozłączyć** "co się stało" od "co z tym zrobić"
- ✅ Potrzebujesz **wiele akcji** dla jednego wydarzenia
- ✅ Potrzebujesz **synchroniczne** wykonanie (lub mieszane)

**Przykład:**
```php
// User się zarejestrował
event(new UserRegistered($user));

// Listener 1: Wyślij email powitalny
// Listener 2: Utwórz profile
// Listener 3: Zaloguj analitykę
// Wszystkie synchronicznie lub każdy może mieć własną kolejkę
```

---

### Jobs - Kiedy?

**Używaj Jobs gdy:**
- ✅ Masz **długą operację** (AI, processing, email)
- ✅ Potrzebujesz **asynchroniczne** wykonanie (nie blokuj requestu)
- ✅ Potrzebujesz **retry logic** (`$tries`, `$timeout`)
- ✅ Potrzebujesz **monitoring** (Horizon dashboard)

**Przykład:**
```php
// Długie operacje w tle
GenerateMovieJob::dispatch($slug, $jobId);
SendEmailJob::dispatch($user);
ProcessImageJob::dispatch($imageId);
```

---

### Events + Jobs - Połączenie (Best Practice)

**Najlepsze podejście:**
```
Event → Listener → Job
```

**Dlaczego?**
1. **Event** - komunikuje że coś się stało (loose coupling)
2. **Listener** - decyduje co zrobić (może być wiele listenerów)
3. **Job** - wykonuje długą pracę w tle (async, retry)

**Przykład:**
```php
// Controller - tylko emituje event
event(new MovieGenerationRequested($slug, $jobId));

// Listener - może być wiele!
class QueueMovieGenerationJob { ... }
class SendNotificationOnMovieGeneration { ... }
class LogMovieGenerationRequest { ... }

// Job - wykonuje długą pracę
class GenerateMovieJob implements ShouldQueue { ... }
```

---

## 📝 Konkretne Przykłady

### Przykład 1: Event bez Job (Synchronicznie)

```php
// Event
class UserRegistered {
    public function __construct(public User $user) {}
}

// Listener - wykonuje synchronicznie
class SendWelcomeEmail {
    public function handle(UserRegistered $event): void {
        Mail::to($event->user)->send(new WelcomeEmail);
    }
}
```

---

### Przykład 2: Event → Job (Asynchronicznie)

```php
// Event
class MovieGenerationRequested {
    public function __construct(public string $slug, public string $jobId) {}
}

// Listener - dispatchuje Job
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// Job - wykonuje w tle
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void {
        // Długa operacja AI...
    }
}
```

---

### Przykład 3: Event → Multiple Listeners

```php
// Event
class OrderPlaced {
    public function __construct(public Order $order) {}
}

// Listener 1: Email
class SendOrderConfirmation {
    public function handle(OrderPlaced $event): void {
        Mail::to($event->order->user)->send(...);
    }
}

// Listener 2: Queue processing
class QueueOrderProcessing {
    public function handle(OrderPlaced $event): void {
        ProcessOrderJob::dispatch($event->order->id);
    }
}

// Listener 3: Analytics
class TrackOrderAnalytics {
    public function handle(OrderPlaced $event): void {
        Analytics::track('order.placed', $event->order);
    }
}
```

Wszystkie trzy wykonują się gdy `event(new OrderPlaced($order))` jest wywołane!

---

## 🔄 Symfonia vs Laravel - Szczegółowe Porównanie

### Symfony EventDispatcher

```php
// Symfony
use Symfony\Component\EventDispatcher\EventDispatcher;

// 1. Event
class MovieGenerationRequested {
    private $slug;
    // ... getters/setters
}

// 2. Listener (callable)
$listener = function(MovieGenerationRequested $event) {
    // ...
};

// 3. Rejestracja
$dispatcher->addListener('movie.generation.requested', $listener, 10);

// 4. Emisja
$dispatcher->dispatch(new MovieGenerationRequested($slug), 'movie.generation.requested');
```

**Kluczowe różnice:**
- Event name jest **stringiem** (`'movie.generation.requested'`)
- Listener może być **callable** lub service
- **Priority** jako trzeci parametr
- **stopPropagation()** dostępne

---

### Laravel Events

```php
// Laravel
// 1. Event
class MovieGenerationRequested {
    public function __construct(public string $slug, public string $jobId) {}
}

// 2. Listener (class)
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event) { ... }
}

// 3. Rejestracja
// EventServiceProvider.php
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,
    ],
];

// 4. Emisja
event(new MovieGenerationRequested($slug, $jobId));
```

**Kluczowe różnice:**
- Event name jest **nazwą klasy**
- Listener to **dedykowana klasa**
- Prostsze API (helper function `event()`)
- Brak stopPropagation (ale można zrezygnować z listenera)

---

## 🎓 Jobs w Laravel - Szczegółowo

### Co to jest Job?

Job to klasa reprezentująca **pracę do wykonania w tle**. Różni się od Event tym, że:

- **Event** = "coś się stało" (informacja)
- **Job** = "coś do zrobienia" (akcja)

### Struktura Job:

```php
class GenerateMovieJob implements ShouldQueue
{
    // Queue traits
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    // Retry settings
    public int $tries = 3;           // Ile razy próbować
    public int $timeout = 90;         // Timeout w sekundach
    public array $backoff = [10, 30]; // Delay między próbami
    
    // Dane Job
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
    
    // Wykonanie
    public function handle(): void {
        // Główna logika
    }
    
    // Failed handling
    public function failed(\Throwable $exception): void {
        // Co zrobić gdy wszystkie próby się nie powiodły
    }
}
```

### Różnica: Job vs Closure

```php
// ❌ Closure (trudne testowanie, brak retry)
Bus::dispatch(function () {
    // ...
});

// ✅ Job (łatwe testowanie, retry, monitoring)
GenerateMovieJob::dispatch($slug, $jobId);
```

---

## 🔗 Events + Jobs - Pełny Flow

### Przykład: Twoja aplikacja

```php
// 1. Controller
class GenerateController {
    public function generate() {
        $jobId = Str::uuid();
        
        // Emituje Event
        event(new MovieGenerationRequested($slug, $jobId));
        
        return response()->json(['job_id' => $jobId], 202);
    }
}

// 2. Event
class MovieGenerationRequested {
    public function __construct(public string $slug, public string $jobId) {}
}

// 3. EventServiceProvider
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,  // Listener
    ],
];

// 4. Listener
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        // Dispatchuje Job
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// 5. Job
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void {
        // Długa operacja AI...
        sleep(3);
        Movie::create([...]);
    }
}

// 6. Queue Worker
php artisan queue:work  // Wykonuje Job w tle
```

### Flow:

```
Request → Controller → Event → Listener → Job → Queue → Worker → Done
         (sync)      (sync)   (sync)     (async) (async) (async)
```

---

## 🆚 Laravel vs Symfony - Tabela Porównawcza

| Aspekt | Laravel | Symfony |
|--------|---------|---------|
| **Event API** | `event(new Event())` | `$dispatcher->dispatch($event, $name)` |
| **Event Name** | Nazwa klasy | String parameter |
| **Listener Registration** | `$listen` array | `services.yaml` tags lub EventSubscriber |
| **Priority** | Kolejność w array | `priority` w tag |
| **Stop Propagation** | ❌ Brak | ✅ `$event->stopPropagation()` |
| **Queued Listeners** | ✅ `implements ShouldQueue` | ⚠️ Ręcznie przez Messenger |
| **Jobs** | ✅ Wbudowane (`ShouldQueue`) | ✅ Messenger component |
| **Retry Logic** | ✅ `$tries`, `$timeout` | ✅ Messenger retry strategy |
| **Monitoring** | ✅ Horizon | ✅ Symfony Messenger UI |

---

## 💡 Best Practices

### 1. **Event = Informacja, Job = Akcja**

```php
// ✅ DOBRZE
event(new MovieGenerationRequested($slug, $jobId)); // Informuje
GenerateMovieJob::dispatch($slug, $jobId);          // Wykonuje

// ❌ ŹLE - Mieszanie
event(new GenerateMovie(...)); // Event nie powinien wykonywać
```

### 2. **Użyj Events dla Loose Coupling**

```php
// Controller nie wie co się stanie
event(new MovieGenerationRequested($slug, $jobId));

// Może być wiele listenerów:
// - QueueMovieGenerationJob
// - SendNotification
// - LogAnalytics
// Controller nie musi o nich wiedzieć!
```

### 3. **Jobs dla Długich Operacji**

```php
// ✅ Job dla AI generation (długie)
class GenerateMovieJob implements ShouldQueue { ... }

// ✅ Event dla prostych akcji (szybkie)
event(new UserRegistered($user)); // Email, logowanie - szybko
```

### 4. **Event → Job Pattern**

Najlepsze podejście dla długich operacji:

```php
// Event emituje informację
event(new MovieGenerationRequested($slug, $jobId));

// Listener dispatchuje Job
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// Job wykonuje pracę
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void { /* ... */ }
}
```

---

## 🎯 Podsumowanie

### Events w Laravel:
- **Co:** Informacja że coś się stało
- **Kiedy:** Rozłączenie logiki, multiple actions
- **Jak:** `event(new Event())` → Listener reaguje

### Jobs w Laravel:
- **Co:** Praca do wykonania w tle
- **Kiedy:** Długie operacje, async, retry needed
- **Jak:** `Job::dispatch()` → Queue Worker wykonuje

### Porównanie z Symfony:
- **Symfony:** EventDispatcher + Messenger (osobne komponenty)
- **Laravel:** Events + Jobs (zintegrowane)
- **Różnice:** API, propagation, priority handling

### Best Practice:
**Event → Listener → Job** pattern dla długich operacji:
- Event = komunikacja (loose coupling)
- Listener = decyzja (co zrobić)
- Job = wykonanie (w tle, async)

