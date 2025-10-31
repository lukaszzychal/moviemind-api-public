# Symfony vs Laravel: Events i Jobs - Szczegółowe Porównanie

## 🎯 Quick Summary

| Koncept | Laravel | Symfony |
|---------|---------|---------|
| **Events** | `event(new Event())` | `$dispatcher->dispatch($event, $name)` |
| **Jobs/Queue** | Wbudowane (`ShouldQueue`) | Messenger component |
| **API Complexity** | Prostsze | Bardziej elastyczne |
| **Stop Propagation** | ❌ Brak | ✅ `stopPropagation()` |

---

## 📘 1. Events - Podstawy

### Laravel Events

**Filosofia:** Event = "coś się stało" (DTO)

```php
// 1. Event (prosty DTO)
class MovieGenerationRequested
{
    use Dispatchable, SerializesModels;
    
    public function __construct(
        public string $slug,
        public string $jobId
    ) {}
}

// 2. Listener
class QueueMovieGenerationJob
{
    public function handle(MovieGenerationRequested $event): void
    {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// 3. Rejestracja
// app/Providers/EventServiceProvider.php
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,
    ],
];

// 4. Emisja
event(new MovieGenerationRequested($slug, $jobId));
```

**Charakterystyka:**
- ✅ Proste API
- ✅ Nazwa Event = nazwa klasy
- ✅ Auto-discovery możliwe
- ❌ Brak stopPropagation
- ❌ Brak priorities (tylko kolejność)

---

### Symfony EventDispatcher

**Filosofia:** Event = obiekt z możliwością kontroli propagacji

```php
// 1. Event (może dziedziczyć po Event)
class MovieGenerationRequested extends Event
{
    private string $slug;
    private string $jobId;
    private bool $propagationStopped = false;
    
    public function __construct(string $slug, string $jobId)
    {
        $this->slug = $slug;
        $this->jobId = $jobId;
    }
    
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }
    
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}

// 2. Listener (callable lub service)
class QueueMovieGenerationJobListener
{
    public function __invoke(MovieGenerationRequested $event): void
    {
        // Jeśli propagation stopped - nie wykonuje
        if ($event->isPropagationStopped()) {
            return;
        }
        
        GenerateMovieJob::dispatch($event->getSlug(), $event->getJobId());
    }
}

// 3. Rejestracja (services.yaml)
services:
    App\Listeners\QueueMovieGenerationJobListener:
        tags:
            - { name: kernel.event_listener, 
                event: movie.generation.requested,
                priority: 10 }

// Lub EventSubscriber
class QueueMovieGenerationJobSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'movie.generation.requested' => ['onMovieGeneration', 10],
        ];
    }
}

// 4. Emisja
$dispatcher = $container->get('event_dispatcher');
$dispatcher->dispatch(
    new MovieGenerationRequested($slug, $jobId),
    'movie.generation.requested'  // ← Nazwa jako string
);
```

**Charakterystyka:**
- ✅ Bardziej elastyczne (event names, priorities)
- ✅ Stop propagation
- ✅ Event name jako string (nie klasa)
- ⚠️ Więcej konfiguracji

---

## 📊 2. Jobs/Queue - Porównanie

### Laravel Jobs

```php
// Job
class GenerateMovieJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $timeout = 90;
    
    public function __construct(public string $slug, public string $jobId) {}
    
    public function handle(): void
    {
        // Work
    }
    
    public function failed(\Throwable $exception): void
    {
        // Failed handling
    }
}

// Dispatch
GenerateMovieJob::dispatch($slug, $jobId);
```

**Charakterystyka:**
- ✅ Wbudowane w framework
- ✅ Proste API
- ✅ Horizon dla monitorowania
- ✅ Retry/timeout jako properties

---

### Symfony Messenger

```php
// Message
class GenerateMovieMessage
{
    public function __construct(
        private string $slug,
        private string $jobId
    ) {}
    
    // Getters
}

// Handler
class GenerateMovieHandler implements MessageHandlerInterface
{
    public function __invoke(GenerateMovieMessage $message): void
    {
        // Work
    }
}

// Dispatch
$bus->dispatch(new GenerateMovieMessage($slug, $jobId));

// Konfiguracja (messenger.yaml)
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'App\Messages\GenerateMovieMessage': async
```

**Charakterystyka:**
- ✅ Osobny component (messenger)
- ✅ Bardziej elastyczne (routing, transport)
- ✅ Retry strategy w konfiguracji
- ⚠️ Więcej konfiguracji

---

## 🔍 3. Kluczowe Różnice

### Event Name

**Laravel:**
```php
// Event name = nazwa klasy
event(new MovieGenerationRequested($slug, $jobId));
//            ↑
//        To jest nazwa eventu
```

**Symfony:**
```php
// Event name = string parameter
$dispatcher->dispatch($event, 'movie.generation.requested');
//                                ↑
//                        To jest nazwa eventu
```

---

### Priority

**Laravel:**
```php
// Kolejność w array = priority
protected $listen = [
    MovieGenerationRequested::class => [
        QueueMovieGenerationJob::class,        // 1. (wykona się pierwszy)
        SendNotificationOnGeneration::class,    // 2.
        LogGenerationRequest::class,            // 3.
    ],
];
```

