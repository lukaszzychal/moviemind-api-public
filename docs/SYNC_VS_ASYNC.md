# Synchroniczne vs Asynchroniczne w Laravel - Events, Listeners, Jobs

## 🎯 Quick Answer

| Element | Domyślnie | Może być async? |
|---------|-----------|-----------------|
| **Event** | ✅ Synchroniczne | N/A (to tylko DTO) |
| **Listener** | ✅ Synchroniczne | ✅ Tak (`implements ShouldQueue`) |
| **Job** | ✅ Asynchroniczne | Zawsze async |

---

## 📊 Szczegółowe Wyjaśnienie

### 1. Events - Zawsze Synchroniczne

**Event = tylko informacja (DTO), nie wykonuje kodu.**

```php
// Event to tylko obiekt z danymi
class MovieGenerationRequested {
    public function __construct(public string $slug, public string $jobId) {}
}

// Emisja eventu jest synchroniczna
event(new MovieGenerationRequested($slug, $jobId));
// ↑ Wykonuje się natychmiast, synchronicznie

// Event NIE wykonuje żadnego kodu - tylko komunikuje informację
```

**Flow:**
```
event(new Event())  ← SYNCHRONICZNE (natychmiastowe)
  ↓
Laravel szuka listenerów  ← SYNCHRONICZNE
  ↓
Wykonuje listenery  ← To zależy (patrz poniżej)
```

---

### 2. Listeners - Domyślnie Synchroniczne

**Listener wykonuje się synchronicznie, CHYBA że implementuje `ShouldQueue`.**

#### Przykład 1: Synchroniczny Listener (domyślne)

```php
class QueueMovieGenerationJob
{
    // NIE implementuje ShouldQueue = SYNCHRONICZNY
    public function handle(MovieGenerationRequested $event): void
    {
        // Ten kod wykonuje się SYNCHRONICZNIE
        // Request czeka aż się wykona
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}
```

**Flow:**
```
Request → Controller → event() → Listener::handle() → Done
         (blokuje request)                    ↑
                                      SYNCHRONICZNE
```

**Czas wykonania:** Request trwa ~1ms (tylko dispatch Job).

---

#### Przykład 2: Asynchroniczny Listener (z ShouldQueue)

```php
class QueueMovieGenerationJob implements ShouldQueue
{
    // ✅ Implementuje ShouldQueue = ASYNCHRONICZNY
    
    public function handle(MovieGenerationRequested $event): void
    {
        // Ten kod wykonuje się ASYNCHRONICZNIE
        // Request NIE czeka - zwraca natychmiast
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}
```

**Flow:**
```
Request → Controller → event() → Listener → Queue → Done (request zwraca)
         (request zwraca szybko)          ↑
                                    ASYNCHRONICZNE
                                    (wykona się w tle)
```

**Czas wykonania:** Request trwa ~5ms (tylko zapis do queue), Listener wykonuje się później w tle.

---

### 3. Jobs - Zawsze Asynchroniczne

**Job zawsze wykonuje się asynchronicznie (implementuje `ShouldQueue`).**

```php
class GenerateMovieJob implements ShouldQueue
{
    public function handle(): void
    {
        // Ten kod ZAWSZE wykonuje się ASYNCHRONICZNIE
        // Request NIE czeka
        sleep(3);  // Długa operacja
        Movie::create([...]);
    }
}
```

**Flow:**
```
Request → Controller → Job::dispatch() → Queue → Done (request zwraca)
         (request zwraca szybko)       ↑
                                  ASYNCHRONICZNE
                                  (wykona się później przez worker)
```

**Czas wykonania:** Request trwa ~5ms, Job wykonuje się później (sekundy/minuty).

---

## 🔄 Przykłady z Twojej Aplikacji

### Obecna Implementacja (Events + Jobs)

```php
// 1. Controller - SYNCHRONICZNE
public function generate() {
    $jobId = Str::uuid();
    
    // Event - SYNCHRONICZNE (tylko emisja)
    event(new MovieGenerationRequested($slug, $jobId));
    
    return response()->json(['job_id' => $jobId], 202);
    // ↑ Request zwraca natychmiast (202 Accepted)
}

// 2. Listener - SYNCHRONICZNE (domyślnie)
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        // SYNCHRONICZNE - request czeka aż to się wykona
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
        // ↑ Dispatch jest szybki (~1ms) - tylko zapis do queue
    }
}

// 3. Job - ASYNCHRONICZNE (zawsze)
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void {
        // ASYNCHRONICZNE - request NIE czeka
        sleep(3);  // Długa operacja AI
        Movie::create([...]);
    }
}
```

