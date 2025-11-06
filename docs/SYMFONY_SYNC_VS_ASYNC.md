# Symfony: Synchroniczne vs Asynchroniczne - Events i Messenger

## 🎯 Quick Answer

| Element | Domyślnie | Może być async? |
|---------|-----------|-----------------|
| **Event** | ✅ Synchroniczne | N/A (to tylko obiekt) |
| **Event Listener** | ✅ Synchroniczne | ✅ Tak (przez Messenger) |
| **Message (Job)** | ✅ Asynchroniczne | Zawsze async |

---

## 📊 Szczegółowe Wyjaśnienie

### 1. Events - Zawsze Synchroniczne

**Event = tylko obiekt z danymi, nie wykonuje kodu.**

```php
// Event to tylko obiekt
class MovieGenerationRequested extends Event
{
    private string $slug;
    private string $jobId;
    
    public function __construct(string $slug, string $jobId)
    {
        $this->slug = $slug;
        $this->jobId = $jobId;
    }
}

// Emisja eventu jest synchroniczna
$dispatcher->dispatch(new MovieGenerationRequested($slug, $jobId), 'movie.generation.requested');
// ↑ Wykonuje się natychmiast, synchronicznie
```

**Flow:**
```
$dispatcher->dispatch($event, $name)  ← SYNCHRONICZNE (natychmiastowe)
  ↓
Symfony szuka listenerów  ← SYNCHRONICZNE
  ↓
Wykonuje listenery  ← To zależy (patrz poniżej)
```

---

### 2. Event Listeners - Domyślnie Synchroniczne

**Listener wykonuje się synchronicznie, CHYBA że dispatchuje Message do Messenger.**

#### Przykład 1: Synchroniczny Listener (domyślne)

```php
// services.yaml
services:
    App\Listeners\QueueMovieGenerationListener:
        tags:
            - { name: kernel.event_listener, event: movie.generation.requested }

// Listener
class QueueMovieGenerationListener
{
    public function __construct(private MessageBusInterface $messageBus) {}
    
    public function __invoke(MovieGenerationRequested $event): void
    {
        // Ten kod wykonuje się SYNCHRONICZNIE
        // Request czeka aż się wykona
        $this->messageBus->dispatch(new GenerateMovieMessage($event->getSlug(), $event->getJobId()));
        // ↑ Dispatch do Messenger jest szybki (~1ms) - tylko zapis do queue
    }
}
```

**Flow:**
```
Request → Controller → $dispatcher->dispatch() → Listener::__invoke() → Done
         (blokuje request)                    ↑
                                      SYNCHRONICZNE
```

**Czas wykonania:** Request trwa ~1-2ms (tylko dispatch Message).

---

#### Przykład 2: Listener Dispatchuje Message (async)

```php
class QueueMovieGenerationListener
{
    public function __construct(private MessageBusInterface $messageBus) {}
    
    public function __invoke(MovieGenerationRequested $event): void
    {
        // Dispatch Message - Listener wykonuje się SYNCHRONICZNIE
        // Ale Message trafia do queue i wykonuje się ASYNCHRONICZNIE
        $this->messageBus->dispatch(new GenerateMovieMessage($event->getSlug(), $event->getJobId()));
        // ↑ Listener: SYNC (szybko)
        // ↑ Message: ASYNC (wykona się później)
    }
}
```

**Flow:**
```
Request → Controller → dispatch() → Listener → messageBus->dispatch() → Done
         (request zwraca szybko)           ↑                     ↑
                                      SYNC (szybko)        ASYNC (w tle)
```

**Czas wykonania:** Request trwa ~2ms, Message wykonuje się później w tle.

---

### 3. Messenger Messages (Jobs) - Zawsze Asynchroniczne

**Message zawsze wykonuje się asynchronicznie (przez Messenger transport).**

```php
// Message (DTO)
class GenerateMovieMessage
{
    public function __construct(
        private string $slug,
        private string $jobId
    ) {}
    
    // Getters...
}

// Handler (wykonuje pracę)
class GenerateMovieHandler implements MessageHandlerInterface
{
    public function __invoke(GenerateMovieMessage $message): void
    {
        // Ten kod ZAWSZE wykonuje się ASYNCHRONICZNIE
        // Request NIE czeka
        sleep(3);  // Długa operacja
        // ... create Movie ...
    }
}

// Konfiguracja (messenger.yaml)
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'App\Messages\GenerateMovieMessage': async

// Dispatch
$messageBus->dispatch(new GenerateMovieMessage($slug, $jobId));
```