**Symfony:**
```php
// Priority jako parametr
tags:
    - { name: kernel.event_listener, event: movie.generation.requested, priority: 10 }
    - { name: kernel.event_listener, event: movie.generation.requested, priority: 5 }  // Niższy = później
```

---

### Stop Propagation

**Laravel:**
```php
// ❌ Brak - wszystkie listenery się wykonają
protected $listen = [
    MovieGenerationRequested::class => [
        Listener1::class,  // Wykona się
        Listener2::class,  // Wykona się (nie można zatrzymać)
        Listener3::class,  // Wykona się
    ],
];
```

**Symfony:**
```php
// ✅ Można zatrzymać propagację
class Listener1 {
    public function __invoke(MovieGenerationRequested $event): void {
        // Coś zrobi
        $event->stopPropagation();  // ← Zatrzymuje dalsze listenery
    }
}

class Listener2 {
    public function __invoke(MovieGenerationRequested $event): void {
        // NIE wykona się jeśli Listener1 wywołał stopPropagation
    }
}
```

---

## 🎓 Kiedy Czego Używać?

### Events - Kiedy?

**Laravel Events:**
- ✅ Chcesz poinformować że coś się stało
- ✅ Potrzebujesz multiple actions dla jednego wydarzenia
- ✅ Chcesz loose coupling

**Symfony Events:**
- ✅ To samo + potrzebujesz stopPropagation
- ✅ Potrzebujesz zaawansowane priorities
- ✅ Potrzebujesz event names jako strings

---

### Jobs - Kiedy?

**Laravel Jobs:**
- ✅ Długie operacje (AI, processing)
- ✅ Potrzebujesz async execution
- ✅ Retry/timeout out-of-the-box

**Symfony Messenger:**
- ✅ To samo + potrzebujesz routing/transport flexibility
- ✅ Multiple transports (sync, async, amqp, redis)

---

## 💡 Best Practices

### Laravel:

1. **Event → Listener → Job pattern:**
```php
// Event (informacja)
event(new MovieGenerationRequested($slug, $jobId));

// Listener (decyzja)
class QueueMovieGenerationJob {
    public function handle(MovieGenerationRequested $event): void {
        GenerateMovieJob::dispatch($event->slug, $event->jobId);
    }
}

// Job (wykonanie)
class GenerateMovieJob implements ShouldQueue {
    public function handle(): void { /* work */ }
}
```

2. **Użyj Jobs dla długich operacji:**
```php
// ✅ Długie - Job
GenerateMovieJob::dispatch($slug, $jobId);

// ✅ Krótkie - Event + Listener (sync)
event(new UserRegistered($user));
```

---

### Symfony:

1. **Event + Messenger pattern:**
```php
// Event
$dispatcher->dispatch(new MovieGenerationRequested($slug, $jobId), 'movie.generation.requested');

// Listener
class QueueMovieGenerationListener {
    public function __invoke(MovieGenerationRequested $event): void {
        $this->messageBus->dispatch(new GenerateMovieMessage($event->getSlug(), $event->getJobId()));
    }
}

// Message Handler
class GenerateMovieHandler implements MessageHandlerInterface {
    public function __invoke(GenerateMovieMessage $message): void {
        // Work
    }
}
```

2. **Stop propagation gdy potrzebne:**
```php
class ValidateAndStopListener {
    public function __invoke(MovieGenerationRequested $event): void {
        if (!$this->isValid($event)) {
            $event->stopPropagation(); // Zatrzymaj dalsze listenery
        }
    }
}
```

---

## 🎯 Podsumowanie

### Laravel Events/Jobs:

**Zalety:**
- ✅ Prostsze API
- ✅ Mniej konfiguracji
- ✅ Wbudowane w framework
- ✅ Horizon dla monitorowania

**Wady:**
- ❌ Brak stopPropagation
- ❌ Mniej elastyczne niż Symfony

---

### Symfony EventDispatcher/Messenger:

**Zalety:**
- ✅ Bardziej elastyczne
- ✅ StopPropagation
- ✅ Event names jako strings
- ✅ Zaawansowane priorities

**Wady:**
- ⚠️ Więcej konfiguracji
- ⚠️ Więcej boilerplate
- ⚠️ Messenger = osobny component

---

## 🔄 Migracja: Symfony → Laravel

Jeśli masz doświadczenie z Symfony:

**Symfony:**
```php
$dispatcher->dispatch(new Event(), 'event.name');
```

**Laravel:**
```php
event(new Event());  // Prostsze!
```

**Główne różnice:**
1. Event name = klasa (nie string)
2. Brak stopPropagation (ale można obsłużyć w listenerze)
3. Priority = kolejność w array
4. Jobs wbudowane (nie potrzebujesz Messenger)

---

## 📚 Dokumentacja

- **Laravel Events:** https://laravel.com/docs/events
- **Laravel Jobs:** https://laravel.com/docs/queues
- **Symfony Events:** https://symfony.com/doc/current/components/event_dispatcher.html
- **Symfony Messenger:** https://symfony.com/doc/current/messenger.html

