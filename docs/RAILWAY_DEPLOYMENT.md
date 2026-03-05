# 🚂 Railway Deployment Guide

## Przegląd

Ten dokument opisuje prawidłowy proces wdrażania aplikacji MovieMind API na Railway.

---

## 📋 Wymagania

- Railway CLI zainstalowane i zalogowane
- Dostęp do projektu na Railway
- Git repository z tagami dla wersji staging/production

---

## 🔧 Konfiguracja Railway

### 1. Linkowanie projektu

```bash
# Sprawdź status Railway CLI
railway status

# Linkuj do projektu (jeśli jeszcze nie zlinkowany)
railway link

# Lista dostępnych serwisów
railway service list

# Linkuj do konkretnego serwisu (np. staging)
railway service link moviemind-api-staging
```

### 2. Zmienne środowiskowe

Wszystkie zmienne środowiskowe są zarządzane przez Railway Dashboard lub CLI:

```bash
# Lista zmiennych
railway variables

# Ustawienie zmiennej (przykład)
railway variables set AI_SERVICE=real
```

**Ważne zmienne środowiskowe:**
- `APP_ENV=staging` lub `production`
- `AI_SERVICE=real` (dla rzeczywistych odpowiedzi AI)
- `OPENAI_API_KEY=...`
- `TMDB_API_KEY=...`
- `DB_CONNECTION=pgsql`
- `DB_HOST` / `DATABASE_URL` – z serwisu Postgres (Reference w Variables). Dla wewnętrznego hosta (`*.railway.internal`) ustaw **`DB_SSLMODE=disable`** (unika błędów SSL).
- `REDIS_HOST=redis-xxx.railway.internal` (ustawiane automatycznie przez Railway)

### 3. Publiczny staging – wymagane zabezpieczenia

Staging ma odzwierciedlać produkcję i jest dostępny na publicznym adresie. **Nie** używaj bypassu auth na stagingu.

- **ADMIN_AUTH_BYPASS_ENVS** – musi być **puste** (nie wpisuj `staging`). W przeciwnym razie endpointy `/api/v1/admin/*` są dostępne bez tokenu.
- **HORIZON_AUTH_BYPASS_ENVS** – musi być **puste** (nie wpisuj `staging`). W przeciwnym razie dashboard `/horizon` jest dostępny bez logowania.

**Wymagane zmienne na publicznym stagingu:**

- **ADMIN_API_TOKEN** – silny, losowy token (np. 64 znaki). Używany w nagłówku `X-Admin-Token` lub `Authorization: Bearer <token>` przy wywołaniach Admin API.
- **HORIZON_BASIC_AUTH_PASSWORD** – silne hasło (min. 32 znaki). Logowanie do Horizon (username = email z listy).
- **HORIZON_ALLOWED_EMAILS** – adresy e-mail (po przecinku) uprawnione do dostępu do Horizon.
- **ADMIN_ALLOWED_EMAILS** – adresy e-mail uprawnione do Admin API (gdzie używane Basic Auth).
- **ADMIN_BASIC_AUTH_PASSWORD** – silne hasło dla Admin API (gdzie używane Basic Auth).

Wzoruj się na `env/staging.env.example` (zgodny z `env/production.env.example`, tylko APP_ENV, APP_URL i DB_DATABASE różne).

---

## 🚀 Proces wdrożenia

### Krok 1: Przygotowanie zmian

```bash
# Przełącz się na branch main
git checkout main
git pull origin main

# Sprawdź ostatni tag staging
git tag -l "staging-*" | sort -V | tail -1

# Utwórz nowy tag (np. staging-1.0.6)
git tag -a staging-1.0.6 -m "Release staging-1.0.6: Description"
git push origin staging-1.0.6
```

### Krok 2: Utworzenie GitHub Release

```bash
# Użyj GitHub CLI lub Web UI
gh release create staging-1.0.6 --title "Release staging-1.0.6" --notes "Release notes..."
```

### Krok 3: Wdrożenie na Railway

#### Opcja A: Automatyczne wdrożenie (przez Git push)

Railway automatycznie wdraża zmiany z `main` branch:

```bash
# Merge PR do main (jeśli jeszcze nie zmergowany)
# Railway automatycznie wykryje zmiany i uruchomi deployment
```

#### Opcja B: Manualne wdrożenie przez Railway CLI

```bash
# Linkuj do serwisu staging
railway service link moviemind-api-staging

# Wdróż z aktualnego katalogu
railway up
```

### Krok 4: Uruchomienie migracji

**⚠️ WAŻNE:** Migracje NIE są uruchamiane automatycznie podczas deploymentu. Musisz uruchomić je ręcznie.

#### Metoda 1: Railway Web UI (Najłatwiejsza)

1. Przejdź do Railway Dashboard: https://railway.app/dashboard
2. Wybierz projekt "MovieMind Api"
3. Wybierz serwis "moviemind-api-staging"
4. Przejdź do zakładki "Deployments"
5. Kliknij na najnowsze deployment (zielony status)
6. Kliknij "Shell" lub "View Logs"
7. W otwartym terminalu wykonaj:
   ```bash
   cd api
   php artisan migrate --force
   ```

#### Metoda 2: Railway CLI SSH (Zalecana)

**Uwaga:** `railway ssh` wykonuje komendy bezpośrednio w kontenerze, więc ma dostęp do wszystkich zmiennych środowiskowych i wewnętrznych hostname'ów Railway.