**Timeline:**
```
0ms    Request przychodzi
1ms    Controller wykonuje się
2ms    event() wywołane (synchronicznie)
3ms    Listener::handle() wykonuje się (synchronicznie, szybko)
4ms    Job::dispatch() zapisuje do queue (synchronicznie, szybko)
5ms    Response zwrócony (202 Accepted) ← REQUEST KONIECZNY
       ↓
       Worker wykonuje Job w tle (później, async)
```

---

## 📊 Tabela Porównawcza

| Krok | Element | Synchroniczne? | Czas | Blokuje Request? |
|------|---------|-----------------|------|-------------------|
| 1 | `event(new Event())` | ✅ Tak | ~1ms | ❌ Nie (szybko) |
| 2 | `Listener::handle()` | ✅ Tak (domyślnie) | ~1ms | ❌ Nie (szybko) |
| 3 | `Job::dispatch()` | ✅ Tak (zapis) | ~1ms | ❌ Nie (szybko) |
| 4 | `Job::handle()` | ❌ Nie (async) | Sekundy/minuty | ❌ Nie (w tle) |

**UWAGA:** Nawet jeśli Listener jest synchroniczny, wykonuje się szybko (tylko dispatch), więc request nie jest blokowany długo.

---

## 🔍 Kiedy Co Używać?

### Synchroniczny Listener - Kiedy?

**Używaj gdy:**
- ✅ Listener robi szybką operację (<100ms)
- ✅ Chcesz mieć gwarancję że się wykona przed zwróceniem response
- ✅ Logowanie, cache update, simple validation

**Przykład:**
```php
class LogGenerationRequest implements ShouldQueue  // ❌ NIE potrzebne
{
    public function handle(MovieGenerationRequested $event): void
    {
        // Szybka operacja - zostaw synchroniczne
        Log::info('Movie generation requested', ['slug' => $event->slug]);
    }
}
```

---

### Asynchroniczny Listener - Kiedy?

**Używaj gdy:**
- ✅ Listener robi długą operację (>100ms)
- ✅ Nie potrzebujesz gwarancji wykonania przed response
- ✅ Email sending, external API calls, heavy processing

**Przykład:**
```php
class SendNotificationOnGeneration implements ShouldQueue  // ✅ ASYNC
{
    public function handle(MovieGenerationRequested $event): void
    {
        // Długa operacja - zrób async
        Mail::to($admin)->send(new GenerationNotification($event));
    }
}
```

---

## 🎯 Twoja Aplikacja - Analiza

### Obecny Flow:

```php
// Controller - SYNCHRONICZNE (szybko)
public function generate() {
    event(new MovieGenerationRequested($slug, $jobId));
    return response()->json([...], 202);  // ← Request zwraca szybko
}

// Listener - SYNCHRONICZNE (szybko, tylko dispatch)
class QueueMovieGenerationJob {
    public function handle(...) {
        GenerateMovieJob::dispatch(...);  // ← Tylko dispatch, szybko
    }
}

// Job - ASYNCHRONICZNE (długo, w tle)
class GenerateMovieJob implements ShouldQueue {
    public function handle() {
        sleep(3);  // ← Długa operacja AI
        Movie::create([...]);
    }
}
```

**To jest DOBRZE zaprojektowane! ✅**

**Dlaczego:**
- Controller zwraca szybko (202 Accepted)
- Listener tylko dispatchuje Job (szybko)
- Długa operacja (AI generation) w Job (async, w tle)

---

## 🔄 Co Się Dzieje w Request/Response Cycle?

### Timeline:

```
┌─────────────────────────────────────────────────────┐
│ HTTP Request przychodzi                              │
└─────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────┐
│ Controller::generate()                              │
│ - Sprawdza feature flag                              │
│ - Generuje jobId                                     │
│ - event(new MovieGenerationRequested(...))  ← SYNC  │
└─────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────┐
│ EventServiceProvider znajduje Listener               │
│ QueueMovieGenerationJob::handle()          ← SYNC   │
│ - GenerateMovieJob::dispatch(...)                   │
│   (zapisuje Job do queue - szybko)                  │
└─────────────────────────────────────────────────────┘
           ↓
┌─────────────────────────────────────────────────────┐
│ Response zwrócony (202 Accepted)                    │
│ Request KONIECZNY ✅                                 │
└─────────────────────────────────────────────────────┘
           ↓
           ↓ (w tle, ASYNC)
┌─────────────────────────────────────────────────────┐
│ Queue Worker odbiera Job                             │
│ GenerateMovieJob::handle()                ← ASYNC   │
│ - sleep(3)                                           │
│ - Movie::create([...])                                │
│ - Cache::put(...)                                     │
└─────────────────────────────────────────────────────┘
```

