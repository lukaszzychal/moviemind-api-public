# Railway Deployment - Automatyzacja Procesu Deploy

> **Data utworzenia:** 2025-01-27  
> **Kontekst:** Wyjaśnienie automatycznego procesu deploy na Railway  
> **Kategoria:** reference

## 🎯 Cel

Ten dokument wyjaśnia, jak działa automatyczny proces deploy na Railway dla MovieMind API - co dzieje się automatycznie i co jest wymagane ręcznie.

---

## ✅ Co Dzieje Się Automatycznie

### 1. 🔨 Build Time (Podczas Budowania Obrazu Docker)

Railway automatycznie wykrywa `Dockerfile` i buduje obraz przy każdym deploymencie (push do repozytorium).

#### Etap 1: Stage "base" - Instalacja Composera
```dockerfile
# Composer jest instalowany w base stage
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer
```

#### Etap 2: Stage "builder" - Instalacja Zależności
```dockerfile
# Composer dependencies są instalowane podczas build
COPY api/composer.json api/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
```

**Co jest instalowane automatycznie:**
- ✅ Wszystkie zależności PHP z `composer.json`
- ✅ Autoloader jest optymalizowany
- ✅ Vendor directory jest kopiowany do finalnego obrazu

#### Etap 3: Stage "production" - Przygotowanie Aplikacji
```dockerfile
# Kopiowanie vendor z builder stage
COPY --from=builder --chown=app:app /var/www/html/vendor ./vendor

# Kopiowanie aplikacji
COPY --chown=app:app api/ ./

# Optymalizacja autoloadera
RUN composer dump-autoload --optimize
```

---

### 2. 🚀 Runtime (Przy Starcie Kontenera)

Kontener uruchamia się z skryptem `entrypoint.sh`, który automatycznie wykonuje wszystkie operacje setupowe.

#### Automatyczne Akcje przy Starcie Kontenera:

##### 1. **Oczekiwanie na Bazę Danych** (automatyczne)
```bash
# Czeka maksymalnie 30 sekund na dostępność bazy danych
while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    # Sprawdza połączenie z bazą danych
done
```

##### 2. **Generowanie APP_KEY** (automatyczne, jeśli brakuje)
```bash
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi
```