```bash
# Linkuj do serwisu (jeśli jeszcze nie zlinkowany)
railway service moviemind-api-staging

# Sprawdź working directory w kontenerze (zwykle /var/www/html)
railway ssh -s moviemind-api-staging "pwd"

# Uruchom migracje
railway ssh -s moviemind-api-staging "php artisan migrate --force"

# LUB dla czystej bazy danych (usuwa wszystkie tabele i uruchamia migracje od nowa)
railway ssh -s moviemind-api-staging "php artisan migrate:fresh --force"
```

**Jeśli `railway ssh` zwraca błąd "Device not configured" na macOS:**
- Sprawdź czy Railway CLI jest zaktualizowane: `railway --version`
- Spróbuj użyć pełnej ścieżki: `railway ssh -s moviemind-api-staging -e staging`
- Jeśli nadal nie działa, użyj Metody 1 (Railway Web UI)

#### Metoda 3: Railway CLI Run (NIE DZIAŁA dla migracji)

**⚠️ NIE UŻYWAJ tej metody dla migracji!**

`railway run` wykonuje komendy lokalnie, nie w kontenerze, więc nie ma dostępu do wewnętrznych hostname'ów Railway (np. `postgres-orjt.railway.internal`). Użyj Metody 1 lub 2.

### Krok 5: Weryfikacja wdrożenia

```bash
# Sprawdź status deploymentu
railway logs --service moviemind-api-staging --deployment latest

# Test endpointu
curl https://moviemind-api-staging.up.railway.app/api/v1/health/openai

# Test głównego endpointu
curl https://moviemind-api-staging.up.railway.app/
```

---

## 🔍 Rozwiązywanie problemów

### Problem: Błąd 500 przy endpointach

**Przyczyna:** Brakujące migracje lub niezgodność schematu bazy danych.

**Rozwiązanie:**
1. Uruchom migracje przez Railway Web UI Shell (Metoda 1)
2. Sprawdź logi: `railway logs --service moviemind-api-staging`
3. Sprawdź czy wszystkie tabele istnieją

### Problem: `railway run` nie może połączyć się z bazą danych

**Przyczyna:** `railway run` wykonuje komendy lokalnie, nie w kontenerze.

**Rozwiązanie:** Użyj Railway Web UI Shell lub `railway shell` zamiast `railway run`.

### Problem: Zmiany nie są wdrażane

**Rozwiązanie:**
1. Sprawdź czy zmiany są w `main` branch
2. Sprawdź status deploymentu w Railway Dashboard
3. Sprawdź logi build: `railway logs --service moviemind-api-staging --deployment latest`

### Problem: UUID vs BigInt w bazie danych

**Przyczyna:** Tabela `movies` może mieć `id` jako `bigint` zamiast `uuid`.

**Rozwiązanie:**
1. Sprawdź czy migracja `2025_12_18_165030_change_movies_table_to_uuid.php` została uruchomiona
2. Jeśli nie, uruchom migracje (patrz Krok 4)
3. Jeśli baza ma dane, użyj skryptu migracji danych (patrz `docs/plan/UUIDV7_MIGRATION_PLAN.md`)

---

## 📝 Checklist wdrożenia

- [ ] Utworzony tag (np. `staging-1.0.6`)
- [ ] Utworzony GitHub Release
- [ ] Zmiany zmergowane do `main`
- [ ] Railway deployment zakończony pomyślnie
- [ ] **Staging:** ADMIN_AUTH_BYPASS_ENVS i HORIZON_AUTH_BYPASS_ENVS puste; ustawione ADMIN_API_TOKEN, HORIZON_BASIC_AUTH_PASSWORD, HORIZON_ALLOWED_EMAILS
- [ ] Migracje uruchomione (przez Railway Web UI Shell)
- [ ] Endpoint `/api/v1/health/openai` działa
- [ ] Endpoint `/api/v1/movies/search` działa (test z parametrami)
- [ ] Logi nie pokazują błędów
- [ ] Testy manualne wykonane

---

## 🔄 Czyste wdrożenie (reset bazy danych)

Jeśli potrzebujesz czystej bazy danych:

1. **Uwaga:** To usunie wszystkie dane!

2. Przez Railway Web UI Shell:
   ```bash
   cd api
   php artisan migrate:fresh --force
   php artisan db:seed --force  # Tylko jeśli APP_ENV != staging/production
   ```

3. Lub ręcznie przez Railway Dashboard:
   - Usuń baze danych PostgreSQL
   - Utwórz nową bazę danych
   - Uruchom migracje (Krok 4)

---

## 📚 Dodatkowe zasoby

- [Railway CLI Documentation](https://docs.railway.app/develop/cli)
- [Railway Deployment Guide](https://docs.railway.app/deploy/deployments)
- [Laravel Deployment Best Practices](https://laravel.com/docs/deployment)

---

## 🎯 Przykład pełnego procesu wdrożenia

```bash
# 1. Przygotowanie
git checkout main
git pull origin main

# 2. Utworzenie tagu
git tag -a staging-1.0.6 -m "Release staging-1.0.6: Fixes and improvements"
git push origin staging-1.0.6

# 3. Utworzenie release
gh release create staging-1.0.6 --title "Release staging-1.0.6" --notes "..."

# 4. Weryfikacja deploymentu (automatyczny przez Railway)
railway logs --service moviemind-api-staging --deployment latest

# 5. Uruchomienie migracji (przez Railway Web UI Shell)
# Otwórz Railway Dashboard → Serwis → Shell → wykonaj:
# cd api && php artisan migrate --force

# 6. Testowanie
curl https://moviemind-api-staging.up.railway.app/api/v1/health/openai
curl https://moviemind-api-staging.up.railway.app/api/v1/movies/search?q=matrix
```

---

**Ostatnia aktualizacja:** 2025-12-28

