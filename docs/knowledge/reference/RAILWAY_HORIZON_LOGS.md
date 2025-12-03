# Jak Dostać Się do Logów Horizon na Railway

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Instrukcje dostępu do logów Laravel Horizon na Railway  
> **Kategoria:** reference

## 🎯 Cel

Ten dokument opisuje wszystkie sposoby dostępu do logów Laravel Horizon na Railway dla projektu MovieMind API.

---

## 📋 Metody Dostępu do Logów Horizon

### Metoda 1: Railway Dashboard - Logi Serwisu (Najprostsza)

#### Scenariusz A: Horizon jako Osobny Serwis

Jeśli Horizon działa w osobnym serwisie na Railway:

1. Otwórz [Railway Dashboard](https://railway.app)
2. Wybierz projekt MovieMind API
3. Znajdź serwis **"Horizon"** lub serwis z nazwą zawierającą "horizon"
4. Kliknij na serwis
5. Przejdź do zakładki **"Deployments"**
6. Wybierz aktywny deployment
7. Kliknij **"Logs"** - zobaczysz logi z kontenera Horizon

#### Scenariusz B: Horizon w Tym Samym Serwisie co Aplikacja

Jeśli Horizon działa w tym samym kontenerze co aplikacja główna:

1. Otwórz [Railway Dashboard](https://railway.app)
2. Wybierz projekt MovieMind API
3. Wybierz serwis aplikacji (główny serwis)
4. Przejdź do zakładki **"Deployments"**
5. Wybierz aktywny deployment
6. Kliknij **"Logs"** - zobaczysz logi z kontenera (w tym logi Horizon)

**Filtrowanie logów:**
- Logi Horizon zwykle zaczynają się od `[Horizon]` lub zawierają informacje o procesowaniu jobów
- Możesz filtrować logi w Railway Dashboard używając wyszukiwarki

---

### Metoda 2: Railway Dashboard - Shell w Kontenerze

1. Otwórz [Railway Dashboard](https://railway.app)
2. Wybierz serwis aplikacji (lub serwis Horizon, jeśli osobny)
3. Przejdź do zakładki **"Deployments"**
4. Wybierz aktywny deployment
5. Kliknij **"Shell"** (otwiera interaktywny terminal w kontenerze)
6. W shellu kontenera:

```bash
# Sprawdź czy Horizon działa
ps aux | grep horizon

# Zobacz logi Horizon (jeśli są w pliku)
tail -f storage/logs/laravel.log | grep -i horizon

# Lub wszystkie logi Laravel (w tym Horizon)
tail -f storage/logs/laravel.log

# Sprawdź logi Laravel (inne pliki)
ls -la storage/logs/

# Otwórz konkretny plik logów
cat storage/logs/laravel.log | grep -i horizon
```

---

### Metoda 3: Railway CLI - Logi przez Terminal

#### Instalacja Railway CLI (jeśli nie masz):

```bash
# macOS
brew install railway

# Lub używając npm
npm i -g @railway/cli
```

#### Logowanie do Railway:

```bash
railway login
```

#### Wyświetlanie Logów:

```bash
# Listuj wszystkie serwisy w projekcie
railway status

# Wyświetl logi serwisu aplikacji
railway logs --service <nazwa-serwisu-aplikacji>

# Wyświetl logi serwisu Horizon (jeśli osobny)
railway logs --service <nazwa-serwisu-horizon>

# Wyświetl logi z ostatnich 100 linii
railway logs --tail 100

# Wyświetl logi w czasie rzeczywistym (follow)
railway logs --follow
```

**Przykład:**
```bash
# Jeśli serwis nazywa się "api" lub "app"
railway logs --service api --follow

# Jeśli serwis Horizon nazywa się "horizon"
railway logs --service horizon --follow
```

---

### Metoda 4: Horizon Dashboard (Web UI)

Horizon ma wbudowany dashboard web, który pokazuje:
- Status jobów (pending, processing, completed, failed)
- Metryki i statystyki
- Szczegóły jobów
- Logi procesowania

#### Dostęp do Horizon Dashboard:

1. Otwórz aplikację na Railway (np. `https://moviemind-api-staging.up.railway.app`)
2. Przejdź do `/horizon` (domyślna ścieżka)
   - URL: `https://moviemind-api-staging.up.railway.app/horizon`
3. Jeśli wymaga autoryzacji:
   - Sprawdź konfigurację `api/config/horizon.php`
   - Dla staging/development może być wyłączona autoryzacja

#### Konfiguracja Autoryzacji Horizon:

Sprawdź `api/config/horizon.php`:

```php
'auth' => [
    'bypass_environments' => explode(',', env('HORIZON_AUTH_BYPASS_ENVS', 'local,staging')),
    'allowed_emails' => array_filter(array_map('trim', explode(',', env('HORIZON_ALLOWED_EMAILS', '')))),
],
```

**Dla staging** - autoryzacja jest zwykle wyłączona (patrz: `HORIZON_AUTH_BYPASS_ENVS=local,staging`)

---

### Metoda 5: Logi w Pliku (Przez Shell)

Jeśli chcesz zobaczyć surowe logi z pliku:

1. Otwórz Shell w Railway Dashboard (Metoda 2)
2. W shellu:

```bash
# Sprawdź strukturę katalogów logów
ls -la storage/logs/

# Zobacz ostatnie 50 linii logów
tail -n 50 storage/logs/laravel.log

# Zobacz logi Horizon (filtrowanie)
grep -i horizon storage/logs/laravel.log

# Zobacz logi w czasie rzeczywistym
tail -f storage/logs/laravel.log | grep -i horizon

# Zobacz wszystkie logi z dzisiaj
grep "$(date +%Y-%m-%d)" storage/logs/laravel.log

# Zobacz logi z określonego joba (jeśli znasz ID)
grep "job-id-123" storage/logs/laravel.log
```

---

## 🔍 Jak Sprawdzić Czy Horizon Działa

### 1. Przez Railway Dashboard Logi:

Sprawdź logi serwisu - powinny zawierać:
```
Horizon started successfully
Processing jobs...
```

### 2. Przez Shell:

```bash
# Sprawdź czy proces Horizon działa
ps aux | grep horizon

# Powinno pokazać coś jak:
# app    123  php artisan horizon
```

### 3. Przez Horizon Dashboard:

Przejdź do `/horizon` w przeglądarce - jeśli dashboard się ładuje, Horizon działa.

### 4. Przez Redis (jeśli masz dostęp):

```bash
# Połącz się z Redis
redis-cli -h $REDIS_HOST -p $REDIS_PORT

# Sprawdź klucze Horizon
KEYS *horizon*

# Sprawdź metryki
HGETALL "horizon:metrics:snapshots"
```

---

## 📊 Co Pokazują Logi Horizon

### Typowe Logi Horizon:

```
[Horizon] Processing: App\Jobs\GenerateMovieJob
[Horizon] Processed: App\Jobs\GenerateMovieJob (123ms)
[Horizon] Failed: App\Jobs\GenerateMovieJob (exception)
[Horizon] Supervisor started
[Horizon] Worker started
```

### Co Znaleźć w Logach:

- **Status jobów:** pending, processing, completed, failed
- **Czasy wykonania:** jak długo trwają joby
- **Błędy:** wyjątki i stack trace
- **Metryki:** liczba przetworzonych jobów, throughput

---

## ⚙️ Konfiguracja Logowania Horizon

### Domyślne Logowanie:

Horizon loguje do standardowego kanału Laravel (`storage/logs/laravel.log`).

### Sprawdzenie Konfiguracji:

Plik `api/config/horizon.php` - sekcja logowania jest domyślnie w Laravel.

### Zmiana Poziomu Logowania:

W `.env` (lub zmiennych środowiskowych Railway):

```env
LOG_LEVEL=debug  # debug, info, notice, warning, error, critical, alert, emergency
LOG_CHANNEL=daily  # single, daily, stack
```

---

## 🔧 Troubleshooting

### Problem: Nie widzę logów Horizon

**Rozwiązanie:**
1. Sprawdź czy Horizon jest uruchomiony (przez Shell: `ps aux | grep horizon`)
2. Sprawdź logi aplikacji - może Horizon loguje do głównych logów
3. Sprawdź czy zmienna `QUEUE_CONNECTION=redis` jest ustawiona

### Problem: Horizon Dashboard nie działa

**Rozwiązanie:**
1. Sprawdź URL: `https://twoja-aplikacja.railway.app/horizon`
2. Sprawdź konfigurację autoryzacji w `horizon.php`
3. Sprawdź czy `HORIZON_AUTH_BYPASS_ENVS` zawiera twoje środowisko

### Problem: Nie widzę logów jobów

**Rozwiązanie:**
1. Sprawdź czy joby są dodawane do kolejki (przez Horizon Dashboard)
2. Sprawdź czy Redis działa (Railway Dashboard → Redis service)
3. Sprawdź logi aplikacji - joby mogą logować do głównych logów

---

## 📚 Powiązane Dokumenty

- [Railway Deployment Automation](./RAILWAY_DEPLOYMENT_AUTOMATION.md) - Automatyczny deploy
- [Manual Testing Guide](./MANUAL_TESTING_GUIDE.md) - Testowanie aplikacji
- [Horizon Configuration](../../api/config/horizon.php) - Konfiguracja Horizon

---

## 🎯 Podsumowanie

### Najprostszy Sposób:

1. ✅ Otwórz Railway Dashboard
2. ✅ Wybierz serwis (aplikacja lub Horizon)
3. ✅ Deployments → Logs
4. ✅ Zobacz logi w czasie rzeczywistym

### Alternatywy:

- **Shell:** Railway Dashboard → Shell → `tail -f storage/logs/laravel.log`
- **CLI:** `railway logs --service <nazwa> --follow`
- **Dashboard:** `https://twoja-aplikacja.railway.app/horizon`

---

**Ostatnia aktualizacja:** 2025-01-27

