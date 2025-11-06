# 🔄 Action Pattern vs Event-Driven Architecture - Analiza

**Data utworzenia:** 2025-11-04  
**Status:** ✅ Zaimplementowane  
**Kontekst:** Refaktoryzacja kontrolerów API zgodnie z SOLID

---

## 📋 **Przegląd**

Dokument analizuje decyzję architektoniczną dotyczącą użycia **Action Pattern** jako warstwy pośredniej między kontrolerami a **Event-Driven Architecture** w MovieMind API.

**Kluczowe pytanie:** Dlaczego użyto Action Pattern zamiast bezpośredniego dispatchowania Event w kontrolerach?

**Odpowiedź:** Action Pattern **nie zastępuje** Event-Driven Architecture, ale **współpracuje z nią**, dodając warstwę enkapsulacji i eliminując duplikację kodu.

---

## 🎯 **Obecna Implementacja**

### **Architektura Przepływu Danych:**

```
┌─────────────────────┐
│  GenerateController │  (HTTP Request Handler)
│  (linia 68)         │
└──────────┬──────────┘
           │
           │ $result = $action->handle($slug, $confidence)
           ↓
┌─────────────────────┐
│ QueueMovieGeneration│  (Action Pattern)
│ Action              │
└──────────┬──────────┘
           │
           │ event(new MovieGenerationRequested(...))
           ↓
┌─────────────────────┐
│ MovieGenerationReq  │  (Event)
│ uested              │
└──────────┬──────────┘
           │
           │ Laravel Event Dispatcher
           ↓
┌─────────────────────┐
│ QueueMovieGeneration│  (Listener)
│ Job                 │
└──────────┬──────────┘
           │
           │ Job::dispatch()
           ↓
┌─────────────────────┐
│ MockGenerateMovieJob│  (Job - Queue Worker)
│ RealGenerateMovieJob│
└─────────────────────┘
```

---

## 📊 **Porównanie Podejść**

### **Podejście 1: Bezpośredni Event Dispatch (Poprzednie)**

**Implementacja:**
```php
// GenerateController.php (stara wersja)
public function handleMovieGeneration(string $slug, string $jobId): JsonResponse
{
    // 1. Tworzenie jobId
    $jobId = (string) Str::uuid();
    
    // 2. Inicjalizacja cache (hardcoded w kontrolerze)
    Cache::put("ai_job:{$jobId}", [
        'job_id' => $jobId,
        'status' => 'PENDING',
        'entity' => 'MOVIE',
        'slug' => $slug,
    ], now()->addMinutes(15));
    
    // 3. Dispatch Event
    event(new MovieGenerationRequested($slug, $jobId));
    
    // 4. Formatowanie odpowiedzi
    return response()->json([
        'job_id' => $jobId,
        'status' => 'PENDING',
        'message' => 'Generation queued for movie by slug',
        'slug' => $slug,
    ], 202);
}
```

**Problem:** Ten sam kod był duplikowany w:
- `GenerateController`
- `MovieController`
- `PersonController`

---

### **Podejście 2: Action Pattern (Obecne)**

**Implementacja:**
```php
// GenerateController.php (linia 68)
$result = $this->queueMovieGenerationAction->handle($slug, $validation['confidence']);
return response()->json($result, 202);
```

```php
// QueueMovieGenerationAction.php
class QueueMovieGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(string $slug, ?float $confidence = null): array
    {
        // 1. Tworzenie jobId
        $jobId = (string) Str::uuid();

        // 2. Inicjalizacja cache (JobStatusService)
        $this->jobStatusService->initializeStatus(
            $jobId,
            'MOVIE',
            $slug,
            $confidence
        );

        // 3. Dispatch Event (Event-Driven Architecture działa dalej!)
        event(new MovieGenerationRequested($slug, $jobId));

        // 4. Formatowanie odpowiedzi
        return [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
            'confidence' => $confidence,
            'confidence_level' => $this->getConfidenceLevel($confidence),
        ];
    }

    private function getConfidenceLevel(float $confidence): string
    {
        return match (true) {
            $confidence >= 0.9 => 'high',
            $confidence >= 0.7 => 'medium',
            $confidence >= 0.5 => 'low',
            default => 'very_low',
        };
    }
}
```