**Flow:**
```
Request → Controller → messageBus->dispatch() → Queue → Done (request zwraca)
         (request zwraca szybko)           ↑
                                  ASYNCHRONICZNE
                                  (wykona się później przez consumer)
```

**Czas wykonania:** Request trwa ~2ms, Handler wykonuje się później (sekundy/minuty).

---

## 🔄 Przykład Pełnego Flow w Symfony

### Implementacja (Event → Listener → Message → Handler)

```php
// 1. Controller - SYNCHRONICZNE
public function generate(Request $request): JsonResponse
{
    $jobId = Uuid::v4()->toString();
    
    // Event - SYNCHRONICZNE (tylko emisja)
    $this->eventDispatcher->dispatch(
        new MovieGenerationRequested($request->get('slug'), $jobId),
        'movie.generation.requested'
    );
    
    return new JsonResponse(['job_id' => $jobId], 202);
    // ↑ Request zwraca natychmiast (202 Accepted)
}

// 2. Listener - SYNCHRONICZNE (domyślnie)
// services.yaml
services:
    App\Listeners\QueueMovieGenerationListener:
        tags:
            - { name: kernel.event_listener, event: movie.generation.requested }

class QueueMovieGenerationListener
{
    public function __construct(private MessageBusInterface $messageBus) {}
    
    public function __invoke(MovieGenerationRequested $event): void
    {
        // SYNCHRONICZNE - request czeka aż to się wykona
        // Ale dispatch jest szybki (~1ms) - tylko zapis do queue
        $this->messageBus->dispatch(
            new GenerateMovieMessage($event->getSlug(), $event->getJobId())
        );
    }
}

// 3. Message - ASYNCHRONICZNE (zawsze)
class GenerateMovieMessage
{
    public function __construct(
        private string $slug,
        private string $jobId
    ) {}
}

// 4. Handler - ASYNCHRONICZNE (zawsze)
class GenerateMovieHandler implements MessageHandlerInterface
{
    public function __invoke(GenerateMovieMessage $message): void
    {
        // ASYNCHRONICZNE - request NIE czeka
        sleep(3);  // Długa operacja AI
        // ... create Movie ...
    }
}

// messenger.yaml
framework:
    messenger:
        transports:
            async: 'doctrine://default'
        routing:
            'App\Messages\GenerateMovieMessage': async
```

**Timeline:**
```
0ms    Request przychodzi
1ms    Controller wykonuje się
2ms    $dispatcher->dispatch() wywołane (synchronicznie)
3ms    Listener::__invoke() wykonuje się (synchronicznie, szybko)
4ms    messageBus->dispatch() zapisuje Message do queue (synchronicznie, szybko)
5ms    Response zwrócony (202 Accepted) ← REQUEST KONIECZNY
       ↓
       ↓ (w tle, ASYNC - później)
       ↓
Consumer → GenerateMovieHandler::__invoke()  ← ASYNC (długo, w tle)
          - sleep(3)
          - Movie::create(...)
```

---

## 📊 Tabela Porównawcza

| Krok | Element | Synchroniczne? | Czas | Blokuje Request? |
|------|---------|----------------|------|-------------------|
| 1 | `$dispatcher->dispatch()` | ✅ Tak | ~1ms | ❌ Nie (szybko) |
| 2 | `Listener::__invoke()` | ✅ Tak (domyślnie) | ~1ms | ❌ Nie (szybko) |
| 3 | `messageBus->dispatch()` | ✅ Tak (zapis) | ~1ms | ❌ Nie (szybko) |
| 4 | `Handler::__invoke()` | ❌ Nie (async) | Sekundy/minuty | ❌ Nie (w tle) |

**UWAGA:** Nawet jeśli Listener jest synchroniczny, wykonuje się szybko (tylko dispatch Message), więc request nie jest blokowany długo.

---

## 🔍 Różnice Kluczowe: Laravel vs Symfony

### Laravel

