# 🚀 Plan Implementacji Integracji z RapidAPI Marketplace

**Data utworzenia:** 2025-01-27  
**Status:** ⏳ PENDING  
**Priorytet:** 🟡 Średni  
**Szacowany czas:** 3-4 tygodnie (120-160 godzin)

---

## 🎯 Cel

Zintegrować MovieMind API z RapidAPI Marketplace, aby umożliwić monetyzację API poprzez sprzedaż subskrypcji (Free, Pro, Enterprise).

---

## 📋 Przegląd Komponentów

### Co już mamy ✅
- ✅ Rate limiting (AdaptiveRateLimit middleware)
- ✅ Podstawowe endpointy API (Movies, People, TV Series, TV Shows)
- ✅ AI generation pipeline
- ✅ TMDb verification
- ✅ Basic Auth dla admin panelu
- ✅ Feature flags (Laravel Pennant)

### Co trzeba zaimplementować ❌
- ❌ API Key Authentication
- ❌ Subscription Plans (Free/Pro/Enterprise)
- ❌ Plan-based rate limiting
- ❌ Usage tracking
- ❌ Billing webhooks
- ❌ RapidAPI headers support
- ❌ Analytics dashboard

---

## 🗓️ Faza 1: Fundament (Tydzień 1-2)

### TASK-RAPI-001: API Key Authentication System

**Czas:** 16-20 godzin  
**Priorytet:** 🔴 Wysoki  
**Zależności:** Brak

#### Zadania:
1. **Model i migracja `api_keys`**
   ```php
   - id (UUID)
   - key (string, unique, hashed)
   - name (string) - opis klucza
   - user_id (nullable UUID) - dla przyszłych użytkowników
   - plan_id (UUID, FK) - przypisany plan
   - is_active (boolean)
   - last_used_at (timestamp, nullable)
   - expires_at (timestamp, nullable)
   - created_at, updated_at
   ```

2. **Service: `ApiKeyService`**
   - `generateKey()` - generowanie bezpiecznego klucza
   - `validateKey($key)` - walidacja klucza
   - `getKeyPlan($key)` - pobranie planu dla klucza
   - `trackUsage($key, $endpoint)` - śledzenie użycia

3. **Middleware: `RapidApiAuth`**
   - Weryfikacja header `X-RapidAPI-Key`
   - Fallback na `Authorization: Bearer {key}`
   - Walidacja klucza w bazie
   - Sprawdzenie czy klucz jest aktywny
   - Sprawdzenie czy klucz nie wygasł
   - Dodanie klucza do request attributes

4. **Controller: `ApiKeyController` (Admin)**
   - `index()` - lista kluczy
   - `store()` - tworzenie nowego klucza
   - `revoke()` - deaktywacja klucza
   - `regenerate()` - regeneracja klucza

5. **Testy:**
   - Unit tests dla `ApiKeyService`
   - Feature tests dla middleware
   - Feature tests dla admin endpoints

#### Pliki do utworzenia:
```
api/database/migrations/YYYY_MM_DD_HHMMSS_create_api_keys_table.php
api/app/Models/ApiKey.php
api/app/Services/ApiKeyService.php
api/app/Http/Middleware/RapidApiAuth.php
api/app/Http/Controllers/Admin/ApiKeyController.php
api/tests/Unit/Services/ApiKeyServiceTest.php
api/tests/Feature/ApiKeyAuthenticationTest.php
api/tests/Feature/Admin/ApiKeyManagementTest.php
```

#### Akceptacja:
- [ ] Klucze API można generować przez admin panel
- [ ] Middleware weryfikuje klucze z header `X-RapidAPI-Key`
- [ ] Nieprawidłowe/nieaktywne klucze zwracają 401
- [ ] Wszystkie testy przechodzą

---

### TASK-RAPI-002: Subscription Plans System

**Czas:** 12-16 godzin  
**Priorytet:** 🔴 Wysoki  
**Zależności:** Brak

#### Zadania:
1. **Model i migracja `subscription_plans`**
   ```php
   - id (UUID)
   - name (string) - 'free', 'pro', 'enterprise'
   - display_name (string) - 'Free', 'Pro', 'Enterprise'
   - description (text)
   - monthly_limit (integer) - limit zapytań/miesiąc
   - rate_limit_per_minute (integer)
   - features (json) - lista dostępnych funkcji
   - price_monthly (decimal, nullable)
   - price_yearly (decimal, nullable)
   - is_active (boolean)
   - created_at, updated_at
   ```