**Korzyści:**
- ✅ Jeden Action używany przez wszystkie kontrolery
- ✅ Encapsulation - logika queueing w jednym miejscu
- ✅ Łatwiejsze testowanie
- ✅ Konsystencja w całej aplikacji

---

## 🔍 **Dlaczego Action Pattern?**

### **1. Single Responsibility Principle (SOLID)**

**Podział odpowiedzialności:**

| Komponent | Odpowiedzialność |
|-----------|------------------|
| **Controller** | HTTP request/response handling, walidacja, routing |
| **Action** | Logika queueing (jobId, cache, confidence, Event dispatch) |
| **Event** | Przenoszenie danych między komponentami |
| **Listener** | Wybór odpowiedniego Job (Mock vs Real) |
| **Job** | Asynchroniczne przetwarzanie generacji AI |

**Przed refaktoryzacją:**
- ❌ Kontroler robił wszystko: HTTP + queueing + cache + Event dispatch

**Po refaktoryzacji:**
- ✅ Kontroler: tylko HTTP request/response
- ✅ Action: tylko queueing logic
- ✅ Event/Listener/Job: tylko asynchroniczne przetwarzanie

---

### **2. DRY (Don't Repeat Yourself)**

**Problem duplikacji:**

Przed refaktoryzacją, każdy kontroler miał identyczną logikę:

```php
// GenerateController
$jobId = Str::uuid();
Cache::put(...);
event(new MovieGenerationRequested(...));
return response()->json([...]);

// MovieController
$jobId = Str::uuid();
Cache::put(...);
event(new MovieGenerationRequested(...));
return response()->json([...]);

// PersonController
$jobId = Str::uuid();
Cache::put(...);
event(new PersonGenerationRequested(...));
return response()->json([...]);
```

**Rozwiązanie:**
- ✅ Jeden `QueueMovieGenerationAction` dla wszystkich kontrolerów
- ✅ Jeden `QueuePersonGenerationAction` dla wszystkich kontrolerów
- ✅ Eliminacja duplikacji kodu

---

### **3. Encapsulation (Enkapsulacja)**

**Action grupuje powiązane operacje:**

1. **Tworzenie jobId** - `Str::uuid()`
2. **Inicjalizacja cache** - `JobStatusService::initializeStatus()`
3. **Dispatch Event** - `event(new MovieGenerationRequested(...))`
4. **Formatowanie odpowiedzi** - confidence level calculation
5. **Logika biznesowa** - message, status, slug

**Kontroler nie musi o tym wiedzieć:**
```php
// Kontroler nie wie o:
// - Jak tworzony jest jobId
// - Jak inicjalizowany jest cache
// - Jak dispatchowany jest Event
// - Jak obliczany jest confidence level

// Kontroler tylko wie:
$result = $action->handle($slug, $confidence);
return response()->json($result, 202);
```

---

### **4. Testability (Łatwość testowania)**

**Testowanie Action osobno:**

```php
// tests/Unit/Actions/QueueMovieGenerationActionTest.php
class QueueMovieGenerationActionTest extends TestCase
{
    public function test_action_creates_job_id_and_dispatches_event(): void
    {
        Event::fake();
        Cache::fake();
        
        $action = new QueueMovieGenerationAction(new JobStatusService());
        $result = $action->handle('the-matrix-1999', 0.95);
        
        // Assertions
        $this->assertArrayHasKey('job_id', $result);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertEquals('high', $result['confidence_level']);
        
        Event::assertDispatched(MovieGenerationRequested::class);
    }
}
```

**Zamiast testowania kontrolera z wieloma zależnościami:**
```php
// Trudniejsze testowanie kontrolera
$controller = new GenerateController(
    $queueMovieGenerationAction,
    $queuePersonGenerationAction
);
// Musisz mockować więcej rzeczy
```

---

### **5. Consistency (Konsystencja)**

**Jednolity sposób queueing w całej aplikacji:**

