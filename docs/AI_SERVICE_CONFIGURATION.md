# AI Service Configuration - Mock vs Real

## 🎯 Overview

Aplikacja wspiera przełączanie między **MockAiService** (dla development/testów) a **RealAiService** (dla production) poprzez konfigurację.

---

## 📊 Architektura

### MockAiService
- ✅ Używa `Bus::dispatch(closure)` - stara architektura
- ✅ Symuluje AI generation (sleep, mock data)
- ✅ Dla lokalnego development i testów
- ✅ Nie wymaga prawdziwego AI API

### RealAiService
- ✅ Używa **Events + Jobs** - nowa architektura
- ✅ Dispatchuje `MovieGenerationRequested` / `PersonGenerationRequested` events
- ✅ Listener dispatchuje `GenerateMovieJob` / `GeneratePersonJob`
- ✅ Dla production - można zintegrować z prawdziwym AI API w Job classes

---

## ⚙️ Konfiguracja

### 1. Zmienna Środowiskowa

Dodaj do `.env`:

```env
# AI Service Configuration
# Options: 'mock' or 'real'
AI_SERVICE=mock
```

**Wartości:**
- `mock` - używa MockAiService (domyślne, dla development)
- `real` - używa RealAiService (dla production)

### 2. Config File

Konfiguracja jest w `config/services.php`:

```php
'ai' => [
    'service' => env('AI_SERVICE', 'mock'), // 'mock' or 'real'
],
```

### 3. Service Provider Binding

`AppServiceProvider` automatycznie wybiera implementację:

```php
$this->app->bind(AiServiceInterface::class, function ($app) use ($aiService) {
    return match ($aiService) {
        'real' => $app->make(RealAiService::class),
        'mock' => $app->make(MockAiService::class),
        default => throw new \InvalidArgumentException(...),
    };
});
```

---

## 🔄 Różnice

### MockAiService Flow:

```
Controller
  ↓
$this->ai->queueMovieGeneration($slug, $jobId)
  ↓
MockAiService::queueMovieGeneration()
  ↓
Bus::dispatch(function() { ... closure ... })
  ↓
Queue Worker wykonuje closure
```

**Charakterystyka:**
- Używa closure w Bus::dispatch
- Symuluje AI (sleep, mock data)
- Brak Events

---

### RealAiService Flow:

```
Controller
  ↓
$this->ai->queueMovieGeneration($slug, $jobId)
  ↓
RealAiService::queueMovieGeneration()
  ↓
event(new MovieGenerationRequested($slug, $jobId))
  ↓
EventServiceProvider → Listener
  ↓
GenerateMovieJob::dispatch()
  ↓
Queue Worker wykonuje Job
```

**Charakterystyka:**
- Używa Events + Jobs architecture
- Można zintegrować z prawdziwym AI API w Job
- Lepsze separation of concerns

---

## 📝 Użycie

### Development (Mock):

```env
AI_SERVICE=mock
```

**Korzyści:**
- ✅ Szybkie testowanie (nie potrzebujesz AI API)
- ✅ Przewidywalne wyniki
- ✅ Brak kosztów API calls

---

### Production (Real):

```env
AI_SERVICE=real
```

**Korzyści:**
- ✅ Events + Jobs architecture
- ✅ Można zintegrować prawdziwy AI API w `GenerateMovieJob`
- ✅ Lepsze monitorowanie (Horizon)
- ✅ Retry logic out-of-the-box

---

## 🔧 Integracja z Prawdziwym AI API

### Obecna Implementacja (Mock):

```php
// GenerateMovieJob::handle()
sleep(3);  // Symulacja
Movie::create([...]);  // Mock data
```

### Produkcja (Real AI):

```php
// GenerateMovieJob::handle()
$response = Http::timeout(30)->post('https://ai-api.com/generate', [
    'slug' => $this->slug,
    'job_id' => $this->jobId,
]);

$result = $response->json();
// Użyj prawdziwych danych z AI API
Movie::create([
    'title' => $result['title'],
    'slug' => $result['slug'],
    // ...
]);
```

---

## 🎯 Kiedy Co Używać?

### MockAiService (`AI_SERVICE=mock`):

**Użyj gdy:**
- ✅ Lokalny development
- ✅ Testy jednostkowe/integracyjne
- ✅ CI/CD pipeline
- ✅ Demo/prezentacje
- ✅ Nie masz dostępu do AI API

---

### RealAiService (`AI_SERVICE=real`):

**Użyj gdy:**
- ✅ Production environment
- ✅ Masz dostęp do prawdziwego AI API
- ✅ Chcesz używać Events + Jobs architecture
- ✅ Potrzebujesz retry/timeout logic

---

## 📊 Porównanie

| Aspekt | MockAiService | RealAiService |
|--------|---------------|---------------|
| **Architektura** | Bus::dispatch(closure) | Events + Jobs |
| **AI API** | ❌ Symulacja | ✅ Można zintegrować |
| **Retry Logic** | ❌ Brak | ✅ Wbudowane w Job |
| **Monitoring** | ❌ Trudne | ✅ Horizon support |
| **Użycie** | Development/Testy | Production |

---

## 🚀 Migracja

### Z Mock do Real:

1. **Zmień `.env`:**
   ```env
   AI_SERVICE=real
   ```

2. **Zaktualizuj `GenerateMovieJob`** (opcjonalnie):
   ```php
   // Zamiast sleep(3)
   $response = Http::post('https://your-ai-api.com/generate', [...]);
   ```

3. **Testuj:**
   ```bash
   php artisan test
   ```

4. **Deploy:**
   ```bash
   # Upewnij się że AI_SERVICE=real w production .env
   ```

---

## 🔍 Debugging

### Sprawdź która implementacja jest używana:

```php
// W tinker lub controller
$service = app(AiServiceInterface::class);
dd(get_class($service));
// Output: App\Services\MockAiService lub App\Services\RealAiService
```

### Sprawdź konfigurację:

```php
dd(config('services.ai.service'));
// Output: 'mock' lub 'real'
```

---

## 📚 Pliki

- **Interface:** `app/Services/AiServiceInterface.php`
- **Mock:** `app/Services/MockAiService.php`
- **Real:** `app/Services/RealAiService.php`
- **Binding:** `app/Providers/AppServiceProvider.php`
- **Config:** `config/services.php`

---

## ✅ Podsumowanie

1. **MockAiService** = dla development/testów (closure-based)
2. **RealAiService** = dla production (Events + Jobs)
3. **Przełączanie** = przez `AI_SERVICE=mock|real` w `.env`
4. **Binding** = automatyczny w AppServiceProvider