```php
// Event (synchroniczne, szybko)
event(new MovieGenerationRequested($slug, $jobId));

// Listener (synchroniczne, szybko)
class Listener {
    public function handle(...) {
        Job::dispatch(...);  // Dispatch Job
    }
}

// Job (asynchroniczne, zawsze)
class Job implements ShouldQueue {
    public function handle() { /* work */ }
}
```

---

### Symfony

```php
// Event (synchroniczne, szybko)
$dispatcher->dispatch(new MovieGenerationRequested(...), 'event.name');

// Listener (synchroniczne, szybko)
class Listener {
    public function __invoke(...) {
        $this->messageBus->dispatch(new Message(...));  // Dispatch Message
    }
}

// Handler (asynchroniczne, zawsze)
class Handler implements MessageHandlerInterface {
    public function __invoke(Message $message) { /* work */ }
}
```

**Podobieństwa:**
- ✅ Event zawsze synchroniczne
- ✅ Listener domyślnie synchroniczne
- ✅ Job/Handler zawsze asynchroniczne

---

## 🎯 Kiedy Co Używać w Symfony?

### Synchroniczny Listener - Kiedy?

**Używaj gdy:**
- ✅ Listener robi szybką operację (<100ms)
- ✅ Chcesz mieć gwarancję że się wykona przed zwróceniem response
- ✅ Logowanie, cache update, simple validation

**Przykład:**
```php
class LogGenerationRequestListener
{
    public function __invoke(MovieGenerationRequested $event): void
    {
        // Szybka operacja - zostaw synchroniczne
        $this->logger->info('Movie generation requested', [
            'slug' => $event->getSlug()
        ]);
    }
}
```

---

### Asynchroniczny Listener (przez Message) - Kiedy?

**Używaj gdy:**
- ✅ Listener dispatchuje długą operację do Message Handler
- ✅ Email sending, external API calls, heavy processing

**Przykład:**
```php
class QueueMovieGenerationListener
{
    public function __construct(private MessageBusInterface $messageBus) {}
    
    public function __invoke(MovieGenerationRequested $event): void
    {
        // Dispatch Message - Listener synchroniczny, ale Message async
        $this->messageBus->dispatch(new GenerateMovieMessage($event->getSlug(), $event->getJobId()));
    }
}

// Handler wykonuje się async
class GenerateMovieHandler implements MessageHandlerInterface
{
    public function __invoke(GenerateMovieMessage $message): void
    {
        // Długa operacja - wykonuje się w tle
        sleep(3);
        // ... create Movie ...
    }
}
```

---

## ⚠️ Ważne Uwagi

### 1. Event Listener - Zawsze Synchroniczny

**W Symfony Event Listener ZAWSZE wykonuje się synchronicznie.** Nie ma opcji `implements ShouldQueue` jak w Laravel.

**Rozwiązanie:** Jeśli chcesz async listener, dispatch Message w listenerze:

```php
// ❌ NIE możesz zrobić listener async bezpośrednio
// Symfony nie ma "Queued Listener" jak Laravel

// ✅ DOBRZE - Listener dispatchuje Message (który jest async)
class Listener {
    public function __invoke(Event $event): void {
        // Listener: SYNC
        $this->messageBus->dispatch(new Message(...));
        // ↑ Message: ASYNC
    }
}
```

---

### 2. Message ZAWSZE Async (gdy routing skonfigurowany)

```php
// Dispatch Message
$messageBus->dispatch(new GenerateMovieMessage($slug, $jobId));
// ↑ Dispatch jest synchroniczny (zapis do queue)

// Ale Handler wykonuje się asynchronicznie
class GenerateMovieHandler implements MessageHandlerInterface {
    public function __invoke(GenerateMovieMessage $message): void {
        // Wykonuje się w tle przez consumer
    }
}

// messenger.yaml
framework:
    messenger:
        transports:
            async: 'doctrine://default'
        routing:
            'App\Messages\GenerateMovieMessage': async  // ← ASYNC
```

---

### 3. Message Handler ZAWSZE Async (gdy transport async)

```php
// messenger.yaml
framework:
    messenger:
        transports:
            async: 'doctrine://default'  # Database/Redis/RabbitMQ
            sync: 'sync://'               # Synchroniczny (do testów)

        routing:
            'App\Messages\GenerateMovieMessage': async  # ← Async transport
            'App\Messages\QuickMessage': sync           # ← Sync transport (dla szybkich operacji)
```