##### 3. **Cache'owanie Konfiguracji** (automatyczne, tylko production)
```bash
# Tylko jeśli APP_ENV != local/dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

##### 4. **Migracje Bazy Danych** (automatyczne, bezpieczne)
```bash
# Automatycznie uruchamia pending migrations
# Bezpieczne - nie usuwa danych!
php artisan migrate --force
```

**Można wyłączyć** ustawiając zmienną środowiskową:
```
RUN_MIGRATIONS=false
```

##### 5. **Optymalizacja Aplikacji** (automatyczne, tylko production)
```bash
php artisan optimize
```

---

## 🔄 Pełny Workflow Deploy na Railway

### Krok 1: Push do Repozytorium
```bash
git push origin main
```

### Krok 2: Railway Wykrywa Zmiany (automatyczne)
- Railway automatycznie wykrywa push
- Rozpoczyna build jeśli skonfigurowane auto-deploy

### Krok 3: Build Obrazu Docker (automatyczne)
Railway automatycznie:
1. ✅ Wykrywa `Dockerfile` w repozytorium
2. ✅ Buduje obraz używając `docker/php/Dockerfile`
3. ✅ Używa stage `production` (domyślnie lub przez konfigurację)
4. ✅ Instaluje Composer i zależności podczas build
5. ✅ Kopiuje aplikację i vendor do obrazu

**Co jest potrzebne w Railway:**
- Ustaw **Root Directory**: `/` (lub katalog główny projektu)
- Ustaw **Dockerfile Path**: `docker/php/Dockerfile` (jeśli Railway nie wykryje automatycznie)
- Ustaw **Build Command**: (pusty - Dockerfile robi wszystko)
- Ustaw **Start Command**: (pusty - Dockerfile ma CMD)

### Krok 4: Deploy Kontenera (automatyczne)
Railway automatycznie:
1. ✅ Tworzy kontener z zbudowanego obrazu
2. ✅ Ustawia zmienne środowiskowe (z Railway Dashboard)
3. ✅ Uruchamia kontener

### Krok 5: Start Kontenera (automatyczne)
Kontener automatycznie:
1. ✅ Uruchamia `start.sh`
2. ✅ `start.sh` uruchamia `entrypoint.sh`
3. ✅ `entrypoint.sh` wykonuje wszystkie operacje setupowe:
   - Czeka na bazę danych
   - Generuje APP_KEY (jeśli brakuje)
   - Cache'uje konfigurację
   - Uruchamia migracje
   - Optymalizuje aplikację
4. ✅ Uruchamia Supervisor (PHP-FPM + Nginx)

---

## 📋 Co Musisz Zrobić Ręcznie (Tylko Raz)

### 1. Konfiguracja Railway Projektu (pierwszy raz)

#### A. Połącz Repozytorium z Railway:
1. Otwórz [Railway Dashboard](https://railway.app)
2. Kliknij **"New Project"**
3. Wybierz **"Deploy from GitHub repo"**
4. Wybierz repozytorium `moviemind-api-public`

#### B. Dodaj Serwis PostgreSQL:
1. W projekcie kliknij **"+ New"**
2. Wybierz **"Database" → "Add PostgreSQL"**
3. Railway automatycznie stworzy bazę danych i ustawi zmienne środowiskowe

#### C. Konfiguracja Serwisu Aplikacji:

**Settings → General:**
- **Root Directory**: `/` (lub zostaw puste jeśli automatyczne wykrywanie działa)
- **Build Command**: (puste - Dockerfile robi wszystko)
- **Start Command**: (puste - Dockerfile ma CMD)

**Settings → Dockerfile:**
- **Dockerfile Path**: `docker/php/Dockerfile`
- **Docker Build Context**: `/` (root projektu)

#### D. Ustaw Zmienne Środowiskowe:

Railway automatycznie ustawi zmienne dla PostgreSQL (z serwisu bazy danych):
- `DATABASE_URL`
- `PGHOST`
- `PGPORT`
- `PGDATABASE`
- `PGUSER`
- `PGPASSWORD`

**Musisz ręcznie dodać:**

| Zmienna | Wartość | Opis |
|---------|---------|------|
| `APP_ENV` | `staging` lub `production` | Środowisko aplikacji |
| `APP_DEBUG` | `0` | Wyłącz debug w produkcji |
| `APP_KEY` | (puste lub wygenerowane) | Klucz aplikacji (może być wygenerowany automatycznie) |
| `OPENAI_API_KEY` | `sk-...` | Klucz API OpenAI |
| `OPENAI_MODEL` | `gpt-4o-mini` | Model OpenAI |
| `AI_SERVICE` | `real` lub `mock` | Serwis AI |
| `QUEUE_CONNECTION` | `redis` | Połączenie kolejki |
| `REDIS_HOST` | (z Railway Redis service) | Host Redis |
| `REDIS_PORT` | (z Railway Redis service) | Port Redis |

**Wskazówka:** Railway automatycznie łączy zmienne środowiskowe między serwisami. Jeśli dodasz Redis service, zmienne Redis będą dostępne automatycznie.

---

## 🔍 Jak Sprawdzić Co Się Dzieje

### 1. Logi Build (w Railway Dashboard):
1. Otwórz serwis aplikacji
2. Kliknij zakładkę **"Deployments"**
3. Wybierz deployment
4. Zobacz logi build

### 2. Logi Runtime (w Railway Dashboard):
1. Otwórz serwis aplikacji
2. Kliknij zakładkę **"Deployments"**
3. Wybierz deployment
4. Kliknij **"Logs"** - zobaczysz logi z `entrypoint.sh`:
   ```
   🚀 MovieMind API - Production Entrypoint
   ⏳ Waiting for database connection...
   ✅ Database connection established
   📁 Ensuring storage directories exist...
   ✅ APP_KEY is set
   📦 Caching configuration for production...
   🔄 Running database migrations...
   ✅ Migrations completed
   ```

### 3. Sprawdzenie Statusu (przez Shell):
1. Otwórz serwis aplikacji
2. Kliknij **"Deployments"**
3. Kliknij **"Shell"**
4. W shellu kontenera:
   ```bash
   php artisan migrate:status
   php artisan config:show
   ```

---

## ⚙️ Konfiguracja Railway

### Przykładowa Konfiguracja w Railway Dashboard:

**Settings → General:**
```
Root Directory: /
Build Command: (puste)
Start Command: (puste)
```

**Settings → Dockerfile:**
```
Dockerfile Path: docker/php/Dockerfile
Docker Build Context: /
```

**Settings → Environment Variables:**
```
APP_ENV=staging
APP_DEBUG=0
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
AI_SERVICE=real
QUEUE_CONNECTION=redis
```

**Settings → Service:**
```
Auto Deploy: Enabled (automatyczny deploy przy push)
```

---

## 🔧 Zaawansowane: Niestandardowa Konfiguracja

### Wyłączenie Automatycznych Migracji:

Ustaw zmienną środowiskową w Railway:
```
RUN_MIGRATIONS=false
```

### Zmiana Środowiska (local/dev):

Ustaw zmienne środowiskowe:
```
APP_ENV=local
APP_DEBUG=1
```

To wyłączy cache'owanie i optymalizację (używa live reload).

---

## ❓ FAQ

### Q: Czy muszę ręcznie instalować Composer?
**A:** Nie! Composer jest instalowany automatycznie podczas build obrazu Docker.

### Q: Czy muszę ręcznie uruchamiać `composer install`?
**A:** Nie! Zależności są instalowane automatycznie podczas build (stage "builder").

### Q: Czy muszę ręcznie uruchamiać migracje?
**A:** Nie! Migracje są uruchamiane automatycznie przy starcie kontenera przez `entrypoint.sh`.

### Q: Czy muszę ręcznie generować APP_KEY?
**A:** Nie! `entrypoint.sh` automatycznie generuje APP_KEY jeśli nie jest ustawiony.

### Q: Czy muszę ręcznie cache'ować konfigurację?
**A:** Nie! Cache'owanie jest wykonywane automatycznie dla production/staging.

### Q: Co muszę zrobić ręcznie?
**A:** Tylko:
1. ✅ Skonfigurować Railway projekt (pierwszy raz)
2. ✅ Dodać PostgreSQL service (pierwszy raz)
3. ✅ Ustawić zmienne środowiskowe (pierwszy raz, potem tylko gdy zmieniasz)
4. ✅ Push do repozytorium - reszta jest automatyczna!

### Q: Jak często muszę robić coś ręcznie?
**A:** Prawie nigdy! Po początkowej konfiguracji, wystarczy:
- Push do repozytorium → Railway automatycznie buduje i deployuje
- Zmiana zmiennych środowiskowych w Railway Dashboard (jeśli potrzebne)

---

## 📚 Powiązane Dokumenty

- [Deployment Setup](./DEPLOYMENT_SETUP.md) - Szczegóły entrypoint.sh
- [Railway Database Cleanup](./RAILWAY_DATABASE_CLEANUP.md) - Jak wyczyścić bazę danych
- [Dockerfile](../technical/DOCKERFILE_ANALYSIS.md) - Analiza Dockerfile (jeśli istnieje)

---

## 🎯 Podsumowanie

### ✅ Wszystko Automatyczne:

1. **Build Time:**
   - ✅ Instalacja Composera
   - ✅ Instalacja zależności PHP
   - ✅ Optymalizacja autoloadera

2. **Runtime (Start Kontenera):**
   - ✅ Czekanie na bazę danych
   - ✅ Generowanie APP_KEY
   - ✅ Cache'owanie konfiguracji
   - ✅ Migracje bazy danych
   - ✅ Optymalizacja aplikacji

### 🔧 Ręczna Konfiguracja (tylko raz):

1. ✅ Konfiguracja projektu Railway
2. ✅ Dodanie PostgreSQL service
3. ✅ Ustawienie zmiennych środowiskowych

**Po konfiguracji: Wystarczy push do repozytorium - reszta jest automatyczna! 🚀**

---

**Ostatnia aktualizacja:** 2025-01-27