```php
// Wszystkie kontrolery używają tego samego Action
GenerateController::handleMovieGeneration() 
    → QueueMovieGenerationAction::handle()

MovieController::handleMissingMovie() 
    → QueueMovieGenerationAction::handle()

PersonController::handleMissingPerson() 
    → QueuePersonGenerationAction::handle()
```

**Gwarancja:**
- ✅ Wszystkie kontrolery zachowują się tak samo
- ✅ Jedna implementacja = jeden punkt zmian
- ✅ Łatwe dodawanie nowych funkcji (np. logging, metrics)

---

## 🔄 **Event-Driven Architecture - Nadal Aktywna**

### **Ważne: Action Pattern nie zastępuje Event-Driven Architecture**

**Przepływ danych z Action:**

```
1. Controller → Action::handle()
2. Action → event(new MovieGenerationRequested(...))  ← EVENT!
3. Laravel Event Dispatcher → Listener
4. Listener → Job::dispatch()
5. Queue Worker → Job::handle()
```

**Event-Driven Architecture działa pełnoprawnie:**
- ✅ Events są dispatchowane
- ✅ Listeners są wywoływane
- ✅ Jobs są przetwarzane asynchronicznie
- ✅ Możliwość wielu Listeners dla jednego Event
- ✅ Decoupling między komponentami

---

### **Dlaczego Action dispatchuje Event zamiast Job bezpośrednio?**

**1. Decoupling (Rozdzielenie zależności):**
```php
// Action nie wie o Job
// Action tylko dispatchuje Event
event(new MovieGenerationRequested($slug, $jobId));

// Listener decyduje o Job
match ($aiService) {
    'real' => RealGenerateMovieJob::dispatch(...),
    'mock' => MockGenerateMovieJob::dispatch(...),
};
```

**2. Możliwość wielu Listeners:**
```php
// EventServiceProvider.php
MovieGenerationRequested::class => [
    QueueMovieGenerationJob::class,      // Queue job
    LogMovieGenerationRequest::class,     // Logging
    SendNotificationToAdmin::class,        // Notification
    UpdateMetrics::class,                  // Metrics
],
```

**3. Testowanie:**
```php
// Można łatwo fake Event w testach
Event::fake();
$action->handle($slug, $confidence);
Event::assertDispatched(MovieGenerationRequested::class);
```

---

## 📊 **Porównanie: Action Pattern vs Bezpośredni Event Dispatch**

| Aspekt | Bezpośredni Event Dispatch | Action Pattern |
|--------|----------------------------|----------------|
| **Event-Driven** | ✅ Tak | ✅ Tak (Action dispatchuje Event) |
| **Duplikacja kodu** | ❌ Duplikacja w każdym kontrolerze | ✅ Jeden Action dla wszystkich |
| **Single Responsibility** | ❌ Kontroler robi za dużo | ✅ Kontroler tylko HTTP, Action tylko queueing |
| **Testability** | ⚠️ Trudne (wiele zależności w kontrolerze) | ✅ Łatwe (test Action osobno) |
| **Consistency** | ⚠️ Różne implementacje w kontrolerach | ✅ Jednolity sposób w całej aplikacji |
| **Encapsulation** | ❌ Logika queueing w kontrolerze | ✅ Logika queueing w Action |
| **Maintainability** | ⚠️ Zmiany w wielu miejscach | ✅ Zmiany w jednym miejscu (Action) |
| **Reusability** | ❌ Trudne do reużycia | ✅ Action może być użyty wszędzie |
| **Extensibility** | ⚠️ Trudne dodawanie nowych funkcji | ✅ Łatwe dodawanie (logging, metrics w Action) |

---

## 🎯 **Kiedy używać Action Pattern?**

### **✅ Użyj Action Pattern gdy:**

1. **Logika jest powtarzana w wielu miejscach**
   - Wiele kontrolerów wykonuje tę samą operację
   - Duplikacja kodu między kontrolerami

2. **Logika jest złożona (więcej niż 1-2 linie)**
   - Wymaga wielu kroków (jobId, cache, Event, formatting)
   - Zawiera logikę biznesową (confidence level calculation)