**Async Handler:**
```php
// Routing: async → Handler wykonuje się w tle
class GenerateMovieHandler implements MessageHandlerInterface {
    public function __invoke(GenerateMovieMessage $message): void {
        // ASYNC - wykonuje się przez consumer w tle
    }
}
```

**Sync Handler (dla szybkich operacji):**
```php
// Routing: sync → Handler wykonuje się synchronicznie (w request)
class QuickHandler implements MessageHandlerInterface {
    public function __invoke(QuickMessage $message): void {
        // SYNC - wykonuje się podczas requestu
        // Użyj tylko dla bardzo szybkich operacji!
    }
}
```

---

## 🔄 Pełny Flow: Event → Listener → Message → Handler

### Przykład:

```php
// 1. Controller (SYNC, szybko)
public function generate(): JsonResponse
{
    $this->eventDispatcher->dispatch(
        new MovieGenerationRequested($slug, $jobId),
        'movie.generation.requested'
    );
    return new JsonResponse([...], 202);
}

// 2. Event (SYNC, tylko emisja)
class MovieGenerationRequested extends Event { ... }

// 3. Listener (SYNC, szybko)
class QueueMovieGenerationListener {
    public function __invoke(MovieGenerationRequested $event): void {
        // SYNC - ale szybko, tylko dispatch
        $this->messageBus->dispatch(new GenerateMovieMessage(...));
    }
}

// 4. Message (ASYNC, zawsze)
class GenerateMovieMessage { ... }

// 5. Handler (ASYNC, zawsze)
class GenerateMovieHandler implements MessageHandlerInterface {
    public function __invoke(GenerateMovieMessage $message): void {
        // ASYNC - długa operacja w tle
        sleep(3);
        // ... work ...
    }
}
```

**Timeline:**
```
0ms    Request
1ms    Controller → dispatch() → Listener → messageBus->dispatch()
2ms    Response (202) ← REQUEST KONIECZNY
       ↓
       ↓ ASYNC (w tle)
       ↓
Consumer → Handler::__invoke() → Done
```

---

## 📊 Porównanie: Laravel vs Symfony

| Aspekt | Laravel | Symfony |
|--------|---------|---------|
| **Event** | SYNC (szybko) | SYNC (szybko) |
| **Listener** | SYNC (domyślnie) | SYNC (zawsze) |
| **Listener Async** | ✅ `implements ShouldQueue` | ❌ Nie (dispatch Message) |
| **Job/Message** | ASYNC (zawsze) | ASYNC (zawsze, gdy routing async) |
| **Consumer/Worker** | `php artisan queue:work` | `php bin/console messenger:consume async` |

---

## 💡 Best Practice w Symfony

### Pattern: Event → Listener → Message → Handler

```php
// 1. Controller (SYNC, szybko)
$dispatcher->dispatch(new Event(), 'event.name');

// 2. Listener (SYNC, szybko - tylko dispatch)
class Listener {
    public function __invoke(Event $event): void {
        $this->messageBus->dispatch(new Message(...));
    }
}

// 3. Message Handler (ASYNC, długo)
class Handler implements MessageHandlerInterface {
    public function __invoke(Message $message): void {
        // Długa operacja
    }
}
```

**Wynik:** Request zwraca szybko (202), długa praca wykonuje się w tle. ✅

---

## 🎯 Podsumowanie

### Events:
- ✅ **Zawsze synchroniczne** (ale szybko, tylko emisja)

### Event Listeners:
- ✅ **Zawsze synchroniczne** (nie ma opcji async listener w Symfony)
- ✅ **Rozwiązanie:** Dispatch Message w listenerze (Message jest async)

### Message Handlers:
- ✅ **Zawsze asynchroniczne** (gdy routing: async)
- ✅ **Może być synchroniczne** (gdy routing: sync, dla szybkich operacji)

### Twoja Aplikacja (gdyby była w Symfony):
- ✅ Controller zwraca szybko (202)
- ✅ Listener szybki (tylko dispatch Message)
- ✅ Długa operacja w Handler (async)

---

## 📚 Dokumentacja

- **Symfony Events:** https://symfony.com/doc/current/components/event_dispatcher.html
- **Symfony Messenger:** https://symfony.com/doc/current/messenger.html
- **Consumer Command:** `php bin/console messenger:consume async`

