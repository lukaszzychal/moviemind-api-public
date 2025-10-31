# Mock vs Real Jobs - Konfiguracja

## 🎯 Overview

Aplikacja wspiera dwie wersje Job classes:
- **Mock Jobs** - dla development/testów (symulują AI)
- **Real Jobs** - dla production (używają prawdziwego AI API)

Przełączanie odbywa się przez `AI_SERVICE` env variable.

---

## 📊 Struktura

### Jobs:

```
app/Jobs/
├── MockGenerateMovieJob.php      ← AI_SERVICE=mock
├── RealGenerateMovieJob.php      ← AI_SERVICE=real
├── MockGeneratePersonJob.php     ← AI_SERVICE=mock
└── RealGeneratePersonJob.php     ← AI_SERVICE=real
```

### Listeners:

```
app/Listeners/
├── QueueMovieGenerationJob.php   ← Wybiera Mock/Real na podstawie config
└── QueuePersonGenerationJob.php   ← Wybiera Mock/Real na podstawie config
```

---

## ⚙️ Konfiguracja

### 1. Zmienna Środowiskowa

```env
# AI Service Configuration
AI_SERVICE=mock  # lub 'real'
```

### 2. Config

`config/services.php`:
```php
'ai' => [
    'service' => env('AI_SERVICE', 'mock'),
],

'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'url' => env('OPENAI_URL', 'https://api.openai.com/v1/chat/completions'),
],
```

---

## 🔄 Jak To Działa

### Flow:

```
Controller
  ↓
event(new MovieGenerationRequested($slug, $jobId))
  ↓
Listener (QueueMovieGenerationJob)
  ↓ sprawdza config('services.ai.service')
  ↓
  ├─ 'mock' → MockGenerateMovieJob::dispatch()
  └─ 'real' → RealGenerateMovieJob::dispatch()
  ↓
Queue Worker
  ↓
Job::handle() → wykonuje pracę
```

---

## 📝 MockGenerateMovieJob

**Kiedy:** `AI_SERVICE=mock`

**Co robi:**
- ✅ Symuluje AI generation (`sleep(3)`)
- ✅ Tworzy mock data z slug
- ✅ Nie potrzebuje AI API key
- ✅ Szybkie testowanie

**Przykład:**
```php
// Slug: "the-matrix"
// Rezultat: Movie z tytułem "The Matrix", release_year=1999, director="Mock AI Director"
```

---

## 📝 RealGenerateMovieJob

**Kiedy:** `AI_SERVICE=real`

**Co robi:**
- ✅ Wywołuje OpenAI API
- ✅ Używa prawdziwego AI do generowania
- ✅ Wymaga `OPENAI_API_KEY`
- ✅ Dłuższy timeout (120s vs 90s)

**Konfiguracja:**
```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

**Przykład API Call:**
```php
Http::post('https://api.openai.com/v1/chat/completions', [
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'You are a movie database assistant...'],
        ['role' => 'user', 'content' => 'Generate movie information for slug: the-matrix'],
    ],
]);
```

---

## 🔍 Różnice

| Aspekt | MockGenerateMovieJob | RealGenerateMovieJob |
|--------|----------------------|----------------------|
| **AI API** | ❌ Symulacja | ✅ Prawdziwy API call |
| **Timeout** | 90s | 120s |
| **API Key** | ❌ Nie potrzebne | ✅ Wymagane |
| **Użycie** | Development/Testy | Production |
| **Dane** | Mock z slug | Real z AI API |

---

## 🎯 Użycie

### Development (Mock):

```env
AI_SERVICE=mock
```

**Flow:**
```
Event → Listener → MockGenerateMovieJob → sleep(3) → Mock data → Done
```

---

### Production (Real):

```env
AI_SERVICE=real
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

**Flow:**
```
Event → Listener → RealGenerateMovieJob → OpenAI API → Real data → Done
```

---

## 🔧 Implementacja Listenera

```php
class QueueMovieGenerationJob
{
    public function handle(MovieGenerationRequested $event): void
    {
        $aiService = config('services.ai.service', 'mock');

        match ($aiService) {
            'real' => RealGenerateMovieJob::dispatch($event->slug, $event->jobId),
            'mock' => MockGenerateMovieJob::dispatch($event->slug, $event->jobId),
            default => throw new \InvalidArgumentException(...),
        };
    }
}
```

**Listener automatycznie wybiera** odpowiedni Job na podstawie konfiguracji!

---

## ✅ Zalety

1. **Elastyczność** - łatwe przełączanie Mock/Real
2. **Separation** - Mock i Real w osobnych klasach
3. **Testowalność** - Mock dla testów, Real dla prod
4. **Configuration-driven** - jeden env variable

---

## 📚 Pliki

- **Mock Jobs:** `app/Jobs/MockGenerateMovieJob.php`, `MockGeneratePersonJob.php`
- **Real Jobs:** `app/Jobs/RealGenerateMovieJob.php`, `RealGeneratePersonJob.php`
- **Listeners:** `app/Listeners/QueueMovieGenerationJob.php`, `QueuePersonGenerationJob.php`
- **Config:** `config/services.php`

---

## 🔍 Sprawdzenie Który Job Jest Używany

```bash
# W tinker
php artisan tinker
>>> config('services.ai.service')
"mock" # lub "real"

# W logach
tail -f storage/logs/laravel.log | grep "GenerateMovieJob"
# Zobaczysz: MockGenerateMovieJob lub RealGenerateMovieJob
```

---

## ⚠️ Uwagi

### Mock Jobs:
- ✅ Bezpieczne do użytku w testach/CI
- ✅ Nie kosztują API calls
- ✅ Przewidywalne wyniki

### Real Jobs:
- ⚠️ Wymagają `OPENAI_API_KEY`
- ⚠️ Kosztują API calls
- ⚠️ Dłuższy timeout
- ⚠️ Możliwe błędy API

---

## 🚀 Migracja

### Z Mock do Real:

1. **Dodaj OpenAI API Key:**
   ```env
   OPENAI_API_KEY=sk-...
   OPENAI_MODEL=gpt-4o-mini
   ```

2. **Zmień AI_SERVICE:**
   ```env
   AI_SERVICE=real
   ```

3. **Testuj:**
   ```bash
   php artisan queue:work
   # Wywołaj endpoint
   ```

4. **Sprawdź logi:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 📊 Podsumowanie

**Mock Jobs** (`AI_SERVICE=mock`):
- Symulują AI (sleep, mock data)
- Dla development/testów
- Nie potrzebują API key

**Real Jobs** (`AI_SERVICE=real`):
- Używają prawdziwego AI API
- Dla production
- Wymagają `OPENAI_API_KEY`

**Listener automatycznie wybiera** odpowiedni Job na podstawie `AI_SERVICE`!