3. **Potrzebujesz enkapsulacji**
   - Kontroler nie powinien wiedzieć o szczegółach implementacji
   - Separacja odpowiedzialności (SOLID)

4. **Chcesz łatwo testować**
   - Testowanie Action osobno jest prostsze
   - Mniej zależności w testach

5. **Potrzebujesz konsystencji**
   - Wszystkie kontrolery powinny robić to samo
   - Jedna implementacja dla wszystkich

---

### **❌ Nie używaj Action Pattern gdy:**

1. **Logika jest bardzo prosta (1-2 linie)**
   ```php
   // Nie potrzebujesz Action dla:
   event(new SimpleEvent($data));
   ```

2. **Logika jest używana tylko w jednym miejscu**
   - Jeśli nie ma duplikacji, Action może być overkill

3. **Logika jest tylko HTTP request/response**
   - To powinno być w kontrolerze, nie w Action

---

## 📝 **Przykłady Użycia**

### **Przykład 1: GenerateController**

```php
// GenerateController.php
private function handleMovieGeneration(string $slug, string $jobId): JsonResponse
{
    // Walidacja
    $validation = SlugValidator::validateMovieSlug($slug);
    if (! $validation['valid']) {
        return response()->json(['error' => 'Invalid slug'], 400);
    }

    // Sprawdzenie czy istnieje
    $existing = Movie::where('slug', $slug)->first();
    if ($existing) {
        return response()->json(['status' => 'DONE', ...], 200);
    }

    // Queue generation (Action Pattern)
    $result = $this->queueMovieGenerationAction->handle(
        $slug, 
        $validation['confidence']
    );

    return response()->json($result, 202);
}
```

**Kontroler jest prosty i czytelny:**
- ✅ Walidacja
- ✅ Sprawdzenie istniejącego
- ✅ Wywołanie Action
- ✅ Zwrócenie odpowiedzi

---

### **Przykład 2: MovieController**

```php
// MovieController.php
private function handleMissingMovie(string $slug): JsonResponse
{
    if (! Feature::active('ai_description_generation')) {
        return response()->json(['error' => 'Movie not found'], 404);
    }

    // Ten sam Action co w GenerateController!
    $result = $this->queueMovieGenerationAction->handle($slug);

    return response()->json($result, 202);
}
```

**Korzyści:**
- ✅ Używa tego samego Action co GenerateController
- ✅ Konsystencja w całej aplikacji
- ✅ Brak duplikacji kodu

---

## 🔧 **Implementacja Techniczna**

### **Struktura Plików:**

```
api/app/
├── Actions/
│   ├── QueueMovieGenerationAction.php    # Action dla movie
│   └── QueuePersonGenerationAction.php  # Action dla person
├── Events/
│   ├── MovieGenerationRequested.php     # Event
│   └── PersonGenerationRequested.php    # Event
├── Listeners/
│   ├── QueueMovieGenerationJob.php      # Listener
│   └── QueuePersonGenerationJob.php     # Listener
└── Jobs/
    ├── MockGenerateMovieJob.php          # Job (mock)
    ├── RealGenerateMovieJob.php          # Job (real)
    ├── MockGeneratePersonJob.php         # Job (mock)
    └── RealGeneratePersonJob.php         # Job (real)
```

---

### **Dependency Injection:**

```php
// GenerateController.php
public function __construct(
    private readonly QueueMovieGenerationAction $queueMovieGenerationAction,
    private readonly QueuePersonGenerationAction $queuePersonGenerationAction
) {}
```

**Korzyści DI:**
- ✅ Łatwe testowanie (mock Action)
- ✅ Loose coupling
- ✅ Laravel automatycznie resolvuje zależności

---

### **Service Container:**

```php
// Action nie wymaga rejestracji w ServiceProvider
// Laravel automatycznie resolvuje przez type hinting

// QueueMovieGenerationAction wymaga JobStatusService
// Laravel automatycznie injectuje JobStatusService
```

---

## 🧪 **Testowanie**

### **Testowanie Action:**

