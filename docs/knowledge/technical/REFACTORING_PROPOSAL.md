# 🔧 Propozycja Refaktoryzacji - Ujednolicenie Mock/Real i Dependency Injection

## 🔍 Problemy Zidentyfikowane

### 1. ❌ Duplikacja Logiki Mock/Real

**Problem:** Wybór między Mock a Real jest w **dwóch miejscach**:

#### Miejsce 1: `AppServiceProvider` (linie 27-33)
```php
$this->app->bind(AiServiceInterface::class, function ($app) use ($aiService) {
    return match ($aiService) {
        'real' => $app->make(RealAiService::class),
        'mock' => $app->make(MockAiService::class),
        // ...
    };
});
```

#### Miejsce 2: `QueueMovieGenerationJob` Listener (linie 17-23)
```php
$aiService = config('services.ai.service', 'mock');
match ($aiService) {
    'real' => RealGenerateMovieJob::dispatch(...),
    'mock' => MockGenerateMovieJob::dispatch(...),
    // ...
};
```

**Skutki:**
- Logika wyboru jest powielona
- Trudniejsze utrzymanie (zmiana w dwóch miejscach)
- Ryzyko niespójności

---

### 2. ❌ Service Location zamiast Dependency Injection

**Problem:** W Jobs używany jest service location:
```php
// RealGenerateMovieJob.php (linia 60)
$openAiClient = app(OpenAiClientInterface::class);
```

**Dlaczego to problem:**
- Mniej czytelne - nie widać zależności w konstruktorze
- Trudniejsze testowanie
- Service Location jest anti-pattern (w niektórych kontekstach)
- Musisz przejrzeć cały kod żeby zobaczyć zależności

**Alternatywa:** Method Injection w `handle()`:
```php
public function handle(OpenAiClientInterface $openAiClient): void
{
    // Laravel automatycznie wstrzykuje zależności
}
```

**Uwaga:** Jobs są serializowane do queue, więc **NIE można** użyć constructor injection, ale **MOŻNA** method injection w `handle()`.

---

### 3. ⚠️ Legacy: AiServiceInterface vs Events

**Obecny stan:**
- `GenerateController` → używa **Events** (nowy sposób) ✅
- `MovieController::show()` → używa **AiServiceInterface** (legacy) ⚠️
- `PersonController::show()` → używa **AiServiceInterface** (legacy) ⚠️

**Pytanie:** Czy `AiServiceInterface` jest jeszcze potrzebny?

---

## ✅ Propozycje Rozwiązań

### Rozwiązanie 1: Ujednolicenie Wyboru Mock/Real

**Utworzyć helper/service do wyboru:**
```php
// app/Helpers/AiServiceSelector.php lub app/Services/AiServiceSelector.php
class AiServiceSelector
{
    public static function getService(): string
    {
        return config('services.ai.service', 'mock');
    }
    
    public static function isReal(): bool
    {
        return self::getService() === 'real';
    }
    
    public static function isMock(): bool
    {
        return self::getService() === 'mock';
    }
    
    public static function validate(): void
    {
        $service = self::getService();
        if (!in_array($service, ['mock', 'real'])) {
            throw new \InvalidArgumentException("Invalid AI service: {$service}. Must be 'mock' or 'real'.");
        }
    }
}
```

**Korzyści:**
- ✅ Jedna logika w jednym miejscu
- ✅ Łatwiejsze utrzymanie
- ✅ Możliwość dodania dodatkowych funkcji (cache, logging, etc.)

---

### Rozwiązanie 2: Method Injection w Jobs

**Przed (Service Location):**
```php
class RealGenerateMovieJob implements ShouldQueue
{
    public function handle(): void
    {
        $openAiClient = app(OpenAiClientInterface::class); // ❌ Service Location
        // ...
    }
}
```

**Po (Method Injection):**
```php
class RealGenerateMovieJob implements ShouldQueue
{
    public function handle(OpenAiClientInterface $openAiClient): void // ✅ DI
    {
        // Laravel automatycznie wstrzykuje zależności
        $aiResponse = $openAiClient->generateMovie($this->slug);
        // ...
    }
}
```

**Korzyści:**
- ✅ Czytelne zależności w sygnaturze metody
- ✅ Łatwiejsze testowanie (można mockować w testach)
- ✅ Lepsze IDE support (autocomplete, refactoring)
- ✅ Zgodne z best practices Laravel

**Uwaga:** Method injection działa dla `handle()` w Jobs! ✅

---

### Rozwiązanie 3: Refaktoryzacja MovieController i PersonController

**Opcja A: Przenieść do Events (jak w GenerateController)**

**Przed:**
```php
// MovieController::show()
$this->ai->queueMovieGeneration($slug, $jobId);
```