**Kluczowe punkty:**
1. Request zwraca szybko (202 Accepted) - nie czeka na Job
2. Listener wykonuje się synchronicznie, ale szybko (tylko dispatch)
3. Job wykonuje się asynchronicznie w tle (długo)

---

## ⚠️ Ważne Uwagi

### 1. Event + Synchroniczny Listener = Blokuje Request

```php
// ❌ ŹLE - jeśli Listener robi długą operację synchronicznie
class SlowListener {
    public function handle(MovieGenerationRequested $event): void {
        sleep(5);  // ← Request czeka 5 sekund! ❌
    }
}
```

**Rozwiązanie:**
```php
// ✅ DOBRZE - Listener async lub dispatch Job
class SlowListener implements ShouldQueue {
    public function handle(MovieGenerationRequested $event): void {
        sleep(5);  // ← Wykonuje się w tle, request nie czeka ✅
    }
}

// LUB

class SlowListener {
    public function handle(MovieGenerationRequested $event): void {
        SlowOperationJob::dispatch($event);  // ← Job wykonuje się async ✅
    }
}
```

---

### 2. Job ZAWSZE Async (nawet jeśli dispatch synchroniczny)

```php
// Dispatch jest synchroniczny (zapis do queue)
GenerateMovieJob::dispatch($slug, $jobId);  // ← SYNC (szybko)
// ↑ Zapisuje Job do queue

// Ale wykonanie jest asynchroniczne
GenerateMovieJob::handle();  // ← ASYNC (wykonuje worker później)
// ↑ Wykonuje się w tle przez queue worker
```

---

### 3. Kiedy Listener Powinien Być Async?

**Szybki Listener (domyślnie synchroniczny):**
```php
class LogGenerationRequest {
    public function handle(MovieGenerationRequested $event): void {
        Log::info('Generation requested', ['slug' => $event->slug]);
        // ↑ Szybko, zostaw synchroniczny
    }
}
```

**Wolny Listener (zrób async):**
```php
class SendEmailOnGeneration implements ShouldQueue {
    public function handle(MovieGenerationRequested $event): void {
        Mail::to($admin)->send(new Notification($event));
        // ↑ Może trwać długo, zrób async
    }
}
```

---

## 📊 Porównanie: Synchroniczne vs Asynchroniczne

### Synchroniczne (domyślne):

**Kiedy:**
- ✅ Szybka operacja (<100ms)
- ✅ Potrzebujesz gwarancji wykonania
- ✅ Logowanie, cache, validation

**Flow:**
```
Request → Controller → Event → Listener → Done
         (blokuje do czasu wykonania listenera)
```

---

### Asynchroniczne (ShouldQueue):

**Kiedy:**
- ✅ Długa operacja (>100ms)
- ✅ Nie potrzebujesz gwarancji wykonania przed response
- ✅ Email, API calls, processing

**Flow:**
```
Request → Controller → Event → Listener → Queue → Done (response)
         (zwraca szybko)                    ↓
                                    Worker wykonuje później
```

---

## 🎯 Podsumowanie

### Events:
- ✅ **Zawsze synchroniczne** (ale szybko, tylko emisja)

### Listeners:
- ✅ **Domyślnie synchroniczne**
- ✅ **Może być async** (`implements ShouldQueue`)
- ✅ Używaj async gdy operacja długa (>100ms)

### Jobs:
- ✅ **Zawsze asynchroniczne**
- ✅ Wykonują się przez queue worker w tle

### Twoja Aplikacja:
- ✅ **Dobrze zaprojektowane!**
- ✅ Controller zwraca szybko (202)
- ✅ Listener szybki (tylko dispatch)
- ✅ Długa operacja w Job (async)

---

## 💡 Best Practice

**Event → Listener → Job Pattern:**

```php
// 1. Controller (synchroniczny, szybko)
event(new MovieGenerationRequested($slug, $jobId));

// 2. Listener (synchroniczny, szybko - tylko dispatch)
class QueueMovieGenerationJob {
    public function handle(...) {
        GenerateMovieJob::dispatch(...);  // Tylko dispatch
    }
}

// 3. Job (asynchroniczny, długo - praca w tle)
class GenerateMovieJob implements ShouldQueue {
    public function handle() {
        // Długa operacja
    }
}
```

**Wynik:** Request zwraca szybko (202), długa praca wykonuje się w tle. ✅

