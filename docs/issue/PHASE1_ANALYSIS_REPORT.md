# Raport z analizy Fazy 1: Analiza i przygotowanie

**Data:** 2026-01-21  
**Status:** ✅ ZAKOŃCZONA  
**Czas:** ~2h

---

## 📋 1.1 Inwentaryzacja komponentów RapidAPI

### Pliki do usunięcia:

#### Middleware:
- ✅ `api/app/Http/Middleware/RapidApiAuth.php` - 114 linii
- ✅ `api/app/Http/Middleware/RapidApiHeaders.php` - 88 linii

#### Services:
- ✅ `api/app/Services/RapidApiService.php` - 152 linie

#### Configuration:
- ✅ `api/config/rapidapi.php` - 104 linie

#### Tests:
- ✅ `api/tests/Feature/RapidApiHeadersTest.php` - 164 linie
- ✅ `api/tests/Unit/Services/RapidApiServiceTest.php` - 195 linii

### Pliki do modyfikacji:

#### Bootstrap:
- ⚠️ `api/bootstrap/app.php` (linie 26-27):
  - Usunąć aliasy: `'rapidapi.auth'` i `'rapidapi.headers'`

#### Controllers:
- ⚠️ `api/app/Http/Controllers/Admin/BillingWebhookController.php`:
  - Usunąć obsługę RapidAPI webhooks (linie 15, 31, 80, 183, 196, 198, 202, 243, 368, 375, 376, 389)
  - Zachować strukturę dla przyszłych webhooków (Stripe/PayPal)

#### Services:
- ⚠️ `api/app/Services/WebhookService.php`:
  - Usunąć logikę RapidAPI (linie 160, 171, 175, 176, 193-248)
  - Zachować strukturę dla innych źródeł webhooków

#### Models:
- ⚠️ `api/app/Models/Subscription.php`:
  - Pole `rapidapi_user_id` (linia 17, 33) - oznaczyć jako deprecated lub usunąć
  - Komentarz w PHPDoc (linia 13) - zaktualizować

#### Migrations:
- ⚠️ `api/database/migrations/2025_12_25_160728_create_subscriptions_table.php`:
  - Pole `rapidapi_user_id` (linia 14) - utworzyć migrację do usunięcia lub oznaczenia jako deprecated
  - Indeks (linia 25) - usunąć jeśli usuwamy pole

#### Tests:
- ⚠️ `api/tests/Feature/BillingWebhooksTest.php`:
  - Usunąć testy RapidAPI webhooks
  - Zachować testy dla innych źródeł

- ⚠️ `api/tests/Feature/PlanBasedRateLimitTest.php`:
  - Usunąć referencje do `rapidapi.auth` middleware (linia 27)
  - Zaktualizować na `api.key.auth` lub podobne

- ⚠️ `api/tests/Feature/ApiKeyAuthenticationTest.php`:
  - Usunąć referencje do `rapidapi.auth` (linia 24, 42, 48, 72, 90, 108, 127, 141, 152, 162, 171)
  - Zaktualizować na standardowe middleware autoryzacji

- ⚠️ `api/tests/Unit/Services/WebhookServiceTest.php`:
  - Usunąć testy RapidAPI (linie 35, 46, 57, 66, 85, 106, 128, 150, 159, 168, 189)

- ⚠️ `api/tests/Unit/Jobs/RetryWebhookJobTest.php`:
  - Usunąć testy RapidAPI (linie 30, 33, 68, 89)

### Dokumentacja do usunięcia:

- ✅ `docs/RAPIDAPI_PRICING.md`
- ✅ `docs/RAPIDAPI_SETUP.md`
- ✅ `docs/RAPIDAPI_WEBHOOKS.md`
- ✅ `docs/issue/RAPIDAPI_INTEGRATION_PLAN.md`

### Dokumentacja do modyfikacji:

- ⚠️ `docs/openapi.yaml`:
  - Usunąć sekcję "RapidAPI Integration" (linie 23-34)
  - Usunąć header `RapidAPIKey` (linie 1760-1769)

- ⚠️ `docs/README.md` - sprawdzić referencje do RapidAPI

- ⚠️ `docs/en/MovieMind-Development-Roadmap.md` - usunąć RapidAPI z roadmap

- ⚠️ `docs/pl/MovieMind-Development-Roadmap.md` - usunąć RapidAPI z roadmap

- ⚠️ `docs/issue/en/TASKS.md` - zaktualizować zadania RapidAPI

- ⚠️ `docs/issue/pl/TASKS.md` - zaktualizować zadania RapidAPI