```php
// tests/Unit/Actions/QueueMovieGenerationActionTest.php
class QueueMovieGenerationActionTest extends TestCase
{
    public function test_action_creates_job_and_dispatches_event(): void
    {
        Event::fake();
        Cache::fake();
        
        $jobStatusService = new JobStatusService();
        $action = new QueueMovieGenerationAction($jobStatusService);
        
        $result = $action->handle('the-matrix-1999', 0.95);
        
        // Assertions
        $this->assertArrayHasKey('job_id', $result);
        $this->assertEquals('PENDING', $result['status']);
        $this->assertEquals('high', $result['confidence_level']);
        $this->assertEquals('the-matrix-1999', $result['slug']);
        
        // Verify Event was dispatched
        Event::assertDispatched(MovieGenerationRequested::class, function ($event) {
            return $event->slug === 'the-matrix-1999';
        });
        
        // Verify cache was initialized
        $jobId = $result['job_id'];
        $cached = Cache::get("ai_job:{$jobId}");
        $this->assertNotNull($cached);
        $this->assertEquals('PENDING', $cached['status']);
    }
}
```

---

### **Testowanie Kontrolera:**

```php
// tests/Feature/GenerateApiTest.php
class GenerateApiTest extends TestCase
{
    public function test_generate_movie_queues_generation(): void
    {
        Event::fake();
        
        $response = $this->postJson('/api/v1/generate', [
            'entity_type' => 'MOVIE',
            'slug' => 'the-matrix-1999',
        ]);
        
        $response->assertStatus(202)
            ->assertJsonStructure([
                'job_id',
                'status',
                'slug',
                'confidence',
            ]);
        
        // Verify Event was dispatched
        Event::assertDispatched(MovieGenerationRequested::class);
    }
}
```

**Korzyści:**
- ✅ Test kontrolera nie wymaga mockowania Action
- ✅ Test Action osobno jest prostszy
- ✅ Oba testy są niezależne

---

## 🔄 **Alternatywne Podejścia**

### **Alternatywa 1: Service Class**

**Zamiast Action Pattern, można użyć Service:**

```php
// MovieGenerationService.php
class MovieGenerationService
{
    public function queueGeneration(string $slug, ?float $confidence = null): array
    {
        // Ta sama logika co Action
    }
}
```

**Różnice:**
- Service jest bardziej ogólny (może mieć wiele metod)
- Action jest bardziej specyficzny (jedna metoda `handle()`)

**Decyzja:** Użyto Action Pattern, bo:
- ✅ Bardziej ekspresywny (jasno komunikuje "to jest akcja")
- ✅ Konwencja Laravel (Action Pattern jest popularny)
- ✅ Single Responsibility (jedna akcja = jedna klasa)

---

### **Alternatywa 2: Command Pattern**

```php
// QueueMovieGenerationCommand.php
class QueueMovieGenerationCommand
{
    public function execute(string $slug, ?float $confidence = null): array
    {
        // Ta sama logika
    }
}
```

**Różnice:**
- Command Pattern jest bardziej skomplikowany
- Action Pattern jest prostszy i bardziej czytelny

**Decyzja:** Użyto Action Pattern, bo:
- ✅ Prostszy w implementacji
- ✅ Wystarczający dla potrzeb projektu
- ✅ Łatwiejszy w zrozumieniu

---

### **Alternatywa 3: Bezpośredni Job Dispatch**

```php
// Zamiast Event → Listener → Job
// Bezpośrednio:
MockGenerateMovieJob::dispatch($slug, $jobId);
```

**Dlaczego nie:**
- ❌ Brak decoupling (Action zna Job)
- ❌ Trudniejsze testowanie
- ❌ Brak możliwości wielu Listeners
- ❌ Brak elastyczności (trudno zmienić Job bez zmiany Action)

**Decyzja:** Użyto Event-Driven, bo:
- ✅ Decoupling (Action nie zna Job)
- ✅ Elastyczność (można zmienić Job bez zmiany Action)
- ✅ Możliwość wielu Listeners
- ✅ Łatwiejsze testowanie

---

## 📚 **Best Practices**

### **1. Action powinien być prosty**

