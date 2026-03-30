# Jak Sprawdzić Czy Queue Działa Async?

## 🔍 Szybka Diagnoza

### 1. Sprawdź konfigurację `QUEUE_CONNECTION`

```bash
cd api
cat .env | grep QUEUE_CONNECTION
```

**Wyniki:**
- `QUEUE_CONNECTION=sync` → **SYNC** (synchroniczne) ❌
- `QUEUE_CONNECTION=database` → **ASYNC** (wymaga worker) ✅
- `QUEUE_CONNECTION=redis` → **ASYNC** (wymaga worker) ✅
- `QUEUE_CONNECTION=rabbitmq` → **ASYNC** (wymaga worker) ✅

### 2. Sprawdź czy worker jest uruchomiony

**W Docker:**
```bash
docker compose ps | grep horizon
# Lub
docker compose logs horizon
```

**Lokalnie:**
```bash
ps aux | grep "queue:work\|horizon"
```

**Jeśli worker NIE jest uruchomiony:**
- Z `QUEUE_CONNECTION=database` → joby trafiają do tabeli `jobs` ale **nie są przetwarzane** ⚠️
- Z `QUEUE_CONNECTION=redis` → joby trafiają do Redis ale **nie są przetwarzane** ⚠️

### 3. Test Async vs Sync

Dodaj do `routes/web.php` lub `routes/api.php`:

```php
Route::get('/test-queue', function () {
    $start = microtime(true);
    
    \Illuminate\Support\Facades\Bus::dispatch(function () {
        sleep(5); // Symulacja długiego procesu
        \Illuminate\Support\Facades\Log::info('Job executed!');
    });
    
    $end = microtime(true);
    $duration = ($end - $start) * 1000; // w milisekundach
    
    return response()->json([
        'queue_connection' => config('queue.default'),
        'duration_ms' => round($duration, 2),
        'is_async' => $duration < 1000, // Jeśli < 1s = async
    ]);
});
```

**Wyniki:**
- `duration_ms < 100` → **ASYNC** ✅ (job trafił do kolejki, request zakończył się szybko)
- `duration_ms > 4000` → **SYNC** ❌ (request czekał na wykonanie sleep(5))

### 4. Sprawdź logi

```bash
# Laravel logi
tail -f api/storage/logs/laravel.log

# Lub jeśli używasz Docker
docker compose logs -f php
```

Jeśli widzisz `Job executed!` **zaraz** po request → **SYNC**
Jeśli widzisz `Job executed!` **po czasie** → **ASYNC**

## 📊 Twoja Aktualna Konfiguracja

### Z `api/.env`:
```
QUEUE_CONNECTION=database
```

**Oznacza to:**
- ✅ **ASYNC** (jeśli worker uruchomiony)
- ⚠️ **NIE DZIAŁA** (jeśli worker NIE uruchomiony)

### Z `compose.yml`:
```yaml
horizon:
  environment:
    QUEUE_CONNECTION: redis
```

**Oznacza to:**
- ✅ **ASYNC** (Horizon worker przetwarza joby z Redis)

## 🚨 Problem: Różne Konfiguracje!

**Masz różne konfiguracje w różnych miejscach:**
1. `api/.env`: `QUEUE_CONNECTION=database`
2. `compose.yml`: `QUEUE_CONNECTION=redis` (tylko w horizon)
3. `env/local.env.example`: `QUEUE_CONNECTION=redis`

**To może powodować problemy!**

## ✅ Rozwiązanie

### Opcja 1: Użyj Redis (Rekomendowane)

**1. Zaktualizuj `api/.env`:**
```env
QUEUE_CONNECTION=redis
```

**2. Uruchom Horizon worker:**
```bash
docker compose up -d horizon
```

**3. Sprawdź czy działa:**
```bash
docker compose logs horizon | tail -20
```

### Opcja 2: Użyj Database Queue

**1. Zostaw `api/.env`:**
```env
QUEUE_CONNECTION=database
```

**2. Uruchom queue worker:**
```bash
# W Docker
docker compose exec php php artisan queue:work

# Lub lokalnie
php artisan queue:work
```

## 🎯 Rekomendacja dla MVP

**Użyj Redis + Horizon** (masz już to skonfigurowane w Docker):

1. ✅ Zmień `api/.env`: `QUEUE_CONNECTION=redis`
2. ✅ Uruchom: `docker compose up -d horizon`
3. ✅ Sprawdź: `docker compose logs horizon`

**Dlaczego Redis?**
- Szybsze niż database queue
- Horizon dashboard do monitorowania
- Już masz Redis w Docker Compose
- Lepsze dla produkcji

