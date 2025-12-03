# Problem: Horizon Nie Przetwarza Jobów na Railway

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Horizon Dashboard działa, ale joby pozostają w statusie "pending" i nie są przetwarzane  
> **Kategoria:** journal  
> **Status:** 🔄 W trakcie rozwiązania

## 🎯 Problem

Horizon Dashboard jest dostępny na Railway (https://moviemind-api-staging.up.railway.app/horizon), ale joby są w statusie "failed" zamiast być przetwarzane pomyślnie.

**Status problemu:**
- ✅ Horizon Dashboard działa
- ✅ Horizon worker działa (próbuje przetwarzać joby)
- ❌ Joby się niepowodzą podczas przetwarzania

**Linki do failed jobs:**
- https://moviemind-api-staging.up.railway.app/horizon/failed
- Przykładowe joby: `41ed5ffa-7480-4cf2-9495-9e731abbce5e`, `8314cfdf-9533-4b7d-80db-69a3de2c3f5d`

## 🔍 Możliwe Przyczyny

### 1. Horizon Worker Nie Jest Uruchomiony

**Najczęstsza przyczyna:** Horizon worker nie działa na Railway.

**Jak sprawdzić:**
1. Railway Dashboard → serwis aplikacji → Shell
2. W shellu:
   ```bash
   ps aux | grep horizon
   ```
3. Jeśli nie widzisz procesu `php artisan horizon` → **Horizon nie działa**

### 2. Horizon Działa w Osobnym Serwisie, Ale Nie Jest Skonfigurowany

Na Railway może być potrzebny osobny serwis dla Horizon.

**Jak sprawdzić:**
1. Railway Dashboard → projekt MovieMind API
2. Sprawdź listę serwisów - czy jest serwis "Horizon"?
3. Jeśli nie ma → **trzeba dodać osobny serwis Horizon**

### 3. Problem z Redis Connection

Horizon potrzebuje Redis do działania.

**Jak sprawdzić:**
1. Railway Dashboard → zmienne środowiskowe
2. Sprawdź:
   - `QUEUE_CONNECTION=redis` ✅
   - `REDIS_HOST` ✅
   - `REDIS_PORT` ✅
3. W shellu kontenera:
   ```bash
   php artisan tinker
   >>> Redis::connection()->ping()
   ```
   Jeśli błąd → problem z połączeniem Redis

### 4. Problem z Konfiguracją Horizon

Sprawdź czy Horizon jest poprawnie skonfigurowany dla środowiska staging.

**Jak sprawdzić:**
1. W shellu kontenera:
   ```bash
   php artisan config:show horizon
   ```
2. Sprawdź czy `APP_ENV=staging` jest ustawione
3. Sprawdź konfigurację w `api/config/horizon.php`:
   - Dla staging: `maxProcesses` powinno być > 0
   - Connection: `redis`
   - Queue: `['default']`

---

## ✅ Rozwiązania

### Rozwiązanie 1: Uruchom Horizon w Tym Samym Kontenerze (Przez Supervisor)

Jeśli używasz stage `production` w Dockerfile, Horizon powinien działać przez Supervisor.

**Sprawdź supervisord.conf:**
```ini
[program:horizon]
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
```

**Sprawdź czy Supervisor uruchamia Horizon:**
1. Railway Dashboard → Shell
2. W shellu:
   ```bash
   supervisorctl status
   ```
3. Powinno pokazać:
   ```
   horizon                         RUNNING   pid 123
   ```

**Jeśli nie działa:**
```bash
# W shellu kontenera
supervisorctl start horizon
```

### Rozwiązanie 2: Dodaj Osobny Serwis Horizon na Railway (Rekomendowane)

**WAŻNE:** To NIE jest "druga aplikacja"! To jest osobny serwis, który uruchamia tylko worker Horizon (`php artisan horizon`). To znacznie lżejszy proces niż pełna aplikacja z Nginx/PHP-FPM.

**Różnica:**
- **Główny serwis aplikacji:** PHP-FPM + Nginx + aplikacja Laravel (obsługuje requesty HTTP)
- **Serwis Horizon:** Tylko `php artisan horizon` (przetwarza joby z kolejki, NIE obsługuje requestów HTTP)

#### Krok 1: Dodaj Nowy Serwis
1. Railway Dashboard → projekt MovieMind API
2. Kliknij **"+ New"**
3. Wybierz **"GitHub Repo"** (ten sam repo co aplikacja)
4. Nazwij serwis: `horizon-worker` lub `moviemind-horizon`

#### Krok 2: Skonfiguruj Serwis Horizon

**Settings → General:**
- **Root Directory**: `/`
- **Dockerfile Path**: `docker/php/Dockerfile`
- **Docker Build Context**: `/`
- **Build Command**: (puste - Dockerfile robi wszystko)
- **Start Command**: `php artisan horizon` ⚠️ **WAŻNE:** Tylko to, bez Nginx/Supervisor!

**Settings → Environment Variables:**
- Skopiuj wszystkie zmienne z głównego serwisu aplikacji:
  - `APP_ENV=staging`
  - `APP_KEY` (ten sam co w głównym serwisie)
  - `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - `REDIS_HOST`, `REDIS_PORT`
  - `QUEUE_CONNECTION=redis`
  - `OPENAI_API_KEY`
  - `OPENAI_MODEL`
  - Wszystkie inne zmienne aplikacji

**Settings → Service:**
- **Auto Deploy**: Enabled
- **Restart Policy**: Always

#### Krok 3: Weryfikacja

Po deploy serwisu Horizon:
1. Sprawdź logi serwisu Horizon
2. Powinny zawierać:
   ```
   Horizon started successfully
   Processing jobs...
   ```
3. W Horizon Dashboard joby powinny zacząć się przetwarzać

### Rozwiązanie 3: Uruchom Horizon Ręcznie (Tymczasowo)

Jeśli chcesz szybko przetestować:

1. Railway Dashboard → Shell w kontenerze aplikacji
2. W shellu:
   ```bash
   php artisan horizon
   ```
3. Pozostaw shell otwarty (Horizon będzie działać)
4. Joby powinny zacząć się przetwarzać

**Uwaga:** To jest rozwiązanie tymczasowe - po zamknięciu shellu Horizon przestanie działać.

### Rozwiązanie 4: Użyj Supervisor w Kontenerze Production

Jeśli używasz stage `production` w Dockerfile, Horizon powinien być uruchamiany przez Supervisor automatycznie.

**Sprawdź Dockerfile:**
- Stage `production` powinien kopiować `supervisord.conf`
- Start command powinien uruchamiać Supervisor
- Supervisor powinien uruchamiać Horizon

**Sprawdź czy to działa:**
1. Railway Dashboard → Shell
2. W shellu:
   ```bash
   supervisorctl status
   ps aux | grep horizon
   ```

---

## 🔧 Diagnostyka Krok po Kroku

### Krok 1: Sprawdź Czy Horizon Jest Uruchomiony

```bash
# W shellu kontenera
ps aux | grep horizon
```

**Oczekiwany wynik:**
```
app    123  0.0  1.0  php artisan horizon
```

**Jeśli brak:** Horizon nie jest uruchomiony.

### Krok 2: Sprawdź Czy Redis Działa

```bash
# W shellu kontenera
php artisan tinker
>>> Redis::connection()->ping()
```

**Oczekiwany wynik:** `"PONG"`

**Jeśli błąd:** Problem z połączeniem Redis.

### Krok 3: Sprawdź Konfigurację Queue

```bash
php artisan config:show queue
```

**Oczekiwany wynik:**
```php
'default' => 'redis',
'connections' => [
    'redis' => [...]
]
```

### Krok 4: Sprawdź Logi Horizon

```bash
# W shellu kontenera
tail -f storage/logs/laravel.log | grep -i horizon
```

**Lub w Railway Dashboard:**
- Serwis aplikacji → Deployments → Logs
- Szukaj logów Horizon

### Krok 5: Sprawdź Horizon Dashboard

1. Otwórz: https://moviemind-api-staging.up.railway.app/horizon
2. Sprawdź sekcję **"Monitoring"**
3. Czy widzisz aktywnych workers?
4. Czy widzisz metryki?

---

## 📋 Checklist Rozwiązywania Problemu

- [ ] Sprawdź czy Horizon jest uruchomiony (`ps aux | grep horizon`)
- [ ] Sprawdź czy Redis działa (`Redis::connection()->ping()`)
- [ ] Sprawdź `QUEUE_CONNECTION=redis` w zmiennych środowiskowych
- [ ] Sprawdź `APP_ENV` (powinno być `staging` lub `production`)
- [ ] Sprawdź logi aplikacji (szukaj błędów Horizon)
- [ ] Sprawdź Horizon Dashboard (czy są aktywni workers?)
- [ ] Sprawdź konfigurację Horizon (`php artisan config:show horizon`)
- [ ] Sprawdź czy Supervisor działa (`supervisorctl status`)
- [ ] Jeśli osobny serwis Horizon - sprawdź czy jest skonfigurowany

---

## 🎯 Rekomendowane Rozwiązanie

### Dla Staging/Production na Railway:

**Rekomendacja:** Dodaj osobny serwis Horizon na Railway.

**Dlaczego:**
- ✅ Osobne logi dla Horizon
- ✅ Możliwość skalowania niezależnie
- ✅ Łatwiejsze monitorowanie
- ✅ Nie blokuje głównego kontenera aplikacji

**Alternatywa:** Jeśli chcesz wszystko w jednym kontenerze, upewnij się że Supervisor uruchamia Horizon w stage `production`.

---

## 📚 Powiązane Dokumenty

- [Railway Horizon Logs](../reference/RAILWAY_HORIZON_LOGS.md) - Jak dostać się do logów
- [Railway Deployment Automation](../reference/RAILWAY_DEPLOYMENT_AUTOMATION.md) - Automatyczny deploy
- [Horizon Configuration](../../api/config/horizon.php) - Konfiguracja Horizon

---

## 🔍 Analiza Failed Jobs

### ✅ ROOT CAUSE ZIDENTYFIKOWANY (2025-01-27)

**Problem:** Permission denied dla `storage/logs/laravel.log`

```
UnexpectedValueException: The stream or file "/var/www/html/storage/logs/laravel.log" 
could not be opened in append mode: Failed to open stream: Permission denied
```

**Przyczyna:**
- Horizon worker uruchamia się jako użytkownik `app` (non-root)
- Katalog `storage/logs/` nie ma odpowiednich uprawnień do zapisu
- Job próbuje zapisać logi podczas wykonania, ale brak uprawnień powoduje błąd

**Failed Job:** `d6aa9031-bac8-4f99-a37a-67d508e6a3c3`
- Link: https://moviemind-api-staging.up.railway.app/horizon/failed/d6aa9031-bac8-4f99-a37a-67d508e6a3c3
- Exception: Permission denied przy próbie zapisu do `storage/logs/laravel.log`

### Inne Możliwe Przyczyny Failed Jobs:

1. **Permission denied dla storage/logs/** ⚠️ **AKTUALNY PROBLEM**
   - Horizon worker nie ma uprawnień do zapisu logów
   - Rozwiązanie: użyj logowania do stderr lub napraw uprawnienia

2. **Brak OPENAI_API_KEY lub nieprawidłowy klucz**
   - Sprawdź zmienne środowiskowe w Railway
   - Joby `RealGenerateMovieJob` wymagają klucza OpenAI

3. **Problem z połączeniem do bazy danych**
   - Joby próbują zapisać dane do PostgreSQL
   - Sprawdź zmienne DB_* w Railway

4. **Problem z Redis connection**
   - Horizon używa Redis do przechowywania metryk
   - Sprawdź REDIS_HOST, REDIS_PORT

5. **Timeout podczas wywołania OpenAI API**
   - Domyślny timeout: 120 sekund
   - Sprawdź logi dla szczegółów błędu

6. **Błąd walidacji danych AI**
   - Feature flag `hallucination_guard` może powodować błędy walidacji
   - Sprawdź logi dla szczegółów

### Jak Sprawdzić Szczegóły Błędu:

**Metoda 1: Horizon Dashboard**
1. Otwórz: https://moviemind-api-staging.up.railway.app/horizon/failed
2. Kliknij na konkretny failed job (np. `41ed5ffa-7480-4cf2-9495-9e731abbce5e`)
3. Zobaczysz:
   - Exception message
   - Stack trace
   - Payload joba

**Metoda 2: Logi w Railway**
1. Railway Dashboard → serwis aplikacji → Logs
2. Szukaj logów z błędami:
   ```
   RealGenerateMovieJob failed
   ```

**Metoda 3: Baza danych**
1. Railway Dashboard → Shell
2. W shellu:
   ```bash
   php artisan tinker
   >>> DB::table('failed_jobs')->orderBy('failed_at', 'desc')->first()
   ```
3. Sprawdź pole `exception` - zawiera pełny stack trace

---

## ✅ Rozwiązanie: Permission Denied dla storage/logs/

### Rozwiązanie 1: Użyj Logowania do stderr (Rekomendowane dla Railway)

Na Railway najlepszym rozwiązaniem jest logowanie do `stderr` zamiast do pliku. Logi trafią bezpośrednio do Railway Dashboard bez problemów z uprawnieniami.

#### Krok 1: Ustaw Zmienną Środowiskową

W Railway Dashboard → zmienne środowiskowe, dodaj:

```
LOG_CHANNEL=stderr
```

Lub jeśli chcesz użyć stack (który łączy kilka kanałów):

```
LOG_CHANNEL=stack
LOG_STACK=stderr
```

#### Krok 2: Rebuild i Redeploy

Po ustawieniu zmiennej środowiskowej:
1. Railway automatycznie zrobi redeploy
2. Albo zrób redeploy ręcznie

#### Krok 3: Weryfikacja

Po redeploy:
- Joby powinny zacząć się przetwarzać poprawnie
- Logi będą widoczne w Railway Dashboard → Logs
- Brak błędów "Permission denied"

**Zalety:**
- ✅ Brak problemów z uprawnieniami
- ✅ Logi widoczne bezpośrednio w Railway Dashboard
- ✅ Automatyczne log rotation (Railway zarządza)
- ✅ Nie zajmuje miejsca na dysku kontenera

### Rozwiązanie 2: Napraw Uprawnienia storage/logs/

Jeśli musisz używać logowania do pliku, napraw uprawnienia.

#### Opcja A: Przez Start Command (Jeśli osobny serwis Horizon)

W Railway Dashboard → serwis Horizon → Settings:

**Start Command:**
```bash
mkdir -p storage/logs && chmod -R 777 storage/logs && php artisan horizon
```

#### Opcja B: Przez Entrypoint Script

Stwórz wrapper script który ustawia uprawnienia przed uruchomieniem Horizon:

1. Stwórz plik `docker/php/horizon-entrypoint.sh`:
```bash
#!/bin/bash
set -e

# Ensure storage/logs exists and has permissions
mkdir -p storage/logs
chmod -R 777 storage/logs 2>/dev/null || true

# Start Horizon
exec php artisan horizon
```

2. W Dockerfile dodaj:
```dockerfile
COPY docker/php/horizon-entrypoint.sh /usr/local/bin/horizon-entrypoint.sh
RUN chmod +x /usr/local/bin/horizon-entrypoint.sh
```

3. W Railway → Start Command:
```bash
horizon-entrypoint.sh
```

### Rozwiązanie 3: Użyj Supervisor (Jeśli w jednym kontenerze)

Jeśli Horizon działa przez Supervisor w głównym kontenerze:

1. Sprawdź `supervisord.conf` - powinno mieć:
```ini
[program:horizon]
user=app
```

2. Upewnij się że entrypoint.sh ustawia uprawnienia dla użytkownika `app`:
```bash
chmod -R 777 storage/logs
```

### Rozwiązanie 4: Zmień Kanał Logowania w Konfiguracji (Tymczasowo)

Tymczasowo możesz zmienić kanał logowania w `api/config/logging.php`:

Zmień z:
```php
'default' => env('LOG_CHANNEL', 'stack'),
```

Na:
```php
'default' => env('LOG_CHANNEL', env('APP_ENV') === 'production' ? 'stderr' : 'stack'),
```

**Uwaga:** To wymaga zmiany kodu i redeploy.

---

## 🎯 Rekomendowane Rozwiązanie dla Railway

**Rozwiązanie 1: LOG_CHANNEL=stderr** ✅ **NAJLEPSZE**

**Dlaczego:**
- ✅ Najprostsze - tylko zmienna środowiskowa
- ✅ Brak problemów z uprawnieniami
- ✅ Logi widoczne w Railway Dashboard
- ✅ Nie wymaga zmian w kodzie
- ✅ Automatyczne zarządzanie logami przez Railway

**Jak zastosować:**
1. Railway Dashboard → zmienne środowiskowe
2. Dodaj: `LOG_CHANNEL=stderr`
3. Redeploy automatyczny
4. Gotowe! ✅

---

## 🔄 Aktualizacje

### 2025-01-27 - Problem Zidentyfikowany (Aktualizacja)

- Horizon Dashboard działa ✅
- Horizon worker działa ✅ (próbuje przetwarzać joby)
- Joby się niepowodzą ❌ (failed, nie pending)

**Status:** Horizon działa, ale joby kończą się błędem podczas przetwarzania.

**Root Cause:** Permission denied dla `storage/logs/laravel.log`
- Horizon worker nie ma uprawnień do zapisu logów
- Exception: `UnexpectedValueException: Permission denied`
- Failed Job ID: `d6aa9031-bac8-4f99-a37a-67d508e6a3c3`

**Następne kroki:**
1. ✅ Sprawdź szczegóły błędów w Horizon Dashboard (Failed Jobs) - **DONE**
2. ✅ Zidentyfikowano root cause: Permission denied dla storage/logs/ - **DONE**
3. ⏳ Napraw uprawnienia lub użyj logowania do stderr (patrz rozwiązanie poniżej)
4. ⏳ Zweryfikuj czy rozwiązanie działa

### 2025-01-27 - Aktualizacja: Wyjaśnienie Rozwiązania 1

**Odpowiedź na pytanie:** Czy "Rozwiązanie 1" to sugeruje postawienie drugiej aplikacji?

**NIE!** Osobny serwis Horizon to:
- ✅ Tylko worker (`php artisan horizon`)
- ✅ NIE uruchamia Nginx, PHP-FPM ani web server
- ✅ NIE obsługuje requestów HTTP
- ✅ Jest znacznie lżejszy niż pełna aplikacja

**Główny serwis:** PHP-FPM + Nginx + Laravel (obsługuje API)
**Serwis Horizon:** Tylko `php artisan horizon` (przetwarza joby)

**Zalety:**
- Osobne logi
- Możliwość skalowania niezależnie
- Nie blokuje głównego kontenera

---

**Ostatnia aktualizacja:** 2025-01-27 (Aktualizacja: Failed Jobs Analysis)