**✅ Dobrze:**
```php
public function handle(string $slug, ?float $confidence = null): array
{
    $jobId = Str::uuid();
    $this->jobStatusService->initializeStatus(...);
    event(new MovieGenerationRequested(...));
    return [...];
}
```

**❌ Źle:**
```php
public function handle(string $slug, ?float $confidence = null): array
{
    // Zbyt dużo logiki biznesowej w Action
    $movie = Movie::where('slug', $slug)->first();
    if ($movie) {
        // ... dużo logiki ...
    }
    // ...
}
```

**Zasada:** Action powinien tylko orchestrować (jobId, cache, Event), nie zawierać logiki biznesowej.

---

### **2. Action powinien zwracać array, nie Response**

**✅ Dobrze:**
```php
public function handle(...): array
{
    return [
        'job_id' => $jobId,
        'status' => 'PENDING',
        // ...
    ];
}

// W kontrolerze:
$result = $action->handle(...);
return response()->json($result, 202);
```

**❌ Źle:**
```php
public function handle(...): JsonResponse
{
    // Action nie powinien zwracać Response
    return response()->json([...], 202);
}
```

**Zasada:** Action zwraca dane, kontroler tworzy Response.

---

### **3. Action powinien używać Event-Driven**

**✅ Dobrze:**
```php
public function handle(...): array
{
    event(new MovieGenerationRequested($slug, $jobId));
    // ...
}
```

**❌ Źle:**
```php
public function handle(...): array
{
    // Bezpośredni dispatch Job (brak decoupling)
    MockGenerateMovieJob::dispatch($slug, $jobId);
    // ...
}
```

**Zasada:** Action dispatchuje Event, nie Job bezpośrednio.

---

### **4. Action powinien być testowalny**

**✅ Dobrze:**
```php
// Action nie ma zależności od HTTP
// Można testować bez mockowania Request/Response
$action = new QueueMovieGenerationAction($jobStatusService);
$result = $action->handle($slug, $confidence);
```

**❌ Źle:**
```php
// Action ma zależność od Request
public function handle(Request $request): array
{
    // Trudne do testowania
}
```

**Zasada:** Action powinien być niezależny od HTTP layer.

---

## 🎯 **Podsumowanie**

### **Kluczowe Punkty:**

1. **Action Pattern nie zastępuje Event-Driven Architecture**
   - Action dispatchuje Event
   - Event-Driven Architecture działa pełnoprawnie
   - Action jest tylko warstwą pośrednią

2. **Action Pattern rozwiązuje problemy:**
   - ✅ Eliminuje duplikację kodu
   - ✅ Zwiększa czytelność (kontroler prostszy)
   - ✅ Ułatwia testowanie
   - ✅ Zapewnia konsystencję

3. **Single Responsibility Principle:**
   - Controller: HTTP request/response
   - Action: Queueing logic (jobId, cache, Event dispatch)
   - Event/Listener: Decoupling i routing
   - Job: Asynchroniczne przetwarzanie

4. **Architektura współpracuje:**
   - Action Pattern + Event-Driven Architecture = Powerful combination
   - Każdy wzorzec ma swoje miejsce
   - Współpracują, nie konkurują

---

## 📚 **Dodatkowe Zasoby**

- [Laravel Actions Pattern](https://laravel.com/docs/actions)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Event-Driven Architecture](https://martinfowler.com/articles/201701-event-driven.html)
- [Refactoring Documentation](./REFACTOR_CONTROLLERS_SOLID.md)

---

## 🔗 **Powiązane Dokumenty**

- [`REFACTOR_CONTROLLERS_SOLID.md`](../issue/REFACTOR_CONTROLLERS_SOLID.md) - Pełna dokumentacja refaktoryzacji
- [`SYMFONY_VS_LARAVEL_EVENTS.md`](./SYMFONY_VS_LARAVEL_EVENTS.md) - Porównanie Event systems
- [`LARAVEL_EVENTS_JOBS_EXPLAINED.md`](./LARAVEL_EVENTS_JOBS_EXPLAINED.md) - Wyjaśnienie Events i Jobs

---

**Ostatnia aktualizacja:** 2025-11-04  
**Status:** ✅ Zaimplementowane i udokumentowane

