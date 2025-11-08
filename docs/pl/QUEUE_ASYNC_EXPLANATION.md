# Laravel Queue: Async vs Sync

## 🔄 Czy `Bus::dispatch()` działa asynchronicznie?

**Odpowiedź:** Zależy od konfiguracji `QUEUE_CONNECTION`!

## 📊 Konfiguracja w Twoim Projekcie

### ✅ **ASYNCHRONICZNIE** (w produkcji):

**Produkcja/Staging/Local:**
- `QUEUE_CONNECTION=redis` (z `env/production.env.example`)
- Używa **Horizon** do przetwarzania jobów
- Job trafia do Redis → Horizon worker wykonuje → status w Cache

**Domyślne (fallback):**
- `QUEUE_CONNECTION=database` (jeśli brak ENV)
- Job trafia do tabeli `jobs` → worker musi być uruchomiony (`php artisan queue:work`)

### ❌ **SYNCHRONICZNIE** (w testach):

**Testy:**
- `QUEUE_CONNECTION=sync` (w `phpunit.xml.dist`)
- Job wykonuje się **natychmiast** w tym samym procesie
- Nie trafia do kolejki - wykonanie blokujące

**CI:**
- `QUEUE_CONNECTION=sync` (w `env/ci.env.example`)
- Synchroniczne wykonanie

## 🔍 Jak to działa?

### Obecny kod w `MockAiService.php`:

```php
Bus::dispatch(function () use ($slug, $jobId) {
    sleep(3);  // Symulacja długiego procesu AI
    // ... tworzenie Movie/Person ...
});
```

### Zachowanie z `QUEUE_CONNECTION=sync` (testy):
```php
// Request przychodzi
Bus::dispatch(...);  // ← Wykonuje się NATYCHMIAST (blokuje!)
// sleep(3) - użytkownik czeka 3 sekundy
// Kontynuuje dalej...
```

### Zachowanie z `QUEUE_CONNECTION=redis` (produkcja):
```php
// Request przychodzi
Bus::dispatch(...);  // ← Zapisuje job do Redis (NATYCHMIASTOWY return!)
// Kontynuuje dalej - użytkownik dostaje odpowiedź 202

// W tle (Horizon worker):
// - Odbiera job z Redis
// - Wykonuje sleep(3)
// - Tworzy Movie/Person
// - Aktualizuje Cache
```

## ✅ Zalety Asynchronicznego Wykonania

1. **Szybka odpowiedź** - API zwraca `202 Accepted` natychmiast
2. **Skalowalność** - wiele workerów może przetwarzać joby równolegle
3. **Nieblokujące** - request nie czeka na zakończenie joba
4. **Retry** - automatyczne ponowne próby przy błędach

## ⚠️ Wymagania dla Async

Aby działało asynchronicznie, musisz mieć:

1. **Worker uruchomiony:**
   ```bash
   # Opcja 1: Horizon (dla Redis)
   php artisan horizon
   
   # Opcja 2: Queue worker (dla database)
   php artisan queue:work
   ```

2. **W Docker Compose:**
   ```yaml
   horizon:
     command: sh -lc "php artisan horizon"
     # ← Ten kontener przetwarza joby!
   ```

## 📝 Aktualna Konfiguracja

| Środowisko | QUEUE_CONNECTION | Async? | Worker |
|-----------|-----------------|--------|--------|
| Production | `redis` | ✅ TAK | Horizon |
| Staging | `redis` | ✅ TAK | Horizon |
| Local | `redis` | ✅ TAK | Horizon |
| Testy | `sync` | ❌ NIE | - |
| CI | `sync` | ❌ NIE | - |

## 🎯 Wniosek

**W produkcji:** `Bus::dispatch()` **DZIAŁA ASYNCHRONICZNIE** ✅
- Job trafia do Redis
- Horizon worker przetwarza w tle
- API zwraca `202` natychmiast

**W testach:** `Bus::dispatch()` **DZIAŁA SYNCHRONICZNIE** ⚠️
- Job wykonuje się natychmiast
- Test czeka na zakończenie (3 sekundy sleep)
- Szybsze dla testów (nie potrzeba Redis/worker)

## 🔧 Jak sprawdzić czy działa async?

```php
// W kodzie (dev/prod):
Log::info('Before dispatch');
Bus::dispatch(function() {
    sleep(10);
    Log::info('Job executed');
});
Log::info('After dispatch');

// Z QUEUE_CONNECTION=redis:
// Logi: "Before dispatch" → "After dispatch" → (10s później) "Job executed"
// ✅ ASYNC

// Z QUEUE_CONNECTION=sync:
// Logi: "Before dispatch" → (10s czeka) → "Job executed" → "After dispatch"
// ❌ SYNC
```