- ⚠️ `docs/business/WEBHOOK_SYSTEM_BUSINESS.md` - usunąć referencje do RapidAPI

- ⚠️ `docs/knowledge/technical/WEBHOOK_SYSTEM.md` - usunąć referencje do RapidAPI

- ⚠️ `docs/qa/WEBHOOK_SYSTEM_*.md` - usunąć referencje do RapidAPI

- ⚠️ `docs/knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md` - usunąć referencje do RapidAPI

- ⚠️ `docs/knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md` - zaktualizować (usunąć sekcje o RapidAPI)

### Konfiguracja:

- ⚠️ `.env.example` - usunąć zmienne:
  - `RAPIDAPI_PROXY_SECRET`
  - `RAPIDAPI_VERIFY_PROXY_SECRET`
  - `RAPIDAPI_LOG_REQUESTS`
  - `RAPIDAPI_WEBHOOK_SECRET`
  - `RAPIDAPI_VERIFY_WEBHOOK_SIGNATURE`

### Routes:

- ✅ Brak dedykowanych route'ów dla RapidAPI w `api/routes/api.php`
- ⚠️ Sprawdzić czy middleware `rapidapi.auth` i `rapidapi.headers` są używane w route'ach

---

## 🔍 1.2 Analiza integracji TMDB/TVmaze

### TMDB - Obecna implementacja:

#### Pliki:
- ✅ `api/app/Services/TmdbVerificationService.php` - 1634 linie
- ✅ `api/app/Services/TmdbMovieCreationService.php`
- ✅ `api/app/Services/TmdbTvShowCreationService.php`
- ✅ `api/app/Services/TmdbTvSeriesCreationService.php`
- ✅ `api/app/Features/tmdb_verification.php` - feature flag
- ✅ `api/config/pennant.php` - definicja flagi `tmdb_verification`
- ✅ `api/composer.json` - zależność `lukaszzychal/tmdb-client-php`

#### Użycie w kodzie:
- ✅ `api/app/Jobs/RealGenerateMovieJob.php` - używa TMDB do weryfikacji
- ✅ `api/app/Jobs/RealGenerateTvShowJob.php` - używa TMDB
- ✅ `api/app/Jobs/RealGenerateTvSeriesJob.php` - używa TMDB
- ✅ `api/app/Actions/QueueMovieGenerationAction.php` - przekazuje dane TMDB
- ✅ `api/app/Actions/QueueTvShowGenerationAction.php`
- ✅ `api/app/Actions/QueueTvSeriesGenerationAction.php`
- ✅ `api/app/Http/Controllers/Api/HealthController.php` - endpoint `/api/v1/health/tmdb`

#### Migracje:
- ✅ `api/database/migrations/2025_12_17_020001_create_tmdb_snapshots_table.php`
- ✅ `api/database/migrations/2025_12_18_165032_change_tmdb_snapshots_table_to_uuid.php`
- ✅ `api/database/migrations/2025_12_17_220207_add_tmdb_id_to_people_table.php`
- ✅ `api/database/migrations/2025_12_17_220440_add_tmdb_id_to_movies_table.php`

#### Modele:
- ✅ `api/app/Models/TmdbSnapshot.php`

#### Testy:
- ✅ `api/tests/Unit/Services/TmdbVerificationServiceTest.php`
- ✅ `api/tests/Feature/TmdbHealthCheckTest.php`

### TVmaze - Status:

- ❌ **Brak implementacji** - TVmaze nie jest zaimplementowane
- ✅ **Plan:** Utworzyć pełną implementację zgodnie z planem migracji

### Miejsca wymagające dokumentacji licencyjnej:

1. **Komentarze w kodzie:**
   - `api/app/Services/TmdbVerificationService.php` - dodać komentarz o licencji
   - `api/app/Jobs/RealGenerateMovieJob.php` - dodać komentarz
   - `api/app/Jobs/RealGenerateTvShowJob.php` - dodać komentarz
   - `api/app/Jobs/RealGenerateTvSeriesJob.php` - dodać komentarz

2. **Dokumentacja:**
   - Utworzyć `docs/LEGAL_TMDB_LICENSE.md`
   - Zaktualizować `README.md` - sekcja o licencji TMDB
   - Zaktualizować `docs/knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md`

3. **Atrybucja w odpowiedziach API:**
   - Sprawdzić czy wymagane (zgodnie z planem)

---

## 💳 1.3 Analiza systemu subskrypcji

### Modele:

- ✅ `api/app/Models/Subscription.php` - zawiera `rapidapi_user_id`
- ✅ `api/app/Models/SubscriptionPlan.php` - plany (Free, Pro, Enterprise)
- ✅ `api/app/Models/ApiKey.php` - klucze API powiązane z planami

### Middleware rate limiting:

- ✅ `api/app/Http/Middleware/PlanBasedRateLimit.php` - używa planu z API key
- ⚠️ Sprawdzić czy zależy od RapidAPI headers

### Zależności od RapidAPI:

1. **Model Subscription:**
   - Pole `rapidapi_user_id` (nullable) - do usunięcia lub oznaczenia jako deprecated
   - Komentarz w PHPDoc wskazuje na RapidAPI

2. **WebhookService:**
   - Metody `processSubscriptionCreated()` i `processSubscriptionUpdated()` używają `RapidApiService`
   - Logika specyficzna dla RapidAPI (linie 160-248)

3. **BillingWebhookController:**
   - Cały controller jest dedykowany dla RapidAPI webhooks
   - Komentarz w PHPDoc (linia 15)
   - Metoda `validateSignature()` używa `config('rapidapi.webhook_secret')`

4. **BillingService:**
   - Sprawdzić czy `createSubscription()` używa `rapidapi_user_id`

### Alternatywne źródło subskrypcji:

- ✅ **Lokalne API Keys** - już zaimplementowane
- ✅ Modele `ApiKey` i `SubscriptionPlan` są gotowe
- ✅ Middleware `PlanBasedRateLimit` używa planu z API key
- ⚠️ Wymaga usunięcia zależności od RapidAPI headers

---

## 📚 1.4 Inwentaryzacja dokumentacji

### Dokumenty do usunięcia:

#### RapidAPI:
- ✅ `docs/RAPIDAPI_PRICING.md`
- ✅ `docs/RAPIDAPI_SETUP.md`
- ✅ `docs/RAPIDAPI_WEBHOOKS.md`
- ✅ `docs/issue/RAPIDAPI_INTEGRATION_PLAN.md`

### Dokumenty do modyfikacji:

#### Usunięcie referencji do RapidAPI:
- ⚠️ `docs/openapi.yaml`
- ⚠️ `docs/README.md`
- ⚠️ `docs/en/MovieMind-Development-Roadmap.md`
- ⚠️ `docs/pl/MovieMind-Development-Roadmap.md`
- ⚠️ `docs/issue/en/TASKS.md`
- ⚠️ `docs/issue/pl/TASKS.md`
- ⚠️ `docs/business/WEBHOOK_SYSTEM_BUSINESS.md`
- ⚠️ `docs/knowledge/technical/WEBHOOK_SYSTEM.md`
- ⚠️ `docs/qa/WEBHOOK_SYSTEM_*.md` (3 pliki)
- ⚠️ `docs/knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md`
- ⚠️ `docs/knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md`

### Dokumenty do utworzenia:

#### Licencje:
- ⚠️ `docs/LEGAL_TMDB_LICENSE.md` - wymagania licencji komercyjnej TMDB

#### Portfolio vs Produkcja:
- ⚠️ `docs/DEPLOYMENT_PRODUCTION.md` - wymagania dla produkcji

### Struktura dokumentacji (do uporządkowania w Fazie 5):

#### Business:
- `docs/business/` - dokumenty biznesowe (PL/EN)

#### Technical:
- `docs/knowledge/technical/` - dokumenty techniczne
- `docs/en/` - dokumentacja angielska
- `docs/pl/` - dokumentacja polska

#### QA:
- `docs/qa/` - dokumenty QA (PL/EN)

---

## 📊 Podsumowanie

### Pliki do usunięcia: **9 plików**
- 2 middleware
- 1 service
- 1 config
- 2 testy
- 4 dokumenty

### Pliki do modyfikacji: **~25 plików**
- Bootstrap, Controllers, Services, Models, Migrations, Tests, Dokumentacja

### Zależności:
- ✅ **RapidAPI:** Wszystkie zależności zidentyfikowane
- ✅ **TMDB:** Pełna implementacja, wymaga dokumentacji licencyjnej
- ❌ **TVmaze:** Brak implementacji - do utworzenia w Fazie 3
- ⚠️ **Subskrypcje:** Zależne od RapidAPI - wymaga refaktoryzacji

### Następne kroki:
1. ✅ Faza 1 zakończona - raport gotowy
2. ⏭️ Przejść do Fazy 2: Usunięcie integracji RapidAPI

---

**Status:** ✅ ANALIZA ZAKOŃCZONA - GOTOWE DO FAZY 2