**Po:**
```php
// MovieController::show()
event(new MovieGenerationRequested($slug, $jobId));
```

**Korzyści:**
- ✅ Spójna architektura (wszystkie controllery używają Events)
- ✅ Można usunąć `AiServiceInterface` binding
- ✅ Uproszczenie kodu

**Wada:**
- ⚠️ Wymaga refaktoryzacji (breaking change, ale wewnętrzne)

**Opcja B: Zostawić jak jest (backward compatibility)**

Jeśli `AiServiceInterface` jest używany gdzieś indziej lub potrzebny do testów, można zostawić.

---

## 📋 Plan Refaktoryzacji (Kroki)

### Krok 1: Utworzyć `AiServiceSelector` Helper
```bash
php artisan make:class Helpers/AiServiceSelector
```

### Krok 2: Użyć w Listenerach
```php
// QueueMovieGenerationJob.php
public function handle(MovieGenerationRequested $event): void
{
    $aiService = AiServiceSelector::getService();
    
    match ($aiService) {
        'real' => RealGenerateMovieJob::dispatch(...),
        'mock' => MockGenerateMovieJob::dispatch(...),
        default => throw new \InvalidArgumentException(...),
    };
}
```

### Krok 3: Użyć w AppServiceProvider
```php
// AppServiceProvider.php
$this->app->bind(AiServiceInterface::class, function ($app) {
    $service = AiServiceSelector::getService();
    
    return match ($service) {
        'real' => $app->make(RealAiService::class),
        'mock' => $app->make(MockAiService::class),
        default => throw new \InvalidArgumentException(...),
    };
});
```

### Krok 4: Method Injection w Jobs
```php
// RealGenerateMovieJob.php
public function handle(OpenAiClientInterface $openAiClient): void
{
    // Zamiast: $openAiClient = app(OpenAiClientInterface::class);
    $aiResponse = $openAiClient->generateMovie($this->slug);
    // ...
}
```

### Krok 5: (Opcjonalnie) Refaktoryzacja Controllers
```php
// MovieController.php - zastąpić
$this->ai->queueMovieGeneration($slug, $jobId);

// Na:
event(new MovieGenerationRequested($slug, $jobId));
```

### Krok 6: (Opcjonalnie) Usunąć AiServiceInterface
Jeśli nie jest już używany, można usunąć:
- Binding w `AppServiceProvider`
- Interface `AiServiceInterface`
- Klasy `MockAiService` i `RealAiService`

---

## 🎯 Rekomendowany Plan Działania

### Faza 1: Bezpieczne Ulepszenia (Nie Breaking)
1. ✅ Utworzyć `AiServiceSelector` helper
2. ✅ Zmienić Jobs na method injection
3. ✅ Użyć helper w Listenerach i AppServiceProvider

### Faza 2: Refaktoryzacja (Wymaga Testów)
4. ⚠️ Przenieść `MovieController` i `PersonController` na Events
5. ⚠️ Usunąć `AiServiceInterface` jeśli nie używany

---

## 💡 Dodatkowe Ulepszenia

### 1. Typ Enum dla AI Service
```php
enum AiService: string
{
    case MOCK = 'mock';
    case REAL = 'real';
    
    public static function current(): self
    {
        $value = config('services.ai.service', 'mock');
        return self::from($value);
    }
}
```

### 2. Cache dla Config
```php
class AiServiceSelector
{
    private static ?string $cached = null;
    
    public static function getService(): string
    {
        if (self::$cached === null) {
            self::$cached = config('services.ai.service', 'mock');
        }
        return self::$cached;
    }
}
```

### 3. Logging Wyboru
```php
public static function getService(): string
{
    $service = config('services.ai.service', 'mock');
    Log::debug('AI Service selected', ['service' => $service]);
    return $service;
}
```

---

## ✅ Podsumowanie

### Problemy:
1. ❌ Duplikacja logiki mock/real (2 miejsca)
2. ❌ Service location zamiast DI w Jobs
3. ⚠️ Legacy `AiServiceInterface` vs nowe Events

### Rozwiązania:
1. ✅ `AiServiceSelector` helper - jedna logika
2. ✅ Method injection w Jobs - czytelniejsze zależności
3. ⚠️ Refaktoryzacja Controllers (opcjonalnie)

### Korzyści:
- ✅ Lepsza czytelność kodu
- ✅ Łatwiejsze utrzymanie
- ✅ Zgodność z best practices
- ✅ Łatwiejsze testowanie

---

**Status:** Propozycje do implementacji  
**Priorytet:** Wysoki - poprawia jakość kodu  
**Breaking Changes:** Tylko w Faza 2 (opcjonalnie)