2. **Service: `PlanService`**
   - `getPlan($planId)` - pobranie planu
   - `getPlanByName($name)` - pobranie planu po nazwie
   - `getDefaultPlan()` - plan domyślny (Free)
   - `canUseFeature($plan, $feature)` - sprawdzenie dostępu do funkcji
   - `getRateLimit($plan, $endpoint)` - limit dla endpointu

3. **Seeder: `SubscriptionPlanSeeder`**
   ```php
   - Free: 100 req/month, rate limit: 10/min, features: ['read']
   - Pro: 10,000 req/month, rate limit: 100/min, features: ['read', 'generate', 'context_tags']
   - Enterprise: unlimited, rate limit: 1000/min, features: ['read', 'generate', 'context_tags', 'webhooks', 'analytics']
   ```

4. **Feature Flags Integration**
   - Rozszerzyć feature flags o `plan_required`
   - Middleware sprawdzający plan przed dostępem do funkcji

5. **Testy:**
   - Unit tests dla `PlanService`
   - Feature tests dla planów

#### Pliki do utworzenia:
```
api/database/migrations/YYYY_MM_DD_HHMMSS_create_subscription_plans_table.php
api/app/Models/SubscriptionPlan.php
api/app/Services/PlanService.php
api/database/seeders/SubscriptionPlanSeeder.php
api/tests/Unit/Services/PlanServiceTest.php
api/tests/Feature/SubscriptionPlansTest.php
```

#### Akceptacja:
- [ ] 3 plany (Free, Pro, Enterprise) w bazie
- [ ] Service zwraca poprawne limity dla planów
- [ ] Feature flags sprawdzają plan
- [ ] Wszystkie testy przechodzą

---

### TASK-RAPI-003: Plan-based Rate Limiting

**Czas:** 12-16 godzin  
**Priorytet:** 🔴 Wysoki  
**Zależności:** TASK-RAPI-001, TASK-RAPI-002

#### Zadania:
1. **Middleware: `PlanBasedRateLimit`**
   - Pobranie planu z API key
   - Sprawdzenie monthly limit (z `api_usage` tabeli)
   - Sprawdzenie per-minute rate limit
   - Zwracanie odpowiednich headers (X-RateLimit-*)
   - Różne limity dla różnych endpointów

2. **Service: `UsageTracker`**
   - `trackRequest($apiKey, $endpoint, $plan)` - logowanie requestu
   - `getMonthlyUsage($apiKey, $month)` - użycie w miesiącu
   - `getRemainingQuota($apiKey, $plan)` - pozostały limit
   - `resetMonthlyUsage()` - reset na początku miesiąca (scheduled job)

3. **Model i migracja `api_usage`**
   ```php
   - id (UUID)
   - api_key_id (UUID, FK)
   - plan_id (UUID, FK)
   - endpoint (string)
   - method (string)
   - response_status (integer)
   - response_time_ms (integer)
   - month (string) - 'YYYY-MM'
   - created_at
   - INDEX (api_key_id, month)
   - INDEX (created_at)
   ```

4. **Job: `ResetMonthlyUsageJob`**
   - Uruchamiany 1. dnia miesiąca
   - Resetuje liczniki użycia

5. **Testy:**
   - Unit tests dla `UsageTracker`
   - Feature tests dla rate limiting per plan
   - Testy przekroczenia limitów

#### Pliki do utworzenia:
```
api/database/migrations/YYYY_MM_DD_HHMMSS_create_api_usage_table.php
api/app/Models/ApiUsage.php
api/app/Services/UsageTracker.php
api/app/Http/Middleware/PlanBasedRateLimit.php
api/app/Jobs/ResetMonthlyUsageJob.php
api/tests/Unit/Services/UsageTrackerTest.php
api/tests/Feature/PlanBasedRateLimitTest.php
```

#### Akceptacja:
- [ ] Free plan: 100 req/month, 10/min
- [ ] Pro plan: 10,000 req/month, 100/min
- [ ] Enterprise: unlimited, 1000/min
- [ ] Headers X-RateLimit-* są poprawne
- [ ] 429 gdy limit przekroczony
- [ ] Wszystkie testy przechodzą

---

## 🗓️ Faza 2: Integracja RapidAPI (Tydzień 3)

### TASK-RAPI-004: RapidAPI Headers Support

**Czas:** 8-12 godzin  
**Priorytet:** 🟡 Średni  
**Zależności:** TASK-RAPI-001, TASK-RAPI-002

#### Zadania:
1. **Middleware: `RapidApiHeaders`**
   - Weryfikacja `X-RapidAPI-Proxy-Secret` (jeśli wymagane)
   - Weryfikacja `X-RapidAPI-User` (identyfikator użytkownika RapidAPI)
   - Weryfikacja `X-RapidAPI-Subscription` (plan użytkownika)
   - Mapowanie planów RapidAPI na nasze plany
   - Logowanie requestów z RapidAPI

