# Problem z Uprawnieniami Storage na Railway Staging

> **Data utworzenia:** 2025-11-06  
> **Kontekst:** Błąd 500 na endpoint `/up` w Railway Staging Environment  
> **Kategoria:** journal  
> **Status:** 🔄 W trakcie rozwiązania

## 📍 Nazewnictwo

**Railway Staging URL:** `https://moviemind-api-staging.up.railway.app`  
**Zmienna środowiskowa:** `RAILWAY_STAGING_URL` (proponowana)  
**Nazwa w dokumentacji:** Railway Staging Environment / Railway Staging

## 🎯 Problem

Endpoint `/up` zwraca błąd 500 z komunikatem:
```
file_put_contents(/var/www/html/storage/framework/views/...): 
Failed to open stream: Permission denied
```

## 🔍 Analiza

### Obserwacje:
- ✅ Endpoint `/api/v1/movies` działa poprawnie (200 OK)
- ❌ Endpoint `/up` zwraca 500 (Permission denied)
- ❌ Problem dotyczy katalogu `storage/framework/views/`

### Przyczyna:
Katalog `storage/framework/views/` nie ma odpowiednich uprawnień do zapisu przez użytkownika `app` (non-root user w kontenerze).

## ✅ Rozwiązanie

### 1. Dodano tworzenie katalogów w entrypoint.sh

Dodano sekcję w `docker/php/entrypoint.sh` przed cache'owaniem konfiguracji:

```bash
# Ensure storage directories exist and have correct permissions
echo "📁 Ensuring storage directories exist..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
mkdir -p bootstrap/cache
chown -R app:app storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache
echo "✅ Storage directories ready"
```

### 2. Weryfikacja w Dockerfile

Dockerfile już zawiera:
- Tworzenie katalogów storage w build time
- Ustawianie uprawnień `chmod -R 775 storage`
- Ustawianie właściciela `chown -R app:app`

### 3. Weryfikacja w start.sh

`start.sh` również tworzy katalogi i ustawia uprawnienia przed uruchomieniem Supervisor.

## 🔄 Workflow Naprawy

1. **Build time (Dockerfile):**
   - Tworzy katalogi storage
   - Ustawia uprawnienia 775
   - Ustawia właściciela app:app

2. **Runtime (entrypoint.sh):**
   - **NOWE:** Tworzy katalogi jeśli nie istnieją
   - **NOWE:** Ustawia uprawnienia przed cache'owaniem
   - Uruchamia migracje
   - Cache'uje konfigurację

3. **Runtime (start.sh):**
   - Tworzy katalogi jeśli nie istnieją
   - Ustawia uprawnienia
   - Uruchamia Supervisor

## 📋 Testowanie

Po wdrożeniu poprawki:

```bash
# Railway Staging URL
RAILWAY_STAGING_URL="https://moviemind-api-staging.up.railway.app"

# Test root endpoint (welcome payload)
curl ${RAILWAY_STAGING_URL}/
# Oczekiwany wynik: JSON z informacjami o API (200 OK)

# Test healthcheck
curl ${RAILWAY_STAGING_URL}/up
# Oczekiwany wynik: {"status":"ok"} lub podobny (200 OK)

# Test API endpoint
curl ${RAILWAY_STAGING_URL}/api/v1/movies
# Oczekiwany wynik: {"data":[]} (200 OK)
```

**Uwaga:** URL `moviemind-api-staging.up.railway.app` jest automatycznie generowany przez Railway. W przyszłości można skonfigurować własną domenę (np. `staging-api.moviemind.com`).

## 🔗 Powiązane Dokumenty

- [Deployment Setup](../reference/DEPLOYMENT_SETUP.md) - Dokumentacja deploymentu
- [Docker Optimization](../reference/DOCKER_OPTIMIZATION.md) - Optymalizacje Docker
- [Entrypoint Script](../../../docker/php/entrypoint.sh) - Skrypt entrypoint

## 📌 Notatki

- Problem występuje tylko na **Railway Staging Environment**
- Lokalnie działa poprawnie (prawdopodobnie inny user/permissions)
- Rozwiązanie: Dodanie tworzenia katalogów w entrypoint.sh przed cache'owaniem
- **Railway Staging URL:** `https://moviemind-api-staging.up.railway.app`

## 🔄 Aktualizacja 2025-11-06 (2)

### Problem nadal występuje:
- Endpoint `/` nadal zwraca 500 (Permission denied)
- Błąd: `file_put_contents(/var/www/html/storage/framework/views/...): Permission denied`

### Dodatkowe zmiany:
1. **Sprawdzanie uprawnień root w entrypoint.sh:**
   - Sprawdzanie czy skrypt jest uruchamiany jako root przed `chown`
   - Fallback na `chmod 777` jeśli `775` nie działa
   - Lepsze logowanie statusu uprawnień

2. **Możliwe przyczyny:**
   - Entrypoint.sh może być uruchamiany jako non-root user
   - `chown` wymaga uprawnień root
   - Katalogi mogą być tworzone z niewłaściwymi uprawnieniami

### Następne kroki:
- Sprawdzić logi Railway po wdrożeniu
- Zweryfikować czy entrypoint.sh jest uruchamiany jako root
- Rozważyć alternatywne rozwiązanie (np. volume mounts z odpowiednimi uprawnieniami)

## 🔄 Aktualizacja 2025-11-06 (3) - ROZWIĄZANIE

### Rozwiązanie problemu z endpointem `/`:
- **Zmieniono route `/` z widoku Blade na JSON response**
- Endpoint `/` teraz zwraca welcome payload w formacie JSON
- Eliminuje potrzebę kompilacji widoku `welcome.blade.php`
- Rozwiązuje problem Permission denied dla `storage/framework/views/`

### Welcome Payload zawiera:
- `name`: Nazwa API (MovieMind API)
- `version`: Wersja API (1.0.0)
- `status`: Status API (ok)
- `environment`: Środowisko (staging/production)
- `endpoints`: Lista dostępnych endpointów
- `documentation`: Linki do dokumentacji (OpenAPI, Postman, Insomnia)

### Status:
- ✅ Endpoint `/` zwraca teraz JSON (200 OK) zamiast 500
- ✅ Endpoint `/api/v1/movies` działa poprawnie (200 OK)
- ✅ Problem z uprawnieniami widoków rozwiązany przez zmianę route

### Nowy URL:
- **Railway Staging:** `https://moviemind-api-staging.up.railway.app`

---

**Ostatnia aktualizacja:** 2025-11-06 (3) - Problem rozwiązany ✅