2. **Service: `RapidApiService`**
   - `mapRapidApiPlan($rapidApiPlan)` - mapowanie planu
   - `validateRapidApiRequest($request)` - walidacja requestu
   - `getRapidApiUser($request)` - pobranie użytkownika

3. **Konfiguracja: `config/rapidapi.php`**
   ```php
   'proxy_secret' => env('RAPIDAPI_PROXY_SECRET'),
   'plan_mapping' => [
       'basic' => 'free',
       'pro' => 'pro',
       'ultra' => 'enterprise',
   ],
   ```

4. **Testy:**
   - Feature tests dla RapidAPI headers
   - Testy mapowania planów

#### Pliki do utworzenia:
```
api/app/Http/Middleware/RapidApiHeaders.php
api/app/Services/RapidApiService.php
api/config/rapidapi.php
api/tests/Feature/RapidApiHeadersTest.php
```

#### Akceptacja:
- [ ] Middleware akceptuje RapidAPI headers
- [ ] Plany są poprawnie mapowane
- [ ] Requesty z RapidAPI są logowane
- [ ] Wszystkie testy przechodzą

---

### TASK-RAPI-005: Billing Webhooks

**Czas:** 12-16 godzin  
**Priorytet:** 🟡 Średni  
**Zależności:** TASK-RAPI-001, TASK-RAPI-002

#### Zadania:
1. **Controller: `BillingWebhookController`**
   - `handleSubscriptionCreated()` - nowa subskrypcja
   - `handleSubscriptionUpdated()` - aktualizacja subskrypcji
   - `handleSubscriptionCancelled()` - anulowanie subskrypcji
   - `handlePaymentSucceeded()` - udana płatność
   - `handlePaymentFailed()` - nieudana płatność

2. **Service: `BillingService`**
   - `createSubscription($rapidApiUserId, $plan)` - tworzenie subskrypcji
   - `updateSubscription($subscriptionId, $plan)` - aktualizacja
   - `cancelSubscription($subscriptionId)` - anulowanie
   - `syncPlanFromRapidApi($rapidApiUserId, $rapidApiPlan)` - synchronizacja

3. **Model i migracja `subscriptions`**
   ```php
   - id (UUID)
   - api_key_id (UUID, FK)
   - rapidapi_user_id (string, nullable)
   - plan_id (UUID, FK)
   - status (enum: active, cancelled, expired)
   - current_period_start (timestamp)
   - current_period_end (timestamp)
   - cancelled_at (timestamp, nullable)
   - created_at, updated_at
   ```

4. **Webhook Security**
   - Weryfikacja podpisu webhooka (HMAC)
   - Idempotency keys (zapobieganie duplikatom)
   - Logowanie wszystkich webhooków

5. **Testy:**
   - Feature tests dla webhooków
   - Testy bezpieczeństwa (podpis, idempotency)

#### Pliki do utworzenia:
```
api/database/migrations/YYYY_MM_DD_HHMMSS_create_subscriptions_table.php
api/app/Models/Subscription.php
api/app/Services/BillingService.php
api/app/Http/Controllers/Admin/BillingWebhookController.php
api/tests/Feature/BillingWebhooksTest.php
```

#### Akceptacja:
- [ ] Webhooki są bezpiecznie weryfikowane
- [ ] Subskrypcje są synchronizowane z RapidAPI
- [ ] Idempotency zapobiega duplikatom
- [ ] Wszystkie testy przechodzą

---

## 🗓️ Faza 3: Analytics & Monitoring (Tydzień 4)

### TASK-RAPI-006: Usage Analytics Dashboard

**Czas:** 16-20 godzin  
**Priorytet:** 🟢 Niski  
**Zależności:** TASK-RAPI-003

#### Zadania:
1. **Controller: `AnalyticsController` (Admin)**
   - `overview()` - przegląd użycia
   - `byPlan()` - użycie per plan
   - `byEndpoint()` - użycie per endpoint
   - `byTimeRange($start, $end)` - użycie w zakresie czasu
   - `topApiKeys()` - najaktywniejsze klucze

2. **Service: `AnalyticsService`**
   - `getUsageStats($filters)` - statystyki użycia
   - `getRevenueStats($filters)` - statystyki przychodów
   - `getTopEndpoints($limit)` - najpopularniejsze endpointy
   - `getErrorRate($timeRange)` - wskaźnik błędów

3. **Resources: `AnalyticsResource`**
   - Formatowanie danych dla frontendu
   - Agregacje (daily, weekly, monthly)

4. **Testy:**
   - Feature tests dla analytics endpoints

#### Pliki do utworzenia:
```
api/app/Http/Controllers/Admin/AnalyticsController.php
api/app/Services/AnalyticsService.php
api/app/Http/Resources/AnalyticsResource.php
api/tests/Feature/Admin/AnalyticsTest.php
```

#### Akceptacja:
- [ ] Dashboard pokazuje użycie per plan
- [ ] Statystyki są poprawne
- [ ] Filtrowanie po czasie działa
- [ ] Wszystkie testy przechodzą

---

### TASK-RAPI-007: RapidAPI Publishing

**Czas:** 8-12 godzin  
**Priorytet:** 🟡 Średni  
**Zależności:** Wszystkie poprzednie zadania

#### Zadania:
1. **Przygotowanie API do publikacji**
   - Aktualizacja OpenAPI spec (dodanie RapidAPI headers)
   - Dokumentacja endpointów
   - Przykłady requestów/response
   - Error codes documentation

2. **Konfiguracja RapidAPI Hub**
   - Rejestracja API
   - Konfiguracja planów
   - Ustawienie webhooków
   - Testowanie w staging

3. **Monitoring**
   - Ustawienie alertów
   - Monitoring użycia
   - Error tracking

4. **Dokumentacja**
   - Quick start guide
   - API reference
   - Pricing information
   - FAQ

#### Pliki do utworzenia:
```
docs/RAPIDAPI_SETUP.md
docs/RAPIDAPI_PRICING.md
docs/RAPIDAPI_WEBHOOKS.md
```

#### Akceptacja:
- [ ] API jest opublikowane w RapidAPI Hub
- [ ] Plany są skonfigurowane
- [ ] Webhooki działają
- [ ] Dokumentacja jest kompletna

---

## 📊 Podsumowanie

### Timeline
- **Tydzień 1-2:** Faza 1 - Fundament (API Keys, Plans, Rate Limiting)
- **Tydzień 3:** Faza 2 - Integracja RapidAPI (Headers, Webhooks)
- **Tydzień 4:** Faza 3 - Analytics & Publishing

### Szacowany czas
- **Minimum:** 120 godzin (3 tygodnie, 40h/tydzień)
- **Realistycznie:** 160 godzin (4 tygodnie, 40h/tydzień)
- **Z buforem:** 200 godzin (5 tygodni, 40h/tydzień)

### Zależności
```
TASK-RAPI-001 (API Keys)
    ↓
TASK-RAPI-002 (Plans)
    ↓
TASK-RAPI-003 (Rate Limiting) ← TASK-RAPI-001, TASK-RAPI-002
    ↓
TASK-RAPI-004 (RapidAPI Headers) ← TASK-RAPI-001, TASK-RAPI-002
    ↓
TASK-RAPI-005 (Webhooks) ← TASK-RAPI-001, TASK-RAPI-002
    ↓
TASK-RAPI-006 (Analytics) ← TASK-RAPI-003
    ↓
TASK-RAPI-007 (Publishing) ← Wszystkie
```

### Metryki sukcesu
- ✅ API Keys działają i są bezpieczne
- ✅ Plany są poprawnie egzekwowane
- ✅ Rate limiting działa per plan
- ✅ Webhooki synchronizują subskrypcje
- ✅ Analytics pokazują użycie
- ✅ API jest opublikowane w RapidAPI

---

## 🔒 Bezpieczeństwo

### Wymagania bezpieczeństwa
1. **API Keys:**
   - Hashowanie kluczy w bazie (bcrypt)
   - Nigdy nie zwracanie pełnego klucza w response
   - Rotacja kluczy

2. **Webhooks:**
   - Weryfikacja podpisu HMAC
   - Idempotency keys
   - Rate limiting dla webhooków

3. **Rate Limiting:**
   - Ochrona przed abuse
   - Fair usage policy
   - Monitoring anomalii

---

## 📝 Notatki

### RapidAPI Requirements
- API musi akceptować header `X-RapidAPI-Key`
- API musi zwracać standardowe HTTP status codes
- API musi mieć dokumentację OpenAPI
- API musi mieć co najmniej 1 plan (Free)

### Pricing Strategy
- **Free:** 100 req/month - tylko read operations
- **Pro:** $9.99/month - 10,000 req/month - read + AI generation
- **Enterprise:** $99/month - unlimited - wszystkie funkcje + webhooks

### Revenue Sharing
- RapidAPI pobiera 20% od każdej transakcji
- Net revenue = 80% ceny subskrypcji

---

**Ostatnia aktualizacja:** 2025-01-27

